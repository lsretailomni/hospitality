<?php

namespace Ls\Hospitality\Cron;

use Ls\Hospitality\Model\LSR;
use Ls\Replication\Api\ReplHierarchyHospRecipeRepositoryInterface;
use Ls\Replication\Helper\ReplicationHelper;
use Ls\Replication\Logger\Logger;
use Ls\Replication\Model\ResourceModel\ReplHierarchyHospRecipe\CollectionFactory as ReplHierarchyHospRecipeCollectionFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionValuesInterfaceFactory;
use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Class ProcessItemRecipe
 * To Process Item Recipe into the Magento data structure.
 */
class ProcessItemRecipe
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

    /** @var ReplHierarchyHospRecipeCollectionFactory */
    public $replHierarchyHospRecipeCollectionFactory;

    /** @var ReplHierarchyHospRecipeRepositoryInterface */
    public $replHierarchyHospRecipeRepositoryInterface;

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
        ReplHierarchyHospRecipeCollectionFactory $replHierarchyHospRecipeCollectionFactory,
        ReplHierarchyHospRecipeRepositoryInterface $replHierarchyHospRecipeRepositoryInterface,
        ProductRepositoryInterface $productRepository,
        ProductCustomOptionRepositoryInterface $optionRepository,
        ProductCustomOptionValuesInterfaceFactory $customOptionValueFactory,
        ProductCustomOptionInterfaceFactory $customOptionFactory
    ) {
        $this->logger                                     = $logger;
        $this->replicationHelper                          = $replicationHelper;
        $this->lsr                                        = $LSR;
        $this->replHierarchyHospRecipeCollectionFactory   = $replHierarchyHospRecipeCollectionFactory;
        $this->replHierarchyHospRecipeRepositoryInterface = $replHierarchyHospRecipeRepositoryInterface;
        $this->productRepository                          = $productRepository;
        $this->customOptionFactory                        = $customOptionFactory;
        $this->customOptionValueFactory                   = $customOptionValueFactory;
        $this->optionRepository                           = $optionRepository;
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
                        LSR::SC_ITEM_RECIPE_CONFIG_PATH_LAST_EXECUTE,
                        $this->store->getId()
                    );
                    $this->logger->debug('Running ProcessItemRecipe Task for store ' . $this->store->getName());
                    $this->processItemRecipies();
                    $this->replicationHelper->updateCronStatus(
                        $this->cronStatus,
                        LSR::SC_SUCCESS_CRON_ITEM_RECIPE,
                        $this->store->getId()
                    );
                    $this->logger->debug(
                        'End ProcessItemRecipe Task with remaining : ' . $this->getRemainingRecords($this->store)
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
    public function processItemRecipies()
    {
        //TODO cover the delete scenario.
        $batchSize = $this->replicationHelper->getItemRecipeBatchSize();
        $filters   = [
            ['field' => 'main_table.scope_id', 'value' => $this->store->getId(), 'condition_type' => 'eq']
        ];

        $criteria = $this->replicationHelper->buildCriteriaForArrayWithAlias(
            $filters,
            $batchSize,
            1
        );
        /** @var \Ls\Replication\Model\ResourceModel\ReplHierarchyHospRecipe\Collection $collection */
        $collection = $this->replHierarchyHospRecipeCollectionFactory->create();
        $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
            $collection,
            $criteria,
            'RecipeNo',
            null,
            'catalog_product_entity',
            'sku'
        );
        $dataToProcess = [];

        if ($collection->getSize() > 0) {
            /** @var \Ls\Replication\Model\ReplHierarchyHospRecipe $itemRecipe */
            foreach ($collection->getItems() as $itemRecipe) {
                //TODO workaround for UoM
                $dataToProcess[$itemRecipe->getRecipeNo()][] = $itemRecipe;
            }

            if (!empty($dataToProcess)) {
                // loop against each Product.
                foreach ($dataToProcess as $itemSKU => $optionArray) {
                    // generate options.
                    $productOptions = [];
                    if (!empty($optionArray)) {
                        try {
                            $product         = $this->productRepository->get(
                                $itemSKU,
                                true,
                                $this->store->getId()
                            );
                            $existingOptions = $this->optionRepository->getProductOptions($product);

                            // check if Recipe is already included in the options.
                            $isOptionExist         = false;
                            $ls_modifier_recipe_id = LSR::LSR_RECIPE_PREFIX;
                            if (!empty($existingOptions)) {
                                foreach ($existingOptions as $existingOption) {
                                    if ($existingOption->getData('ls_modifier_recipe_id') == $ls_modifier_recipe_id) {
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

                            $optionData = [];
                            /** @var \Ls\Replication\Model\ReplHierarchyHospRecipe $optionValueData */
                            foreach ($optionArray as $optionValueData) {
                                $existingOptionValues = $productOption->getValues();
                                /**
                                 * Dev Notes:
                                 * For the Option Values, we are only checking the duplication based on title.
                                 */
                                $isOptionValueExist = false;
                                if (!empty($existingOptionValues)) {
                                    foreach ($existingOptionValues as $existingOptionValue) {
                                        if ($existingOptionValue->getTitle() == $optionValueData->getDescription()) {
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
                                        ->setSortOrder($optionValueData->getLineNo())
                                        ->setPrice(-$optionValueData->getExclusionPrice());
                                    $optionData['values'][] = $optionValue;
                                    $optionData['title']    = "Exclude Ingredients";
                                }

                                $optionValueData->setProcessed(1)
                                    ->setProcessedAt($this->replicationHelper->getDateTime())
                                    ->setIsUpdated(0);

                                $this->replHierarchyHospRecipeRepositoryInterface->save($optionValueData);
                                //$this->logger->debug(var_export($optionValueData, true));
                            }
                            /**
                             * TODO set type dynamic based on minimum and maximum value
                             * set require based on minimum and maximum value
                             * set title based from first option text.
                             */
                            if ($optionNeedsToBeUpdated) {
                                try {
                                    // check if Option
                                    $productOption->setTitle($optionData['title'])
                                        ->setPrice('')
                                        ->setPriceType('fixed')
                                        ->setValues($optionData['values'])
                                        ->setIsRequire(0)
                                        ->setType('multiple')
                                        ->setData('ls_modifier_recipe_id', $ls_modifier_recipe_id)
                                        ->setProductSku($itemSKU)
                                        ->setSortOrder(99);
                                    $savedProductOption = $this->optionRepository->save($productOption);
                                    $product->addOption($savedProductOption);

                                } catch (\Exception $e) {
                                    $this->logger->error($e->getMessage());
                                    $this->logger->error(
                                        'Error while creating recipes for product ' . $itemSKU . ' for store ' . $this->store->getName()
                                    );
                                }
                            }
                        } catch (\Exception $e) {
                            $this->logger->error($e->getMessage());
                            $this->logger->error(
                                'Error while creating modifiers for  ' . $itemSKU . ' for store ' . $this->store->getName()
                            );
                        }
                    }
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
            $collection = $this->replHierarchyHospRecipeCollectionFactory->create();
            $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
                $collection,
                $criteria,
                'RecipeNo',
                null,
                'catalog_product_entity',
                'sku'
            );
            $this->remainingRecords = $collection->getSize();
        }
        return $this->remainingRecords;
    }
}
