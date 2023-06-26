<?php

namespace Ls\Hospitality\Cron;

use Exception;
use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Model\ReplDataTranslation;
use \Ls\Replication\Api\ReplDataTranslationRepositoryInterface;
use \Ls\Replication\Helper\ReplicationHelper;
use \Ls\Replication\Logger\Logger;
use \Ls\Replication\Model\ResourceModel\ReplDataTranslation\CollectionFactory as ReplDataTranslationCollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product;
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
     * @var ReplicationHelper
     */
    public $replicationHelper;

    /**
     * @var ReplDataTranslationRepositoryInterface
     */
    public $dataTranslationRepository;

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var StoreInterface $store
     */
    public $store;

    /**
     * @var bool
     */
    public $cronStatus = false;

    /**
     * @var Product
     */
    public $productResourceModel;

    /**
     * @var ProductRepositoryInterface
     */
    public $productRepository;

    /**
     * @var ReplDataTranslationCollectionFactory
     */
    public $replDataTranslationCollectionFactory;

    /**
     * @param ReplicationHelper $replicationHelper
     * @param ReplDataTranslationRepositoryInterface $dataTranslationRepository
     * @param LSR $LSR
     * @param Logger $logger
     * @param Product $productResourceModel
     * @param ProductRepositoryInterface $productRepository
     * @param ReplDataTranslationCollectionFactory $replDataTranslationCollectionFactory
     */
    public function __construct(
        ReplicationHelper $replicationHelper,
        ReplDataTranslationRepositoryInterface $dataTranslationRepository,
        LSR $LSR,
        Logger $logger,
        Product $productResourceModel,
        ProductRepositoryInterface $productRepository,
        ReplDataTranslationCollectionFactory $replDataTranslationCollectionFactory
    ) {
        $this->replicationHelper                    = $replicationHelper;
        $this->dataTranslationRepository            = $dataTranslationRepository;
        $this->lsr                                  = $LSR;
        $this->logger                               = $logger;
        $this->productResourceModel                 = $productResourceModel;
        $this->productRepository                    = $productRepository;
        $this->replDataTranslationCollectionFactory = $replDataTranslationCollectionFactory;
    }

    /**
     * Entry point for cron running automatically
     *
     * @param mixed $storeData
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute($storeData = null)
    {
        if (!empty($storeData) && $storeData instanceof StoreInterface) {
            $stores = [$storeData];
        } else {
            $stores = $this->lsr->getAllStores();
        }
        if (!empty($stores)) {
            foreach ($stores as $store) {
                $this->lsr->setStoreId($store->getId());
                $this->store = $store;
                if ($this->lsr->isLSR($this->store->getId())) {
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
                        $this->cronStatus = $itemsStatus;

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
     * @param string $sku
     * @param null $productData
     * @return bool
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
                $sku = $dataTranslation->getKey();
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
     * Get filter given values
     *
     * @param string $scopeId
     * @param string $langCode
     * @param string $translationId
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
     * @throws NoSuchEntityException|LocalizedException
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
