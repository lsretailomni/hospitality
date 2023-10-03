<?php

namespace Ls\Hospitality\Cron;

use Exception;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Api\ReplItemModifierRepositoryInterface;
use \Ls\Replication\Controller\Adminhtml\Deletion\LsTables;
use \Ls\Replication\Helper\ReplicationHelper;
use \Ls\Replication\Logger\Logger;
use \Ls\Replication\Model\ReplItemModifier;
use \Ls\Replication\Model\ResourceModel\ReplItemModifier\Collection;
use \Ls\Replication\Model\ResourceModel\ReplItemModifier\CollectionFactory as ReplItemModifierCollectionFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionValuesInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionValuesInterfaceFactory;
use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * To Process Item Modifiers into the Magento data structure.
 */
class ProcessItemModifier
{
    /** @var bool */
    public $cronStatus = false;

    /** @var int */
    public $remainingRecords;

    /** @var LSR */
    public $lsr;

    /** @var ReplicationHelper */
    public $replicationHelper;

    /** @var Logger */
    public $logger;

    /** @var StoreInterface $store */
    public $store;

    /** @var ReplItemModifierCollectionFactory */
    public $replItemModifierCollectionFactory;

    /** @var ReplItemModifierRepositoryInterface */
    public $replItemModifierRepositoryInterface;

    /** @var ProductRepositoryInterface */
    public $productRepository;

    /** @var ProductCustomOptionInterfaceFactory */
    public $customOptionFactory;

    /** @var ProductCustomOptionRepositoryInterface */
    public $optionRepository;

    /** @var ProductCustomOptionValuesInterfaceFactory */
    public $customOptionValueFactory;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @var string[]
     */
    public static $triggerFunctionToSkip = ['Infocode'];

    /**
     * @var LsTables
     */
    public  LsTables $lsTables;

    /**
     * ProcessItemModifier constructor.
     * @param ReplicationHelper $replicationHelper
     * @param Logger $logger
     * @param LSR $LSR
     * @param ReplItemModifierCollectionFactory $replItemModifierCollectionFactory
     * @param ReplItemModifierRepositoryInterface $replItemModifierRepositoryInterface
     * @param ProductRepositoryInterface $productRepository
     * @param ProductCustomOptionRepositoryInterface $optionRepository
     * @param ProductCustomOptionValuesInterfaceFactory $customOptionValueFactory
     * @param ProductCustomOptionInterfaceFactory $customOptionFactory
     * @param HospitalityHelper $hospitalityHelper
     * @param LsTables $lsTables
     */
    public function __construct(
        ReplicationHelper $replicationHelper,
        Logger $logger,
        LSR $LSR,
        ReplItemModifierCollectionFactory $replItemModifierCollectionFactory,
        ReplItemModifierRepositoryInterface $replItemModifierRepositoryInterface,
        ProductRepositoryInterface $productRepository,
        ProductCustomOptionRepositoryInterface $optionRepository,
        ProductCustomOptionValuesInterfaceFactory $customOptionValueFactory,
        ProductCustomOptionInterfaceFactory $customOptionFactory,
        HospitalityHelper $hospitalityHelper,
        LsTables $lsTables
    ) {
        $this->logger                              = $logger;
        $this->replicationHelper                   = $replicationHelper;
        $this->lsr                                 = $LSR;
        $this->replItemModifierCollectionFactory   = $replItemModifierCollectionFactory;
        $this->replItemModifierRepositoryInterface = $replItemModifierRepositoryInterface;
        $this->productRepository                   = $productRepository;
        $this->customOptionFactory                 = $customOptionFactory;
        $this->customOptionValueFactory            = $customOptionValueFactory;
        $this->optionRepository                    = $optionRepository;
        $this->hospitalityHelper                   = $hospitalityHelper;
        $this->lsTables                            = $lsTables;
    }

