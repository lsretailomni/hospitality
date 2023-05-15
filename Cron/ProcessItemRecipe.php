<?php

namespace Ls\Hospitality\Cron;

use Exception;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Api\ReplItemRecipeRepositoryInterface;
use \Ls\Replication\Helper\ReplicationHelper;
use \Ls\Replication\Logger\Logger;
use \Ls\Replication\Model\ReplItemRecipe;
use \Ls\Replication\Model\ResourceModel\ReplItemRecipe\Collection;
use \Ls\Replication\Model\ResourceModel\ReplItemRecipe\CollectionFactory as ReplItemRecipeCollectionFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionValuesInterfaceFactory;
use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

/**
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

    /** @var ReplItemRecipeCollectionFactory */
    public $replItemRecipeCollectionFactory;

    /** @var ReplItemRecipeRepositoryInterface */
    public $replItemRecipeRepositoryInterface;

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
     * ProcessItemRecipe constructor.
     * @param ReplicationHelper $replicationHelper
     * @param Logger $logger
     * @param LSR $LSR
     * @param ReplItemRecipeCollectionFactory $replItemRecipeCollectionFactory
     * @param ReplItemRecipeRepositoryInterface $replItemRecipeRepositoryInterface
     * @param ProductRepositoryInterface $productRepository
     * @param ProductCustomOptionRepositoryInterface $optionRepository
     * @param ProductCustomOptionValuesInterfaceFactory $customOptionValueFactory
     * @param ProductCustomOptionInterfaceFactory $customOptionFactory
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        ReplicationHelper $replicationHelper,
        Logger $logger,
        LSR $LSR,
        ReplItemRecipeCollectionFactory $replItemRecipeCollectionFactory,
        ReplItemRecipeRepositoryInterface $replItemRecipeRepositoryInterface,
        ProductRepositoryInterface $productRepository,
        ProductCustomOptionRepositoryInterface $optionRepository,
        ProductCustomOptionValuesInterfaceFactory $customOptionValueFactory,
        ProductCustomOptionInterfaceFactory $customOptionFactory,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->logger                            = $logger;
        $this->replicationHelper                 = $replicationHelper;
        $this->lsr                               = $LSR;
        $this->replItemRecipeCollectionFactory   = $replItemRecipeCollectionFactory;
        $this->replItemRecipeRepositoryInterface = $replItemRecipeRepositoryInterface;
        $this->productRepository                 = $productRepository;
        $this->customOptionFactory               = $customOptionFactory;
        $this->customOptionValueFactory          = $customOptionValueFactory;
        $this->optionRepository                  = $optionRepository;
        $this->hospitalityHelper                 = $hospitalityHelper;
    }

    /**
     * Entry point for cron
     *
     * @param mixed $storeData
     * @return void
     * @throws LocalizedException
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
                        LSR::SC_ITEM_RECIPE_CONFIG_PATH_LAST_EXECUTE,
                        $this->store->getId(),
                        ScopeInterface::SCOPE_STORES
                    );
                    $this->logger->debug('Running ProcessItemRecipe Task for store ' . $this->store->getName());
                    $this->processItemRecipies();
                    $this->replicationHelper->updateCronStatus(
                        $this->cronStatus,
                        LSR::SC_SUCCESS_CRON_ITEM_RECIPE,
                        $this->store->getId(),
                        false,
                        ScopeInterface::SCOPE_STORES
                    );
                    $this->logger->debug(
                        'End ProcessItemRecipe Task with remaining : ' . $this->getRemainingRecords()
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
     * Item Recipes processing
     *
     * @return void
     * @throws LocalizedException
     */
    public function processItemRecipies()
    {
        //TODO cover the delete scenario.
        $batchSize = $this->hospitalityHelper->getItemRecipeBatchSize();
        $filters   = [
            ['field' => 'main_table.scope_id', 'value' => $this->getScopeId(), 'condition_type' => 'eq']
        ];

        $criteria = $this->replicationHelper->buildCriteriaForArrayWithAlias(
            $filters,
            $batchSize,
            1
        );
        /** @var Collection $collection */
        $collection = $this->replItemRecipeCollectionFactory->create();
        $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
            $collection,
            $criteria,
            'RecipeNo',
            null,
            ['repl_item_recipe_id']
        );
        $dataToProcess = [];

        if ($collection->getSize() > 0) {
            /** @var ReplItemRecipe $itemRecipe */
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
                            $product         = $this->replicationHelper->getProductDataByIdentificationAttributes(
                                $itemSKU,
                                '',
                                '',
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
                            /** @var ReplItemRecipe $optionValueData */
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

                                    if (!empty($optionValueData->getImageId())) {
                                        $swatchPath = $this->hospitalityHelper->getImage(
                                            $optionValueData->getImageId()
                                        );

                                        if (!empty($swatchPath)) {
                                            $optionValue->setSwatch($swatchPath);
                                        }
                                    }
                                    $optionData['values'][] = $optionValue;
                                    $optionData['title']    = "Exclude Ingredients";
                                }

                                $optionValueData->setProcessed(1)
                                    ->setProcessedAt($this->replicationHelper->getDateTime())
                                    ->setIsUpdated(0);

                                $this->replItemRecipeRepositoryInterface->save($optionValueData);
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
                                        ->setSortOrder(99)
                                        ->setSwatch(
                                            $this->hospitalityHelper->getFirstAvailableOptionValueImagePath(
                                                $optionData['values']
                                            )
                                        );
                                    $savedProductOption = $this->optionRepository->save($productOption);
                                    $product->addOption($savedProductOption);
                                    if (!$product->getHasOptions()) {
                                        $product->setHasOptions(1);
                                        $product = $this->productRepository->save($product);
                                    }
                                } catch (Exception $e) {
                                    $this->logger->error($e->getMessage());
                                    $this->logger->error(
                                        'Error while creating recipes for product ' . $itemSKU . ' for store ' . $this->store->getName()
                                    );
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
            $remainingItems = (int)$this->getRemainingRecords();
            if ($remainingItems == 0) {
                $this->cronStatus = true;
            }
        } else {
            $this->cronStatus = true;
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
            $collection = $this->replItemRecipeCollectionFactory->create();
            $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
                $collection,
                $criteria,
                'RecipeNo',
                null,
                ['repl_item_recipe_id']
            );
            $this->remainingRecords = $collection->getSize();
        }
        return $this->remainingRecords;
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
