<?php

namespace Ls\Hospitality\Plugin\Replication\Cron;

use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Cron\SyncInventory;
use \Ls\Hospitality\Model\Order\CheckAvailability;
use \Ls\Replication\Helper\ReplicationHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Plugin to update ls_current_availability attribute after inventory sync
 */
class SyncInventoryPlugin
{
    /**
     * @var LSR
     */
    private $lsr;
    /**
     * @var CheckAvailability
     */
    private $checkAvailability;

    /**
     * @var ReplicationHelper
     */
    private $replicationHelper;

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var ProductResourceModel
     */
    private $productResourceModel;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LSR $lsr
     * @param CheckAvailability $checkAvailability
     * @param ReplicationHelper $replicationHelper
     * @param CollectionFactory $productCollectionFactory
     * @param ProductResourceModel $productResourceModel
     * @param CacheInterface $cache
     * @param LoggerInterface $logger
     */
    public function __construct(
        LSR $lsr,
        CheckAvailability $checkAvailability,
        ReplicationHelper $replicationHelper,
        CollectionFactory $productCollectionFactory,
        ProductResourceModel $productResourceModel,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->lsr                      = $lsr;
        $this->checkAvailability        = $checkAvailability;
        $this->replicationHelper        = $replicationHelper;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productResourceModel     = $productResourceModel;
        $this->cache                    = $cache;
        $this->logger                   = $logger;
    }

    /**
     * After inventory sync, update ls_current_availability attribute
     *
     * @param SyncInventory $subject
     * @param $result
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function afterExecute(SyncInventory $subject, $result)
    {
        $storeId = $subject->getScopeId();

        if (!$this->lsr->isHospitalityStore($storeId)) {
            return $result;
        }

        try {
            $this->updateCurrentAvailability($storeId);
            $this->logger->info('Hospitality availability attributes updated for store: ' . $storeId);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update availability: ' . $e->getMessage());
        }
        $this->lsr->setStoreId(null);
        return $result;
    }

    /**
     * Update ls_current_availability attribute based on availability data from LS Central
     *
     * @param $storeId
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Exception
     */
    private function updateCurrentAvailability($storeId)
    {
        $availabilityMap = $this->checkAvailability->checkCatalogAvailability($storeId);

        if (empty($availabilityMap)) {
            $this->logger->warning('No availability data received from LS Central');
            return;
        }

        $processedProductIds = [];
        $cachedProductIdsToClean = [];

        foreach ($availabilityMap as $itemId => $uomData) {
            foreach ($uomData as $uom => $quantity) {
                try {
                    $product = $this->replicationHelper->getProductDataByIdentificationAttributes(
                        $itemId,
                        '',
                        $uom,
                        $storeId
                    );
                } catch (NoSuchEntityException $e) {
                    try {
                        $product = $this->replicationHelper->getProductDataByIdentificationAttributes(
                            $itemId,
                            '',
                            '',
                            $storeId
                        );
                    } catch (NoSuchEntityException $e) {
                        $this->logger->debug(sprintf(
                            'Product not found for Item: %s - %s in Sync Inventory Hospitality',
                            $itemId,
                            $e->getMessage()
                        ));
                        continue;
                    }
                } catch (\Exception $e) {
                    $this->logger->debug(sprintf(
                        'Could not process product Item: %s, UOM: %s - %s in Sync Inventory Hospitality',
                        $itemId,
                        $uom,
                        $e->getMessage()
                    ));
                    continue;
                }

                if (!$product || !$product->getId()) {
                    continue;
                }

                $processedProductIds[] = $product->getId();

                if ($quantity <= 0) {
                    if ($product->getData(LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE) != 1) {
                        $product->setData(LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE, 1);
                        $this->productResourceModel->saveAttribute($product, LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE);
                        $cachedProductIdsToClean[] = $product->getId();
                    }
                } else {
                    if ($product->getData(LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE) != 0) {
                        $product->setData(LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE, 0);
                        $this->productResourceModel->saveAttribute($product, LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE);
                        $cachedProductIdsToClean[] = $product->getId();
                    }
                }
            }
        }

        if (!empty($cachedProductIdsToClean)) {
            $this->replicationHelper->flushFpcCacheAgainstIds($cachedProductIdsToClean);
        }

        $this->resetMissingUnavailableProducts($processedProductIds, $storeId);
    }

    /**
     * Reset products previously marked as unavailable but missing in the current availability map
     *
     * @param $processedProductIds
     * @param $storeId
     * @return void
     * @throws \Exception
     */
    private function resetMissingUnavailableProducts($processedProductIds, $storeId)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->joinAttribute(
            LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE,
            'catalog_product/' . LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE,
            'entity_id',
            null,
            'left',
            $storeId
        );

        $collection->addAttributeToFilter(LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE, ['eq' => '1']);

        if (!empty($processedProductIds)) {
            $collection->addFieldToFilter('entity_id', ['nin' => $processedProductIds]);
        }

        if ($collection->count() === 0) {
            $this->logger->debug('No unavailable products to reset for store: ' . $storeId);
            return;
        }

        $resetCount = 0;
        $cachedProductIdsToClean = [];
        foreach ($collection as $product) {
            $product->setData(LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE, 0); // Reset to available
            $this->productResourceModel->saveAttribute($product, LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE);
            $cachedProductIdsToClean[] = $product->getId();
            $resetCount++;
        }

        if ($resetCount > 0) {
            $this->replicationHelper->flushFpcCacheAgainstIds($cachedProductIdsToClean);
            $this->logger->info(sprintf(
                'Reset %d previously unavailable products to available and cleared cache',
                $resetCount
            ));
        }
    }
}
