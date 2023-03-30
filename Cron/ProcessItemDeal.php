<?php

namespace Ls\Hospitality\Cron;

use \Exception;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Api\ReplHierarchyHospDealLineRepositoryInterface;
use \Ls\Replication\Api\ReplHierarchyHospDealRepositoryInterface;
use \Ls\Replication\Api\ReplHierarchyLeafRepositoryInterface;
use \Ls\Replication\Api\ReplItemModifierRepositoryInterface;
use \Ls\Replication\Api\ReplItemRecipeRepositoryInterface;
use \Ls\Replication\Cron\ReplEcommHierarchyHospDealLineTask;
use \Ls\Replication\Cron\ReplEcommHierarchyHospDealTask;
use \Ls\Replication\Cron\ReplEcommHierarchyLeafTask;
use \Ls\Replication\Cron\ReplEcommImageLinksTask;
use \Ls\Replication\Helper\ReplicationHelper;
use \Ls\Replication\Logger\Logger;
use \Ls\Replication\Model\ReplHierarchyHospDeal;
use \Ls\Replication\Model\ReplHierarchyLeaf;
use Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionValuesInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Store\Api\Data\StoreInterface;
use Zend_Db_Select_Exception;

/**
 * Create Items in magento replicated from omni
 */
class ProcessItemDeal
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

    /**
     * @var ReplHierarchyHospDealRepositoryInterface
     */
    public $replHierarchyHospDealRepository;

    /**
     * @var ReplHierarchyHospDealLineRepositoryInterface
     */
    public $replHierarchyHospDealLineRepository;

    /**
     * @var ReplHierarchyLeafRepositoryInterface
     */
    public $replHierarchyLeafRepository;

    /** @var ProductRepositoryInterface */
    public $productRepository;

    /** @var ProductInterfaceFactory */
    public $productFactory;

    /** @var ProductCustomOptionInterfaceFactory */
    public $customOptionFactory;
    /**
     * @var ProductCustomOptionValuesInterfaceFactory
     */
    public $customOptionValueFactory;
    /**
     * @var ProductCustomOptionRepositoryInterface
     */
    public $optionRepository;

    /** @var ReplItemRecipeRepositoryInterface */
    public $replItemRecipeRepository;

    /** @var ReplItemModifierRepositoryInterface */
    public $replItemModifierRepository;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @var ProcessItemModifier
     */
    public $processItemModifier;

    /**
     * @param ReplicationHelper $replicationHelper
     * @param Logger $logger
     * @param LSR $LSR
     * @param ReplHierarchyHospDealRepositoryInterface $replHierarchyHospDealRepository
     * @param ReplHierarchyHospDealLineRepositoryInterface $replHierarchyHospDealLineRepository
     * @param ReplHierarchyLeafRepositoryInterface $replHierarchyLeafRepository
     * @param ProductRepositoryInterface $productRepository
     * @param ProductInterfaceFactory $productInterfaceFactory
     * @param ProductCustomOptionRepositoryInterface $optionRepository
     * @param ProductCustomOptionValuesInterfaceFactory $customOptionValueFactory
     * @param ProductCustomOptionInterfaceFactory $customOptionFactory
     * @param ReplItemRecipeRepositoryInterface $replItemRecipeRepositoryInterface
     * @param ReplItemModifierRepositoryInterface $replItemModifierRepository
     * @param HospitalityHelper $hospitalityHelper
     * @param ProcessItemModifier $processItemModifier
     */
    public function __construct(
        ReplicationHelper $replicationHelper,
        Logger $logger,
        LSR $LSR,
        ReplHierarchyHospDealRepositoryInterface $replHierarchyHospDealRepository,
        ReplHierarchyHospDealLineRepositoryInterface $replHierarchyHospDealLineRepository,
        ReplHierarchyLeafRepositoryInterface $replHierarchyLeafRepository,
        ProductRepositoryInterface $productRepository,
        ProductInterfaceFactory $productInterfaceFactory,
        ProductCustomOptionRepositoryInterface $optionRepository,
        ProductCustomOptionValuesInterfaceFactory $customOptionValueFactory,
        ProductCustomOptionInterfaceFactory $customOptionFactory,
        ReplItemRecipeRepositoryInterface $replItemRecipeRepositoryInterface,
        ReplItemModifierRepositoryInterface $replItemModifierRepository,
        HospitalityHelper $hospitalityHelper,
        ProcessItemModifier $processItemModifier
    ) {
        $this->logger                              = $logger;
        $this->replicationHelper                   = $replicationHelper;
        $this->lsr                                 = $LSR;
        $this->replHierarchyHospDealRepository     = $replHierarchyHospDealRepository;
        $this->replHierarchyHospDealLineRepository = $replHierarchyHospDealLineRepository;
        $this->replHierarchyLeafRepository         = $replHierarchyLeafRepository;
        $this->productRepository                   = $productRepository;
        $this->productFactory                      = $productInterfaceFactory;
        $this->customOptionFactory                 = $customOptionFactory;
        $this->customOptionValueFactory            = $customOptionValueFactory;
        $this->optionRepository                    = $optionRepository;
        $this->replItemRecipeRepository            = $replItemRecipeRepositoryInterface;
        $this->replItemModifierRepository          = $replItemModifierRepository;
        $this->hospitalityHelper                   = $hospitalityHelper;
        $this->processItemModifier                 = $processItemModifier;
    }

    /**
     * Execute manually
     *
     * @param mixed $storeData
     * @return int[]
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws StateException
     * @throws Zend_Db_Select_Exception
     */
    public function executeManually($storeData = null)
    {
        $this->execute($storeData);
        $remainingRecords = (int)$this->getRemainingRecords(true);

        return [$remainingRecords];
    }

    /**
     * Entry point for cron
     *
     * @param mixed $storeData
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws StateException
     * @throws Zend_Db_Select_Exception
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

                if ($this->lsr->isLSR($this->store->getId())
                    && $this->lsr->isHospitalityStore($store->getId())
                ) {
                    $this->replicationHelper->updateConfigValue(
                        $this->replicationHelper->getDateTime(),
                        LSR::SC_ITEM_DEAL_CONFIG_PATH_LAST_EXECUTE,
                        $this->store->getId()
                    );

                    $fullReplicationImageLinkStatus = $this->lsr->getStoreConfig(
                        ReplEcommImageLinksTask::CONFIG_PATH_STATUS,
                        $store->getId()
                    );
                    $fullReplicationDealStatus      = $this->lsr->getStoreConfig(
                        ReplEcommHierarchyHospDealTask::CONFIG_PATH_STATUS,
                        $store->getId()
                    );
                    $fullReplicationDealLineStatus  = $this->lsr->getStoreConfig(
                        ReplEcommHierarchyHospDealLineTask::CONFIG_PATH_STATUS,
                        $store->getId()
                    );
                    $fullReplicationLeafStatus      = $this->lsr->getStoreConfig(
                        ReplEcommHierarchyLeafTask::CONFIG_PATH_STATUS,
                        $store->getId()
                    );
                    $cronCategoryCheck              = $this->lsr->getStoreConfig(
                        LSR::SC_SUCCESS_CRON_CATEGORY,
                        $store->getId()
                    );

                    if ($cronCategoryCheck == 1 &&
                        $fullReplicationImageLinkStatus == 1 &&
                        $fullReplicationDealStatus == 1 &&
                        $fullReplicationDealLineStatus == 1 &&
                        $fullReplicationLeafStatus == 1) {
                        $this->logger->debug('Running ProcessItemDeal Task for store ' . $this->store->getName());
                        $this->processItemDeals();
                        $this->caterDealLinesAddOrUpdate();
                    }
                    $remainingItems = (int)$this->getRemainingRecords();

                    if ($remainingItems == 0) {
                        $this->cronStatus = true;
                    }
                    $this->replicationHelper->updateCronStatus(
                        $this->cronStatus,
                        LSR::SC_SUCCESS_CRON_ITEM_DEAL,
                        $this->store->getId()
                    );
                    $this->logger->debug(
                        'End ProcessItemDeal Task with remaining : ' . $this->getRemainingRecords()
                    );
                }
                $this->lsr->setStoreId(null);
            }
        }
    }

    /**
     * Get Remaining Records
     *
     * @param bool $forceReload
     * @return int
     */
    public function getRemainingRecords(
        $forceReload = false
    ) {
        if ($this->remainingRecords === null || $forceReload) {
            $records                = $this->getDealsToProcess();
            $this->remainingRecords = $records->getTotalCount();
        }

        return $this->remainingRecords;
    }

    /**
     * Cater deal lines add or update
     *
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws Zend_Db_Select_Exception
     */
    public function caterDealLinesAddOrUpdate()
    {
        $fetchResult = $this->hospitalityHelper->getUpdatedDealLinesRecords($this->store);

        if (count($fetchResult) > 0) {
            foreach ($fetchResult as $dealLine) {
                $product = $this->replicationHelper->getProductDataByIdentificationAttributes(
                    $dealLine['DealNo'],
                    '',
                    '',
                    $this->store->getId()
                );

                if ($product->getOptions()) {
                    foreach ($product->getOptions() as $option) {
                        $this->optionRepository->delete($option);
                    }
                    $product = $this->replicationHelper->getProductDataByIdentificationAttributes(
                        $dealLine['DealNo'],
                        '',
                        '',
                        $this->store->getId()
                    );
                }
                $this->processItemDealLine(
                    $product
                );
            }
        }
    }

    /**
     * Process item deals
     *
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws StateException
     * @throws LocalizedException
     */
    public function processItemDeals()
    {
        $productBatchSize    = $this->lsr->getStoreConfig(
            \Ls\Core\Model\LSR::SC_REPLICATION_PRODUCT_BATCHSIZE,
            $this->store->getId()
        );
        $replHierarchyLeaves = $this->getDealsToProcess($productBatchSize);

        /* @var  ReplHierarchyLeaf $item */
        foreach ($replHierarchyLeaves->getItems() as $item) {
            $storeId = $this->lsr->getStoreConfig(
                \Ls\Core\Model\LSR::SC_SERVICE_STORE,
                $this->store->getId()
            );
            $lineNo  = $this->hospitalityHelper->getMealMainItemSku($item->getNavId());
            try {
                $productData     = $this->replicationHelper->getProductDataByIdentificationAttributes(
                    $item->getNavId(),
                    '',
                    '',
                    $this->store->getId()
                );
                $websitesProduct = $productData->getWebsiteIds();

                /** Check if item exist in the website and assign it if it doesn't exist*/
                if (!in_array($this->store->getWebsiteId(), $websitesProduct, true)) {
                    $websitesProduct[] = $this->store->getWebsiteId();
                    $productData->setWebsiteIds($websitesProduct);
                }

                $productData->setName($item->getDescription());
                $productData->setMetaTitle($item->getDescription());
                $productData->setPrice($item->getDealPrice());

                if ($lineNo) {
                    $itemStock = $this->replicationHelper->getInventoryStatus(
                        $lineNo,
                        $storeId,
                        $this->store->getId()
                    );
                    if ($itemStock) {
                        $productData->setStockData([
                            'use_config_manage_stock' => 1,
                            'is_in_stock'             => ($itemStock->getQuantity() > 0) ? 1 : 0,
                            'qty'                     => $itemStock->getQuantity()
                        ]);
                    }
                }

                try {
                    // @codingStandardsIgnoreLine
                    $this->logger->debug('Trying to save product ' . $item->getNavId() . ' in store ' . $this->store->getName());
                    /** @var ProductRepositoryInterface $productSaved */
                    $productSaved = $this->productRepository->save($productData);
                    $this->replicationHelper->getProductAttributes(
                        $item->getNavId(),
                        $this->store->getId(),
                        $this->productRepository
                    );
                    $this->replicationHelper->assignProductToCategories($productSaved, $this->store);
                    // @codingStandardsIgnoreLine
                } catch (Exception $e) {
                    $this->logger->debug($e->getMessage());
                    $this->logger->debug('Problem with sku: ' . $item->getNavId() . ' in ' . __METHOD__);
                    $item->setData('is_failed', 1);
                }
            } catch (NoSuchEntityException $e) {
                /** @var Product $product */
                $product = $this->productFactory->create();
                $product->setStoreId($this->store->getId());
                $product->setWebsiteIds([$this->store->getWebsiteId()]);
                $product->setName($item->getDescription());
                $product->setMetaTitle($item->getDescription());
                $product->setSku($item->getNavId());
                $product->setUrlKey($this->replicationHelper->oSlug($item->getDescription() . '-' . $item->getNavId()));
                $product->setVisibility(Visibility::VISIBILITY_BOTH);
                $product->setWeight(1);
                $product->setPrice($item->getDealPrice());
                $product->setData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE, 1);
                $product->setCustomAttribute(\Ls\Core\Model\LSR::LS_ITEM_ID_ATTRIBUTE_CODE, $item->getNavId());

                $attributeSetId = $this->replicationHelper->getAttributeSetId(
                    null,
                    'ls_replication_repl_hierarchy_leaf',
                    $this->store->getId(),
                    LSR::SC_REPLICATION_ATTRIBUTE_SET_EXTRAS . '_' .
                    $this->store->getId()
                );
                $product->setAttributeSetId($attributeSetId);
                $product->setTypeId(Type::TYPE_SIMPLE);

                if ($lineNo) {
                    $itemStock = $this->replicationHelper->getInventoryStatus(
                        $lineNo,
                        $storeId,
                        $this->store->getId()
                    );
                    if ($itemStock) {
                        $product->setStockData([
                            'use_config_manage_stock' => 1,
                            'is_in_stock'             => ($itemStock->getQuantity() > 0) ? 1 : 0,
                            'qty'                     => $itemStock->getQuantity()
                        ]);
                    }
                }
                try {
                    // @codingStandardsIgnoreLine
                    $this->logger->debug('Trying to save product ' . $item->getNavId() . ' in store ' . $this->store->getName());
                    /** @var ProductRepositoryInterface $productSaved */
                    $productSaved = $this->productRepository->save($product);
                    $this->replicationHelper->getProductAttributes(
                        $item->getNavId(),
                        $this->store->getId(),
                        $this->productRepository
                    );
                    $this->replicationHelper->assignProductToCategories($productSaved, $this->store);
                    // @codingStandardsIgnoreLine
                } catch (Exception $e) {
                    $this->logger->debug($e->getMessage());
                    $this->logger->debug('Problem with sku: ' . $item->getNavId() . ' in ' . __METHOD__);
                    $item->setData('is_failed', 1);
                }
            }
            $item->setData('processed_at', $this->replicationHelper->getDateTime());
            $item->setData('processed', 1);
            $item->setData('is_updated', 0);
            $this->replHierarchyLeafRepository->save($item);
        }
    }

    /**
     * Process item deal line
     *
     * @param mixed $product
     * @throws InputException|NoSuchEntityException
     */
    public function processItemDealLine($product)
    {
        $filters  = [
            ['field' => 'scope_id', 'value' => $this->store->getId(), 'condition_type' => 'eq'],
            [
                'field' => 'DealNo',
                'value' => $product->getData(LSR::LS_ITEM_ID_ATTRIBUTE_CODE), 'condition_type' => 'eq'
            ]
        ];
        $criteria = $this->replicationHelper->buildCriteriaForDirect($filters, -1);
        $criteria->setSortOrders(
            [$this->replicationHelper->getSortOrderObject('LineNo')]
        );
        $replHierarchyHospDeals = $this->replHierarchyHospDealRepository->getList($criteria);

        /* @var  ReplHierarchyHospDeal $replHierarchyHospDeal */
        foreach ($replHierarchyHospDeals->getItems() as $replHierarchyHospDeal) {
            if ($replHierarchyHospDeal->getType() == 'Modifier') {
                $filters  = [
                    ['field' => 'scope_id', 'value' => $this->store->getId(), 'condition_type' => 'eq'],
                    [
                        'field'          => 'DealNo',
                        'value'          => $product->getData(LSR::LS_ITEM_ID_ATTRIBUTE_CODE),
                        'condition_type' => 'eq'
                    ],
                    ['field' => 'DealLineCode', 'value' => $replHierarchyHospDeal->getNo(), 'condition_type' => 'eq']
                ];
                $criteria = $this->replicationHelper->buildCriteriaForDirect($filters, -1);
                $criteria->setSortOrders(
                    [$this->replicationHelper->getSortOrderObject('LineNo')]
                );
                $replHierarchyHospDealLines = $this->replHierarchyHospDealLineRepository->getList($criteria);

                if ($replHierarchyHospDealLines->getTotalCount() > 0) {
                    $this->createCustomOptionsForModifiers(
                        $replHierarchyHospDeal,
                        $replHierarchyHospDealLines,
                        $product
                    );
                }
            } elseif ($replHierarchyHospDeal->getType() == 'Item') {
                $filters  = [
                    ['field' => 'scope_id', 'value' => $this->store->getId(), 'condition_type' => 'eq'],
                    ['field' => 'nav_id', 'value' => $replHierarchyHospDeal->getNo(), 'condition_type' => 'eq']
                ];
                $criteria = $this->replicationHelper->buildCriteriaForDirect($filters, -1);
                $criteria->setSortOrders(
                    [$this->replicationHelper->getSortOrderObject('SubCode')]
                );
                $repItemModifiers  = $this->replItemModifierRepository->getList($criteria);
                $sku               = $product->getData(LSR::LS_ITEM_ID_ATTRIBUTE_CODE);
                $itemModifiersData = null;
                if ($repItemModifiers->getTotalCount() > 0) {
                    $itemModifiersData = $this->createCustomOptionsForItemModifiers(
                        $repItemModifiers,
                        $sku
                    );
                    $this->processItemModifier->setStore($this->store);
                    $this->processItemModifier->process($itemModifiersData, false);
                    $product = $this->replicationHelper->getProductDataByIdentificationAttributes(
                        $product->getData(LSR::LS_ITEM_ID_ATTRIBUTE_CODE),
                        '',
                        '',
                        $this->store->getId()
                    );
                }

                $filters  = null;
                $filters  = [
                    ['field' => 'scope_id', 'value' => $this->store->getId(), 'condition_type' => 'eq'],
                    ['field' => 'RecipeNo', 'value' => $replHierarchyHospDeal->getNo(), 'condition_type' => 'eq']
                ];
                $criteria = $this->replicationHelper->buildCriteriaForDirect($filters, -1);
                $criteria->setSortOrders(
                    [$this->replicationHelper->getSortOrderObject('LineNo')]
                );
                $repItemRecipes = $this->replItemRecipeRepository->getList($criteria);
                if ($repItemRecipes->getTotalCount() > 0) {
                    $this->createCustomOptionsForRecipe($replHierarchyHospDeal, $repItemRecipes, $product);
                }
            } else {
                $replHierarchyHospDeal->setIsFailed(1);
            }
            $replHierarchyHospDeal->setProcessed(1)
                ->setProcessedAt($this->replicationHelper->getDateTime())
                ->setIsUpdated(0);
            $this->replHierarchyHospDealRepository->save($replHierarchyHospDeal);
        }
    }

    /**
     * Create custom options for modifiers
     *
     * @param mixed $replHierarchyHospDeal
     * @param mixed $replHierarchyHospDealLines
     * @param mixed $product
     * @return void
     * @throws NoSuchEntityException
     */
    public function createCustomOptionsForModifiers(&$replHierarchyHospDeal, $replHierarchyHospDealLines, $product)
    {
        $optionValues = $this->getCustomOptionsValues($replHierarchyHospDealLines, 'modifier');
        try {
            $this->createCustomOptionAgainstGivenData(
                $replHierarchyHospDeal->getDescription(),
                'drop_down',
                $replHierarchyHospDeal->getMinSelection() > 0 ? 1 : 0,
                $replHierarchyHospDeal->getLineNo(),
                $replHierarchyHospDeal->getDealNo(),
                $optionValues,
                $product
            );
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $this->logger->debug('Problem with sku: ' . $product->getSku() . ' in ' . __METHOD__);
            $replHierarchyHospDeal->setData('is_failed', 1);
        }
    }

    /**
     * Process item modifiers
     *
     * @param $replItemModifiers
     * @param $sku
     * @return array
     */
    public function createCustomOptionsForItemModifiers($replItemModifiers, $sku)
    {
        return $this->processItemModifier->formatModifier($replItemModifiers, $sku, false);
    }

    /**
     * Create custom options for recipes
     *
     * @param mixed $replHierarchyHospDeal
     * @param mixed $repItemRecipes
     * @param mixed $product
     * @return void
     * @throws NoSuchEntityException
     */
    public function createCustomOptionsForRecipe(&$replHierarchyHospDeal, $repItemRecipes, $product)
    {
        $optionValues = $this->getCustomOptionsValues($repItemRecipes, 'recipe');
        try {
            $this->createCustomOptionAgainstGivenData(
                'Exclude Ingredients',
                'multiple',
                0,
                $replHierarchyHospDeal->getLineNo(),
                $replHierarchyHospDeal->getDealNo(),
                $optionValues,
                $product,
                LSR::LSR_RECIPE_PREFIX
            );
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $this->logger->debug('Problem with sku: ' . $product->getSku() . ' in ' . __METHOD__);
            $replHierarchyHospDeal->setData('is_failed', 1);
        }
    }

    /**
     * Create custom option against given data
     *
     * @param mixed $description
     * @param mixed $type
     * @param mixed $required
     * @param mixed $sortOrder
     * @param mixed $sku
     * @param mixed $values
     * @param mixed $product
     * @param mixed $lsModifierRecipeId
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    public function createCustomOptionAgainstGivenData(
        $description,
        $type,
        $required,
        $sortOrder,
        $sku,
        $values,
        $product,
        $lsModifierRecipeId = null
    ) {
        $productOption = $this->customOptionFactory->create();
        $productOption->setTitle($description)
            ->setValues($values)
            ->setType($type)
            ->setIsRequire($required)
            ->setSortOrder($sortOrder)
            ->setProductSku($sku)
            ->setData('ls_modifier_recipe_id', $lsModifierRecipeId)
            ->setSwatch(
                $this->hospitalityHelper->getFirstAvailableOptionValueImagePath(
                    $values
                )
            );
        $savedProductOption = $this->optionRepository->save($productOption);
        $product->addOption($savedProductOption);

        if (!$product->getHasOptions()) {
            $product->setHasOptions(1);
            $this->productRepository->save($product);
        }
    }

    /**
     * Get custom options values
     *
     * @param mixed $dealLines
     * @param mixed $type
     * @return array
     * @throws NoSuchEntityException
     */
    public function getCustomOptionsValues($dealLines, $type)
    {
        $optionValues = [];

        foreach ($dealLines->getItems() as $i => $dealLine) {
            $optionValue = $this->customOptionValueFactory->create();
            $optionValue->setTitle($dealLine->getDescription())
                ->setPriceType('fixed')
                ->setSortOrder($dealLine->getLineNo());

            if (!empty($dealLine->getImageId())) {
                $swatchPath = $this->hospitalityHelper->getImage($dealLine->getImageId());

                if (!empty($swatchPath)) {
                    $optionValue->setSwatch($swatchPath);
                }
            }
            if ($type == 'modifier') {
                $optionValue->setPrice($dealLine->getAddedAmount());
                $dealLine->setProcessed(1)
                    ->setProcessedAt($this->replicationHelper->getDateTime())
                    ->setIsUpdated(0);
                $this->replHierarchyHospDealLineRepository->save($dealLine);
            } else {
                $optionValue->setPrice(-$dealLine->getExclusionPrice());
                $this->replItemRecipeRepository->save($dealLine);
            }
            $optionValues[] = $optionValue;
        }

        return $optionValues;
    }

    /**
     * Get deals to process
     *
     * @param mixed $productBatchSize
     * @return mixed
     */
    public function getDealsToProcess(
        $productBatchSize = -1
    ) {
        $filters  = [
            ['field' => 'HierarchyCode', 'value' => true, 'condition_type' => 'notnull'],
            ['field' => 'nav_id', 'value' => true, 'condition_type' => 'notnull'],
            ['field' => 'Description', 'value' => true, 'condition_type' => 'notnull'],
            ['field' => 'scope_id', 'value' => $this->store->getId(), 'condition_type' => 'eq'],
            ['field' => 'Type', 'value' => 'Deal', 'condition_type' => 'eq']
        ];
        $criteria = $this->replicationHelper->buildCriteriaForArray($filters, $productBatchSize);

        return $this->replHierarchyLeafRepository->getList($criteria);
    }
}