    /**
     * Entry point for cron
     *
     * @param mixed $storeData
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute($storeData = null)
    {
        if (!empty($storeData) && $storeData instanceof StoreInterface) {
            $stores = [$storeData];
        } else {
            /** @var StoreInterface[] $stores */
            $stores = $this->lsr->getAllStores();
        }
        if (!empty($stores)) {
            foreach ($stores as $store) {
                $this->lsr->setStoreId($store->getId());
                $this->store = $store;
                if ($this->lsr->isLSR($this->store->getId())
                    && $this->lsr->isHospitalityStore($store->getId())
                ) {
                    $this->replicationHelper->updateConfigValue(
                        $this->replicationHelper->getDateTime(),
                        LSR::SC_ITEM_MODIFIER_CONFIG_PATH_LAST_EXECUTE,
                        $this->store->getId(),
                        ScopeInterface::SCOPE_STORES
                    );
                    $this->logger->debug('Running ProcessItemModifier Task for store ' . $this->store->getName());
                    $this->processItemModifiers();
                    $this->replicationHelper->updateCronStatus(
                        $this->cronStatus,
                        LSR::SC_SUCCESS_CRON_ITEM_MODIFIER,
                        $this->store->getId(),
                        false,
                        ScopeInterface::SCOPE_STORES
                    );
                    $this->logger->debug(
                        'End ProcessItemModifier Task with remaining : ' . $this->getRemainingRecords()
                    );
                }
                $this->lsr->setStoreId(null);
            }
        }
    }

    /**
     * Execute manually
     *
     * @param mixed $storeData
     * @return int[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function executeManually($storeData = null)
    {
        $this->execute($storeData);
        $remainingRecords = (int)$this->getRemainingRecords(true);
        return [$remainingRecords];
    }

    /**
     * Item modifier processing
     *
     * @return void
     * @throws LocalizedException
     */
    public function processItemModifiers()
    {
        //TODO cover the delete scenario.
        $coreConfigTableName = $this->replicationHelper->getGivenTableName('core_config_data');
        $batchSize           = $this->hospitalityHelper->getItemModifiersBatchSize();
        $filters             = [
            ['field' => 'main_table.scope_id', 'value' => $this->getScopeId(), 'condition_type' => 'eq']
        ];

        $criteria            = $this->replicationHelper->buildCriteriaForArrayWithAlias(
            $filters,
            $batchSize,
            false
        );
        /** @var Collection $collection */
        $collection          = $this->replItemModifierCollectionFactory->create();
        $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
            $collection,
            $criteria,
            'nav_id',
            null,
            ['repl_item_modifier_id']
        );
        if ($collection->getSize() > 0) {
            $dataToProcess = $this->formatModifier($collection);
            $this->process($dataToProcess);
            $remainingItems = (int)$this->getRemainingRecords();
            if ($remainingItems == 0) {
                $this->cronStatus = true;
            }
            $this->lsTables->resetSpecificCronData("repl_hierarchy_hosp_deal",$this->getScopeId(),$coreConfigTableName);
        } else {
            $this->cronStatus = true;
        }
    }

    /**
     * Arrange modifier data
     *
     * @param $itemModifiers
     * @param null $sku
     * @param bool $isNotDeal
     * @return array
     */
    public function formatModifier($itemModifiers, $sku = null, $isNotDeal = true)
    {
        $dataToProcess = null;
        /**
         * There are types of Modifiers which we dont need to process
         * i-e All modifiers whose TriggerFunction = Infocode
         **/
        foreach ($itemModifiers->getItems() as $itemModifier) {
            if (empty($sku)) {
                $itemId = $itemModifier->getNavId();
            } else {
                $itemId = $sku;
            }
            if (!in_array($itemModifier->getTriggerFunction(), self::$triggerFunctionToSkip)) {
                $dataToProcess['data'][$itemId][$itemModifier->getCode()]
                [$itemModifier->getSubCode()] = $itemModifier;
                if ($itemModifier->getGroupMaxSelection()) {
                    $dataToProcess[$itemModifier->getCode()] ['max_select'] = $itemModifier->getGroupMaxSelection();
                }

                if ($itemModifier->getGroupMinSelection()) {
                    $dataToProcess[$itemModifier->getCode()] ['min_select'] = $itemModifier->getGroupMinSelection();
                }
            } else {
                $dataToProcess[$itemModifier->getTriggerCode()] ['min_select'] = $itemModifier->getMinSelection();
                $dataToProcess[$itemModifier->getTriggerCode()] ['max_select'] = $itemModifier->getMaxSelection();
                if ($isNotDeal) {
                    $itemModifier->setProcessed(1)
                        ->setProcessedAt($this->replicationHelper->getDateTime())
                        ->setIsUpdated(0);
                    $this->replItemModifierRepositoryInterface->save($itemModifier);
                }
            }
        }

        return $dataToProcess;
    }

    /**
     * Process formatted modifiers data
     *
     * @param $dataToProcess
     * @param bool $isNotDeal
     * @return void
     */
    public function process($dataToProcess, $isNotDeal = true)
    {
        if (!empty($dataToProcess)) {
            // loop against each Product.
            $deleteSubCodeArr   = [];
            foreach ($dataToProcess['data'] as $itemSKU => $optionArray) {
                // generate options.
                $productOptions     = [];

                if (!empty($optionArray)) {
                    // get Product Repository;
                    /** @var  $product */
                    try {
                        $product         = $this->replicationHelper->getProductDataByIdentificationAttributes(
                            $itemSKU,
                            '',
                            '',
                            $this->store->getId()
                        );
                        $existingOptions = $this->optionRepository->getProductOptions($product);

                        foreach ($optionArray as $optionCode => $optionValuesArray) {
                            $isOptionExist         = false;
                            $ls_modifier_recipe_id = LSR::LSR_ITEM_MODIFIER_PREFIX . $optionCode;
                            if (!empty($existingOptions)) {
                                foreach ($existingOptions as $existingOption) {
                                    if ($existingOption->getData('ls_modifier_recipe_id') ==
                                        $ls_modifier_recipe_id) {
                                        $isOptionExist = true;
                                        $productOption = $existingOption;
                                        break;
                                    }
                                }
                            }
                            if (!$isOptionExist) {
                                /** @var ProductCustomOptionInterface $productOption */
                                $productOption = $this->customOptionFactory->create();
                            }
                            $optionNeedsToBeUpdated = false;
                            $isOptionValueDeleted   = false;
                            if (!empty($optionValuesArray)) {
                                $optionData = [];
                                /** @var ReplItemModifier $optionValueData */
                                foreach ($optionValuesArray as $subcode => $optionValueData) {
                                    $existingOptionValues = $productOption->getValues();
                                    /** @var ProductCustomOptionValuesInterface $optionValue */
                                    if ($optionValueData->getExplanatoryHeaderText() != '') {
                                        $title = $optionValueData->getExplanatoryHeaderText();
                                    } else {
                                        $title = $optionValueData->getCode();
                                    }
                                    /**
                                     * Dev Notes:
                                     * For the Option Values, we are only checking the duplication based on title.
                                     */
                                    $isOptionValueExist = false;

                                    if (!empty($existingOptionValues)) {
                                        foreach ($existingOptionValues as $existingOptionValue) {
                                            //Collect the deleted item modifiers
                                            if(($existingOptionValue->getSortOrder() == $optionValueData->getSubCode())
                                                && $optionValueData->getIsDeleted()
                                            ) {
                                                $isOptionValueDeleted   = true;
                                                $isOptionValueExist     = true;
                                                $optionData['title']    = $title;
                                                $optionData ['code']    = $optionValueData->getCode();
                                                $deleteSubCodeArr[]     = $optionValueData->getCode()."-".$optionValueData->getSubCode();
                                                break;
                                            }
                                        }

                                        foreach ($existingOptionValues as $existingOptionValue) {
                                            //unset the data if deleted item already in optionData
                                            if(in_array($optionValueData->getCode()."-".$existingOptionValue->getSortOrder(),$deleteSubCodeArr)) {
                                               unset($optionData['values'][$optionValueData->getCode()."-".$existingOptionValue->getSortOrder()]);
                                               continue;
                                            }
                                            $optionData['values'][$optionValueData->getCode()."-".$existingOptionValue->getSortOrder()] = $existingOptionValue;
                                            if ($existingOptionValue->getTitle() ==
                                                $optionValueData->getDescription()) {
                                                $isOptionValueExist = true;
                                                break;
                                            }
                                        }
                                    }
                                    if (!$isOptionValueExist) {
                                        $optionNeedsToBeUpdated = true;
                                        $optionValue            = $this->customOptionValueFactory->create();
                                        $optionValue->setTitle($optionValueData->getDescription())
                                            ->setPriceType('fixed')
                                            ->setSortOrder($subcode)
                                            ->setSku($subcode)
                                            ->setPrice($optionValueData->getAmountPercent());

                                        if (!empty($optionValueData->getTriggerCode())) {
                                            $replImage = $this->hospitalityHelper->getImageGivenItem(
                                                $optionValueData->getTriggerCode(),
                                                $this->getScopeId()
                                            );

                                            if ($replImage) {
                                                $swatchPath = $this->hospitalityHelper->getImage(
                                                    $replImage->getImageId()
                                                );

                                                if (!empty($swatchPath)) {
                                                    $optionValue->setSwatch($swatchPath);
                                                }
                                            }
                                        }
                                        $optionData['values'][] = $optionValue;
                                        $optionData['title']    = $title;
                                        $optionData ['code']    = $optionValueData->getCode();
                                    }

                                    if ($isNotDeal) {
                                        $optionValueData->setProcessed(1)
                                            ->setProcessedAt($this->replicationHelper->getDateTime())
                                            ->setIsUpdated(0);

                                        $this->replItemModifierRepositoryInterface->save($optionValueData);
                                    }
                                }

                                if ($optionNeedsToBeUpdated || $isOptionValueDeleted) {
                                    try {
                                        if($productOption &&
                                            (!array_key_exists('values',$optionData) ||
                                                (array_key_exists('values',$optionData)
                                                    && count($optionData['values']) == 0)
                                            )
                                        ){
                                            //Remove custom option if all option values are deleted
                                            $this->optionRepository->delete($productOption);
                                        } else {
                                            // check if Option
                                            $productOption->setTitle($optionData['title'])
                                                ->setPrice('')
                                                ->setPriceType('fixed')
                                                ->setValues($optionData['values'])
                                                ->setIsRequire(0)
                                                ->setType('drop_down')
                                                ->setData('ls_modifier_recipe_id', $ls_modifier_recipe_id)
                                                ->setProductSku($itemSKU)
                                                ->setSwatch(
                                                    $this->hospitalityHelper->getFirstAvailableOptionValueImagePath(
                                                        $optionData['values']
                                                    )
                                                );
                                            if (isset($dataToProcess[$optionData['code']])) {
                                                if (isset($dataToProcess[$optionData['code']]['min_select']) &&
                                                    $dataToProcess[$optionData['code']]['min_select'] >= 1) {
                                                    $productOption->setIsRequire(true);
                                                }

                                                if (isset($dataToProcess[$optionData['code']]['max_select']) &&
                                                    $dataToProcess[$optionData['code']]['max_select'] > 1) {
                                                    $productOption->setType('multiple');
                                                }
                                            }
                                            $savedProductOption = $this->optionRepository->save($productOption);
                                            $product->addOption($savedProductOption);
                                            if (!$product->getHasOptions()) {
                                                $product->setHasOptions(1);
                                                $product = $this->productRepository->save($product);
                                            }
                                        }

                                    } catch (Exception $e) {
                                        $this->logger->error($e->getMessage());
                                        $this->logger->error(
                                            sprintf(
                                                'Error while creating options for %s for product %s for store %s',
                                                $optionCode,
                                                $itemSKU,
                                                $this->store->getName()
                                            )
                                        );
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $this->logger->error($e->getMessage());
                        $this->logger->error(
                            'Error while creating modifiers for  ' . $itemSKU . ' for store ' . $this->store->getName()
                        );
                    }
                }
            }
        }
    }

    /**
     * Get remaining records
     *
     * @param bool $forceReload
     * @return int
     * @throws LocalizedException
     */
    public function getRemainingRecords(
        $forceReload = false
    ) {
        if (!$this->remainingRecords || $forceReload) {
            $filters = [
                ['field' => 'main_table.scope_id', 'value' => $this->getScopeId(), 'condition_type' => 'eq']
            ];

            $criteria   = $this->replicationHelper->buildCriteriaForArrayWithAlias(
                $filters,
                -1,
                1
            );
            $collection = $this->replItemModifierCollectionFactory->create();
            $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
                $collection,
                $criteria,
                'nav_id',
                null,
                ['repl_item_modifier_id']
            );
            $this->remainingRecords = $collection->getSize();
        }
        return $this->remainingRecords;
    }

    /**
     * Setting up store
     *
     * @param $store
     * @return void
     */
    public function setStore($store)
    {
        $this->store = $store;
    }

    /**
     * Get current scope id
     *
     * @return int
     */
    public function getScopeId()
    {
        return $this->store->getWebsiteId();
    }
}
