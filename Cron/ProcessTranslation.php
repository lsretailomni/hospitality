<?php
declare(strict_types=1);

namespace Ls\Hospitality\Cron;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Model\ReplDataTranslation;
use \Ls\Replication\Api\ReplDataTranslationRepositoryInterface;
use \Ls\Replication\Helper\ReplicationHelper;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Replication\Logger\Logger;
use \Ls\Replication\Model\ResourceModel\ReplDataTranslation\CollectionFactory as ReplDataTranslationCollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\CustomOptions;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Cron responsible to update translations for deals
 */
class ProcessTranslation
{
    /**
     * @var StoreInterface $store
     */
    public $store;

    /**
     * @var bool
     */
    public $cronStatus = false;

    /**
     * @param ReplicationHelper $replicationHelper
     * @param ReplDataTranslationRepositoryInterface $dataTranslationRepository
     * @param LSR $lsr
     * @param Logger $logger
     * @param Product $productResourceModel
     * @param ProductRepositoryInterface $productRepository
     * @param ReplDataTranslationCollectionFactory $replDataTranslationCollectionFactory
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        public ReplicationHelper $replicationHelper,
        public ReplDataTranslationRepositoryInterface $dataTranslationRepository,
        public LSR $lsr,
        public Logger $logger,
        public Product $productResourceModel,
        public ProductRepositoryInterface $productRepository,
        public ReplDataTranslationCollectionFactory $replDataTranslationCollectionFactory,
        public HospitalityHelper $hospitalityHelper
    ) {
    }

    /**
     * Entry point for cron running automatically
     *
     * @param mixed $storeData
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException|GuzzleException
     */
    public function execute($storeData = null)
    {
        if (!$this->lsr->isSSM()) {
            if (!empty($storeData) && $storeData instanceof StoreInterface) {
                $stores = [$storeData];
            } else {
                $stores = $this->lsr->getAllStores();
            }
        } else {
            $stores = [$this->lsr->getAdminStore()];
        }
        if (!empty($stores)) {
            foreach ($stores as $store) {
                $this->lsr->setStoreId($store->getId());
                $this->store = $store;
                if ($this->lsr->isLSR($this->store->getId()) && $this->lsr->isHospitalityStore($store->getId())) {
                    $langCode = $this->lsr->getStoreConfig(
                        LSR::SC_STORE_DATA_TRANSLATION_LANG_CODE,
                        $store->getId()
                    );
                    $this->logger->debug('DataTranslationDealHtmlTask Started for Store ' . $store->getName());
                    try {
                        if ($langCode == "Default") {
                            $langCode = null;
                        }
                        $itemsStatus      = $this->updateDeal($store, $langCode);
                        $modifierStatus   = $this->updateModifiersRecipe($store, $langCode);
                        $this->cronStatus = $itemsStatus && $modifierStatus;

                    } catch (Exception $e) {
                        $this->logDetailedException(__METHOD__, $this->store->getName(), '');
                        $this->logger->debug($e->getMessage());
                    }

                    $this->replicationHelper->updateConfigValue(
                        $this->replicationHelper->getDateTime(),
                        LSR::SC_PROCESS_TRANSLATION_CONFIG_PATH_LAST_EXECUTE,
                        $store->getId(),
                        ScopeInterface::SCOPE_STORES
                    );
                    $this->replicationHelper->updateCronStatus(
                        $this->cronStatus,
                        LSR::SC_SUCCESS_PROCESS_TRANSLATION,
                        $store->getId(),
                        false,
                        ScopeInterface::SCOPE_STORES
                    );
                    $this->logger->debug('DataTranslationTask Completed for Store ' . $store->getName());
                }

                $this->lsr->setStoreId(null);
            }
        }
    }


