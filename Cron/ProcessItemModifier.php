<?php

namespace Ls\Hospitality\Cron;

use Ls\Core\Model\LSR;
use Ls\Replication\Api\ReplItemModifierRepositoryInterface;
use Ls\Replication\Helper\ReplicationHelper;
use Ls\Replication\Logger\Logger;
use Ls\Replication\Model\ResourceModel\ReplItemModifier\CollectionFactory as ReplItemModifierCollectionFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionValuesInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionValuesInterfaceFactory;
use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Class ProcessItemModifier
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

    public function __construct(
        ReplicationHelper $replicationHelper,
        Logger $logger,
        LSR $LSR,
        ReplItemModifierCollectionFactory $replItemModifierCollectionFactory,
        ReplItemModifierRepositoryInterface $replItemModifierRepositoryInterface,
        ProductRepositoryInterface $productRepository,
        ProductCustomOptionRepositoryInterface $optionRepository,
        ProductCustomOptionValuesInterfaceFactory $customOptionValueFactory,
        ProductCustomOptionInterfaceFactory $customOptionFactory
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
    }

    /**
     * @param null $storeData
     * @throws InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
                if ($this->lsr->isLSR($this->store->getId()
                    && $this->lsr->isHospitalityStore($store->getId()))
                ) {
                    $this->replicationHelper->updateConfigValue(
                        $this->replicationHelper->getDateTime(),
                        LSR::SC_ITEM_MODIFIER_CONFIG_PATH_LAST_EXECUTE,
                        $this->store->getId()
                    );
                    $this->logger->debug('Running ProcessItemModifier Task for store ' . $this->store->getName());
                    $this->processItemModifiers();
                    $this->replicationHelper->updateCronStatus(
                        $this->cronStatus,
                        LSR::SC_SUCCESS_CRON_ITEM_MODIFIER,
                        $this->store->getId()
                    );
                    $this->logger->debug(
                        'End ProcessItemModifier Task with remaining : ' . $this->getRemainingRecords($this->store)
                    );
                }
                $this->lsr->setStoreId(null);
            }
        }
    }

    /**
     * @param null $storeData
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function executeManually($storeData = null)
    {
        $this->execute($storeData);
        $remainingRecords = (int)$this->getRemainingRecords($storeData, true);
        return [$remainingRecords];
    }

    /**
     * @throws InputException
     */
    public function processItemModifiers()
    {
        $batchSize = $this->replicationHelper->getItemModifiersBatchSize();
        $filters   = [
            ['field' => 'main_table.scope_id', 'value' => $this->store->getId(), 'condition_type' => 'eq']
        ];

        $criteria = $this->replicationHelper->buildCriteriaForArrayWithAlias(
            $filters,
            $batchSize,
            1
        );
        /** @var \Ls\Replication\Model\ResourceModel\ReplItemModifier\Collection $collection */
        $collection = $this->replItemModifierCollectionFactory->create();
        $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
            $collection,
            $criteria,
            'nav_id',
            null,
            'catalog_product_entity',
            'sku'
        );
        $dataToProcess = [];

        if ($collection->getSize() > 0) {
            /** @var \Ls\Replication\Model\ReplItemModifier $itemModifier */
            foreach ($collection->getItems() as $itemModifier) {
                $dataToProcess[$itemModifier->getNavId()][$itemModifier->getCode()][$itemModifier->getSubCode()] = $itemModifier;
            }

            if (!empty($dataToProcess)) {
                // loop against each Product.
                foreach ($dataToProcess as $itemSKU => $optionArray) {
                    // generate options.
                    $productOptions = [];
                    $this->logger->debug('For Product ID  for option is ' . $itemSKU . ' and Options are ');
                    if (!empty($optionArray)) {
                        // get Product Repository;
                        /** @var  $product */
                        try {
                            $product = $this->productRepository->get(
                                $itemSKU,
                                true,
                                $this->store->getId()
                            );
                            $product->setHasOptions(1);

                            $product = $this->productRepository->save($product);
                            foreach ($optionArray as $optionCode => $optionValuesArray) {

                                $this->logger->debug('-- Key for option is ' . $optionCode . ' and value is ');
                                if (!empty($optionValuesArray)) {

                                    /** @var ProductCustomOptionInterface $productOption */
                                    $productOption = $this->customOptionFactory->create();

                                    $optionData = [];
                                    /** @var \Ls\Replication\Model\ReplItemModifier $optionValueData */
                                    foreach ($optionValuesArray as $subcode => $optionValueData) {
                                        /** @var ProductCustomOptionValuesInterface $optionValue */
                                        $optionValue = $this->customOptionValueFactory->create();
                                        $optionValue->setTitle($optionValueData->getDescription())
                                            ->setPriceType('fixed')
                                            ->setSortOrder($subcode)
                                            ->setPrice($optionValueData->getAmountPercent());
                                        $optionData['values'][] = $optionValue;
                                        if ($optionValueData->getExplanatoryHeaderText() != '') {
                                            $optionData['title'] = $optionValueData->getExplanatoryHeaderText();
                                        } else {
                                            $optionData['title'] = $optionValueData->getCode();
                                        }
                                        $optionValueData->setProcessed(1)
                                            ->setProcessedAt($this->replicationHelper->getDateTime())
                                            ->setIsUpdated(0);

                                        $this->replItemModifierRepositoryInterface->save($optionValueData);

                                        $this->logger->debug('-- -- Code is  ' . $subcode . ' and value is ');
                                        //$this->logger->debug(var_export($optionValueData, true));
                                    }
                                    /**
                                     * TODO set type dynamic based on minimum and maximum value
                                     * set require based on minimum and maximum value
                                     * set title based from first option text.
                                     */
                                    try {
                                        $productOption->setTitle($optionData['title'])
                                            ->setPrice('')
                                            ->setPriceType('fixed')
                                            ->setValues($optionData['values'])
                                            ->setIsRequire(0)
                                            ->setType('drop_down')
                                            ->setProductSku($itemSKU);
                                        $savedProductOption = $this->optionRepository->save($productOption);
                                        $product->addOption($savedProductOption);

                                    } catch (\Exception $e) {
                                        $this->logger->debug($e->getMessage());
                                        $this->logger->debug(
                                            'Error while creating options for' . $optionCode . ' for product ' . $itemSKU . ' for store ' . $this->store->getName()
                                        );
                                    }

                                }
                            }
                        } catch (\Exception $e) {
                            $this->logger->debug($e->getMessage());
                            $this->logger->debug(
                                'Error while creating modifiers for  ' . $itemSKU . ' for store ' . $this->store->getName()
                            );
                        }
                    }

                    /*                    $this->logger->debug('Key is ' . $itemSKU . ' and value is ');
                                        $this->logger->debug(var_export($optionArray, true));*/
                    //break;
                }
            }
            $remainingItems = (int)$this->getRemainingRecords($this->store);
            if ($remainingItems == 0) {
                $this->cronStatus = true;
            }
        } else {
            $this->cronStatus = true;
        }
    }

    /**
     * @param $storeData
     * @return int
     */
    public function getRemainingRecords(
        $storeData,
        $forceReload = false
    ) {
        if (!$this->remainingRecords || $forceReload) {

            $filters = [
                ['field' => 'main_table.scope_id', 'value' => $this->store->getId(), 'condition_type' => 'eq']
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
                'catalog_product_entity',
                'sku'
            );
            $this->remainingRecords = $collection->getSize();
        }
        return $this->remainingRecords;
    }
}