    /**
     * Cater translation of products name and description
     *
     * @param $store
     * @param $langCode
     * @return bool
     * @throws LocalizedException
     */
    public function updateDeal($store, $langCode)
    {
        $filters    = $this->getFiltersGivenValues(
            $store->getId(),
            $langCode,
            LSR::SC_TRANSLATION_ID_DEAL_ITEM_HTML
        );
        $criteria   = $this->replicationHelper->buildCriteriaForArrayWithAlias($filters, -1);
        $collection = $this->replDataTranslationCollectionFactory->create();
        $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
            $collection,
            $criteria,
            'key',
            null,
            ['repl_data_translation_id']
        );
        $websiteId = $store->getWebsiteId();
        $this->replicationHelper->applyProductWebsiteJoin($collection, $websiteId);
        $query = $collection->getSelect()->__toString();
        /** @var ReplDataTranslation $dataTranslation */
        foreach ($collection as $dataTranslation) {
            try {
                $sku         = $dataTranslation->getKey();
                $productData = $this->replicationHelper->getProductDataByIdentificationAttributes(
                    $sku,
                    '',
                    '',
                    $store->getId()
                );
                if (isset($productData)) {
                    if ($dataTranslation->getTranslationId() == LSR::SC_TRANSLATION_ID_DEAL_ITEM_HTML) {
                        $productData->setDescription($dataTranslation->getText());
                        $this->productResourceModel->saveAttribute($productData, 'description');
                    }
                }
            } catch (Exception $e) {
                $this->logDetailedException(__METHOD__, $this->store->getName(), $dataTranslation->getKey());
                $this->logger->debug($e->getMessage());
                $dataTranslation->setData('is_failed', 1);
            }
            $dataTranslation->setData('processed_at', $this->replicationHelper->getDateTime());
            $dataTranslation->setData('processed', 1);
            $dataTranslation->setData('is_updated', 0);
            $dataTranslation->setData('is_failed', 0);
            // @codingStandardsIgnoreLine
            $this->dataTranslationRepository->save($dataTranslation);
        }

        return $collection->getSize() == 0;
    }

    /**
     * Cater translation of modifiers select text and description
     *
     * @param $store
     * @param $langCode
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function updateModifiersRecipe($store, $langCode)
    {
        $storeId           = $this->lsr->getStoreId();
        $productCollection = null;
        $filters           = $this->getFiltersGivenValues(
            $store->getId(),
            $langCode,
            [
                LSR::SC_TRANSLATION_ID_DEAL_MODIFIER_SELECT,
                LSR::SC_TRANSLATION_ID_DEAL_MODIFIER_DESC,
                LSR::SC_TRANSLATION_ID_RECIPE_DESC
            ]
        );
        $criteria          = $this->replicationHelper->buildCriteriaForArrayWithAlias($filters, -1);
        $collection        = $this->replDataTranslationCollectionFactory->create();
        $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
            $collection,
            $criteria,
            null,
            null,
            ['repl_data_translation_id']
        );
        $websiteId = $store->getWebsiteId();
        $query     = $collection->getSelect()->__toString();
        /** @var ReplDataTranslation $dataTranslation */
        foreach ($collection as $dataTranslation) {
            try {
                if ($dataTranslation->getTranslationId() == LSR::SC_TRANSLATION_ID_DEAL_MODIFIER_SELECT) {
                    $lsModifierRecipeIds = LSR::LSR_ITEM_MODIFIER_PREFIX . trim($dataTranslation->getKey());
                    $this->processModifiersRecipeTranslation($lsModifierRecipeIds, $storeId, $dataTranslation);
                } elseif ($dataTranslation->getTranslationId() == LSR::SC_TRANSLATION_ID_RECIPE_DESC) {
                    $lsModifierRecipeIds = LSR::LSR_RECIPE_PREFIX;
                    $modifier            = explode(";", $dataTranslation->getKey());
                    $sku                 = '';
                    if (is_array($modifier) && count($modifier) > 1) {
                        $recipe = $this->hospitalityHelper->getRecipeByLineNumber($modifier[0], $modifier[1]);
                        if ($recipe) {
                            $sku = current($recipe)->getItemNo();
                        }
                    }
                    $this->processModifiersRecipeTranslation($lsModifierRecipeIds, $storeId, $dataTranslation, $sku);
                } elseif (!empty($dataTranslation->getKey())) {
                    $modifier = explode(";", $dataTranslation->getKey());
                    if (is_array($modifier)) {
                        $lsModifierRecipeIds = LSR::LSR_ITEM_MODIFIER_PREFIX . $modifier[0];
                    } else {
                        $lsModifierRecipeIds = LSR::LSR_ITEM_MODIFIER_PREFIX . trim($dataTranslation->getKey());
                    }
                    $this->processModifiersRecipeTranslation($lsModifierRecipeIds, $storeId, $dataTranslation);
                }

            } catch (Exception $e) {
                $this->logDetailedException(__METHOD__, $this->store->getName(), $dataTranslation->getKey());
                $this->logger->debug($e->getMessage());
                $dataTranslation->setData('is_failed', 1);
            }
            $dataTranslation->setData('processed_at', $this->replicationHelper->getDateTime());
            $dataTranslation->setData('processed', 1);
            $dataTranslation->setData('is_updated', 0);
            $dataTranslation->setData('is_failed', 0);
            // @codingStandardsIgnoreLine
            $this->dataTranslationRepository->save($dataTranslation);
        }

        return $collection->getSize() == 0;
    }

    /**
     * Process modifier and recipe option title and modifier option value title translations
     *
     * @param $lsModifierRecipeIds
     * @param $storeId
     * @param $dataTranslation
     * @param null $sku
     * @return void
     */
    public function processModifiersRecipeTranslation($lsModifierRecipeIds, $storeId, $dataTranslation, $sku = null)
    {
        if (!empty($lsModifierRecipeIds)) {
            try {
                $productCollection = $this->replicationHelper->getProductsByRecipeId($lsModifierRecipeIds, $sku);

                if ($productCollection) {
                    foreach ($productCollection as $productObj) {
                        $product          = $this->productRepository->getById(
                            $productObj->getId(),
                            false,
                            $storeId
                        );
                        $customOptionsArr = [];
                        foreach ($product->getOptions() as $customOption) {
                            $optionKey = ($customOption->getLsModifierRecipeId()) ?
                                str_replace("ls_mod_", "", $customOption->getLsModifierRecipeId())
                                : "";
                            if ($customOption->getValues()) {
                                $values          = $customOption->getValues();
                                $newOptionValues = [];
                                if ($dataTranslation->getKey() == $optionKey
                                    && !empty($dataTranslation->getText())
                                    && $dataTranslation->getTranslationId() ==
                                    LSR::SC_TRANSLATION_ID_DEAL_MODIFIER_SELECT
                                ) {
                                    $customOption->setTitle($dataTranslation->getText());
                                    $customOption->setStoreTitle($dataTranslation->getText());
                                    $customOption->setStoreId($storeId);
                                }
                                foreach ($values as $value) {
                                    if ($dataTranslation->getTranslationId() == LSR::SC_TRANSLATION_ID_RECIPE_DESC) {
                                        $valueKey = $value->getSku();
                                    } else {
                                        $valueKey = $optionKey . ";" . $value->getSku();
                                    }
                                    if ((str_starts_with($dataTranslation->getKey(), $valueKey) || $sku == $valueKey)
                                        && !empty($dataTranslation->getText())
                                        && ($dataTranslation->getTranslationId() ==
                                            LSR::SC_TRANSLATION_ID_DEAL_MODIFIER_DESC
                                            || $dataTranslation->getTranslationId() ==
                                            LSR::SC_TRANSLATION_ID_RECIPE_DESC)
                                    ) {
                                        $value->setTitle($dataTranslation->getText());
                                        $value->setStoreId($storeId);
                                    }
                                    $newOptionValues[] = $value;
                                }
                                if (!empty($newOptionValues)) {
                                    $customOption->setValues($newOptionValues);
                                    $customOptionsArr[] = $customOption;
                                }
                            }
                        }
                        if (!empty($customOptionsArr)) {
                            $product->setOptions($customOptionsArr);
                            $product->setStoreId($storeId);
                            $this->productRepository->save($product);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logDetailedException(__METHOD__, $this->store->getName(), $dataTranslation->getKey());
                $this->logger->debug($e->getMessage());
            }
        }
    }

    /**
     * Get filter given values
     *
     * @param string $scopeId
     * @param string $langCode
     * @param $translationId
     * @return array[]
     */
    public function getFiltersGivenValues($scopeId, $langCode, $translationId)
    {
        $conditionType = ($langCode) ? 'eq' : 'null';
        return [
            ['field' => 'main_table.scope_id', 'value' => $scopeId, 'condition_type' => 'eq'],
            ['field' => 'main_table.LanguageCode', 'value' => $langCode, 'condition_type' => $conditionType],
            [
                'field'          => 'main_table.TranslationId',
                'value'          => $translationId,
                'condition_type' => 'in'
            ],
            ['field' => 'main_table.text', 'value' => true, 'condition_type' => 'notnull'],
            ['field' => 'main_table.key', 'value' => true, 'condition_type' => 'notnull']
        ];
    }

    /**
     * Execute manually
     *
     * @param mixed $storeData
     * @return int[]
     * @throws NoSuchEntityException|LocalizedException|GuzzleException
     */
    public function executeManually($storeData = null)
    {
        $this->execute($storeData);
        return [0];
    }


    /**
     * Log Detailed exception
     *
     * @param $method
     * @param $storeName
     * @param $itemId
     * @return void
     */
    public function logDetailedException($method, $storeName, $itemId)
    {
        $this->logger->debug(
            sprintf(
                'Exception happened in %s for store: %s, item id: %s',
                $method,
                $storeName,
                $itemId
            )
        );
    }
}
