<?php

namespace Ls\Hospitality\Helper;

use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\SubLineType;
use \Ls\Omni\Client\Ecommerce\Entity\OrderHospLine;
use \Ls\Replication\Api\ReplHierarchyHospDealRepositoryInterface;
use \Ls\Replication\Api\ReplItemUnitOfMeasureRepositoryInterface as ReplItemUnitOfMeasure;
use \Ls\Replication\Helper\ReplicationHelper;
use \Ls\Replication\Model\ReplItemModifierRepository;
use \Ls\Replication\Model\ReplItemRecipeRepository;
use \Ls\Replication\Model\ResourceModel\ReplHierarchyHospDeal\CollectionFactory as DealCollectionFactory;
use \Ls\Replication\Model\ResourceModel\ReplHierarchyHospDealLine\CollectionFactory as DealLineCollectionFactory;
use Magento\Catalog\Helper\Product\Configuration;
use Magento\Catalog\Model\Product\Interceptor;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Zend_Db_Select;
use Zend_Db_Select_Exception;

/**
 * Useful helper functions for Hospitality
 *
 */
class HospitalityHelper extends AbstractHelper
{

    /** @var ProductRepository $productRepository */
    public $productRepository;

    /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
    public $searchCriteriaBuilder;

    /**
     * @var Configuration
     */
    public $configurationHelper;

    /**
     * @var ReplItemModifierRepository
     */
    public $itemModifierRepository;

    /**
     * @var ReplItemRecipeRepository
     */
    public $recipeRepository;

    /** @var ReplItemUnitOfMeasure */
    public $replItemUomRepository;

    /**
     * @var ReplicationHelper
     */
    public $replicationHelper;

    /**
     * @var DealCollectionFactory
     */
    public $replHierarchyHospDealCollectionFactory;

    /**
     * @var DealLineCollectionFactory
     */
    public $replHierarchyHospDealLineCollectionFactory;
    /**
     * @var ResourceConnection
     */
    public $resourceConnection;

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @var ReplHierarchyHospDealRepositoryInterface
     */
    public $replHierarchyHospDealRepository;

    /**
     * @param Context $context
     * @param Configuration $configurationHelper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductRepository $productRepository
     * @param ReplItemModifierRepository $itemModifierRepository
     * @param ReplItemRecipeRepository $recipeRepository
     * @param ReplItemUnitOfMeasure $replItemUnitOfMeasureRepository
     * @param ReplicationHelper $replicationHelper
     * @param DealLineCollectionFactory $replHierarchyHospDealLineCollectionFactory
     * @param DealCollectionFactory $replHierarchyHospDealCollectionFactory
     * @param ReplHierarchyHospDealRepositoryInterface $replHierarchyHospDealRepository
     * @param ResourceConnection $resourceConnection
     * @param LSR $lsr
     */
    public function __construct(
        Context $context,
        Configuration $configurationHelper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepository $productRepository,
        ReplItemModifierRepository $itemModifierRepository,
        ReplItemRecipeRepository $recipeRepository,
        ReplItemUnitOfMeasure $replItemUnitOfMeasureRepository,
        ReplicationHelper $replicationHelper,
        DealLineCollectionFactory $replHierarchyHospDealLineCollectionFactory,
        DealCollectionFactory $replHierarchyHospDealCollectionFactory,
        ReplHierarchyHospDealRepositoryInterface $replHierarchyHospDealRepository,
        ResourceConnection $resourceConnection,
        LSR $lsr
    ) {
        parent::__construct($context);
        $this->configurationHelper                        = $configurationHelper;
        $this->searchCriteriaBuilder                      = $searchCriteriaBuilder;
        $this->productRepository                          = $productRepository;
        $this->itemModifierRepository                     = $itemModifierRepository;
        $this->recipeRepository                           = $recipeRepository;
        $this->replItemUomRepository                      = $replItemUnitOfMeasureRepository;
        $this->replicationHelper                          = $replicationHelper;
        $this->replHierarchyHospDealLineCollectionFactory = $replHierarchyHospDealLineCollectionFactory;
        $this->replHierarchyHospDealCollectionFactory     = $replHierarchyHospDealCollectionFactory;
        $this->replHierarchyHospDealRepository = $replHierarchyHospDealRepository;
        $this->resourceConnection                         = $resourceConnection;
        $this->lsr                                        = $lsr;
    }

    /**
     * @param $quoteItem
     * @return array
     */
    public function getSelectedOrderHospSubLineGivenQuoteItem($quoteItem)
    {
        $sku = $quoteItem->getSku();

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('sku', $sku, 'eq')->create();

        $productList = $this->productRepository->getList($searchCriteria)->getItems();

        /** @var Interceptor $product */
        $product = array_pop($productList);

        $uom = $product->getAttributeText('lsr_uom');
        $itemSku = explode("-", $sku);
        $lsrId = $itemSku[0];

        /**
         * Business Logic ***
         * For configurable based products, we are storing values based on UoM Description
         * However in the Modifiers, we are storing data based on UoM Code.
         * So in order to have a proper filter, we need to check if UoM is not empty then get the Code for specific item
         * based on description.
         */
        $uoMCode = $mainDealLine = null;

        if ($uom) {
            // only try if UoM is not null
            // get UoM code based on Description
            $uoMCode = $this->getUoMCodeByDescription($lsrId, $uom);
        }
        // if found UoM code by description then replace else continue.
        $uom = $uoMCode ? $uoMCode : $uom;
        $selectedOptionsOfQuoteItem = $this->configurationHelper->getCustomOptions($quoteItem);
        $selectedOrderHospSubLine = [];

        if ($product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE)) {
            $mainDealLine = current($this->getMainDealLine($lsrId));

            if ($mainDealLine) {
                $selectedOrderHospSubLine['deal'][] = [
                    'DealLineId' => $mainDealLine->getLineNo(),
                    'LineNumber' => $mainDealLine->getLineNo()
                ];
            }

            foreach ($selectedOptionsOfQuoteItem as $index => $option) {
                if (isset($option['ls_modifier_recipe_id'])) {
                    continue;
                }

                $selectedOrderHospSubLine['deal'][] = [
                    'DealLineId' => $this->getCustomOptionSortOrder($product, $option['option_id']),
                    'DealModLineId' => $this->getCustomOptionValueSortOrder(
                        $product,
                        $option['option_id'],
                        trim($option['value'])
                    )
                ];
                unset($selectedOptionsOfQuoteItem[$index]);
            }
        }

        foreach ($selectedOptionsOfQuoteItem as $option) {
            if (isset($option['ls_modifier_recipe_id'])) {
                $itemSubLineCode = $option['ls_modifier_recipe_id'];
            } else {
                $itemSubLineCode = $option['label'];
            }
            $decodedValue = htmlspecialchars_decode($option['value']);

            foreach (array_map('trim', explode(',', $decodedValue)) as $optionValue) {
                if ($itemSubLineCode == LSR::LSR_RECIPE_PREFIX) {
                    if ($product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE) && $mainDealLine) {
                        $recipeData['DealLineId'] = $mainDealLine->getLineNo();
                        $recipeData['ParentSubLineId'] = $mainDealLine->getLineNo();
                        $recipe = $this->getRecipe($mainDealLine->getNo(), $optionValue);
                    } else {
                        $recipe = $this->getRecipe($lsrId, $optionValue);
                    }

                    if (!empty($recipe)) {
                        $itemId = reset($recipe)->getItemNo();
                        $recipeData['ItemId'] = $itemId;
                        $selectedOrderHospSubLine['recipe'][] = $recipeData;
                    }
                } else {
                    $formattedItemSubLineCode = $this->getItemSubLineCode($itemSubLineCode);
                    $itemModifier = $this->getItemModifier(
                        $lsrId,
                        $formattedItemSubLineCode,
                        $optionValue,
                        $uom
                    );

                    if (!empty($itemModifier)) {
                        $subCode = reset($itemModifier)->getSubCode();
                        $selectedOrderHospSubLine['modifier'][] =
                            ['ModifierGroupCode' => $formattedItemSubLineCode, 'ModifierSubCode' => $subCode];
                    }
                }
            }
        }

        return $selectedOrderHospSubLine;
    }

    /**
     * @param OrderHospLine $line
     * @return float|null
     */
    public function getAmountGivenLine(OrderHospLine $line)
    {
        $amount = $line->getAmount();

        foreach ($line->getSubLines() as $subLine) {
            if ($subLine->getType() == SubLineType::DEAL) {
                continue;
            }

            $amount += $subLine->getAmount();
        }

        return $amount;
    }

    /**
     * @param OrderHospLine $line
     * @return float|null
     */
    public function getPriceGivenLine(OrderHospLine $line)
    {
        $price = $line->getPrice();

        foreach ($line->getSubLines() as $subLine) {
            $price += $subLine->getPrice();
        }

        return $price;
    }

    /**
     * @param OrderHospLine $line
     * @param $item
     * @return bool
     */
    public function isSameAsSelectedLine(OrderHospLine $line, $item)
    {
        $selectedOrderHospSubLine = $this->getSelectedOrderHospSubLineGivenQuoteItem($item);
        $selectedCount = $this->getSelectedSubLinesCount($selectedOrderHospSubLine);

        if ($selectedCount != count($line->getSubLines()->getOrderHospSubLine())) {
            return false;
        }

        foreach ($line->getSubLines() as $omniSubLine) {
            $found = false;

            if ($omniSubLine->getType() == SubLineType::MODIFIER) {
                if ((int)$omniSubLine->getQuantity()) {
                    if (!empty($selectedOrderHospSubLine['modifier'])) {
                        foreach ($selectedOrderHospSubLine['modifier'] as $quoteSubLine) {
                            if ($omniSubLine->getModifierGroupCode() == $quoteSubLine['ModifierGroupCode'] &&
                                $omniSubLine->getModifierSubCode() == $quoteSubLine['ModifierSubCode']) {
                                $found = true;
                                break;
                            }
                        }
                    }
                } else {
                    if (!empty($selectedOrderHospSubLine['recipe'])) {
                        foreach ($selectedOrderHospSubLine['recipe'] as $quoteSubLine) {
                            if ($omniSubLine->getItemId() == $quoteSubLine['ItemId']) {
                                $found = true;
                                break;
                            }
                        }
                    }
                }
            } elseif ($omniSubLine->getType() == SubLineType::DEAL) {
                if (!empty($selectedOrderHospSubLine['deal'])) {
                    foreach ($selectedOrderHospSubLine['deal'] as $quoteSubLine) {
                        if ($omniSubLine->getDealLineId() == $quoteSubLine['DealLineId']) {
                            if ($omniSubLine->getDealModifierLineId()) {
                                if (isset($quoteSubLine['DealModLineId']) &&
                                    $omniSubLine->getDealModifierLineId() == $quoteSubLine['DealModLineId']) {
                                    $found = true;
                                    break;
                                }
                            } else {
                                if (isset($quoteSubLine['LineNumber']) &&
                                    $omniSubLine->getLineNumber() == $quoteSubLine['LineNumber']) {
                                    $found = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $label
     * @return mixed|string
     */
    public function getItemSubLineCode($label)
    {
        $subString = explode(LSR::LSR_ITEM_MODIFIER_PREFIX, $label);

        return end($subString);
    }

    /**
     * @param $navId
     * @param $code
     * @param $value
     * @param $uom
     * @return mixed
     */
    public function getItemModifier($navId, $code, $value, $uom)
    {
        // removing this for now.
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('nav_id', $navId)
            ->addFilter('Description', $value)
            ->addFilter('Code', $code);

        if ($uom) {
            $searchCriteria->addFilter('UnitOfMeasure', $uom);
        }
        $itemModifier = $this->itemModifierRepository->getList(
            $searchCriteria->setPageSize(1)
                ->setCurrentPage(1)
                ->create()
        );

        return $itemModifier->getItems();
    }

    /**
     * @param $navId
     * @param $description
     * @return |null
     */
    public function getUoMCodeByDescription($navId, $description)
    {
        // removing this for now.
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('ItemId', $navId)
            ->addFilter('Description', $description);
        $itemUom        = $this->replItemUomRepository->getList(
            $searchCriteria->setPageSize(1)
                ->setCurrentPage(1)
                ->create()
        );

        if ($itemUom->getTotalCount() > 0) {
            return $itemUom->getItems()[0]->getCode();
        }

        return null;
    }

    /**
     * @param $recipeNo
     * @param $value
     * @return mixed
     */
    public function getRecipe($recipeNo, $value)
    {
        $recipe = $this->recipeRepository->getList(
            $this->searchCriteriaBuilder->addFilter('RecipeNo', $recipeNo)
                ->addFilter('Description', $value)
                ->setPageSize(1)->setCurrentPage(1)
                ->create()
        );

        return $recipe->getItems();
    }

    /**
     * @param $store
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    public function getUpdatedDealLinesRecords($store)
    {
        $batchSize = $this->getItemModifiersBatchSize();
        $filters2  = [
            ['field' => 'main_table.scope_id', 'value' => $store->getId(), 'condition_type' => 'eq']
        ];

        $criteria2   = $this->replicationHelper->buildCriteriaForArrayWithAlias(
            $filters2,
            $batchSize,
            1
        );
        $collection2 = $this->replHierarchyHospDealLineCollectionFactory->create();
        $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
            $collection2,
            $criteria2,
            'DealNo',
            null,
            'catalog_product_entity',
            'sku'
        );
        $collection2->getSelect()->group('main_table.DealNo')
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(['main_table.DealNo']);

        $filters1 = [
            ['field' => 'main_table.scope_id', 'value' => $store->getId(), 'condition_type' => 'eq'],
            ['field' => 'main_table.Type', 'value' => ['Item', 'Modifier'], 'condition_type' => 'in']
        ];

        $criteria1   = $this->replicationHelper->buildCriteriaForArrayWithAlias(
            $filters1,
            $batchSize,
            1
        );
        $collection1 = $this->replHierarchyHospDealCollectionFactory->create();
        $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
            $collection1,
            $criteria1,
            'DealNo',
            null,
            'catalog_product_entity',
            'sku'
        );
        $collection1->getSelect()->group('main_table.DealNo')
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(['main_table.DealNo']);
        $select = $this->resourceConnection->getConnection()->select()->union(
            [$collection1->getSelect(), $collection2->getSelect()]
        );
//        $query       = $select->__toString();

        return $this->resourceConnection->getConnection()->fetchAll($select);
    }

    /**
     * @return string
     */
    public function getItemModifiersBatchSize()
    {
        return $this->lsr->getStoreConfig(LSR::SC_REPLICATION_ITEM_MODIFIER_BATCH_SIZE);
    }

    /**
     * @return string
     */
    public function getItemRecipeBatchSize()
    {
        return $this->lsr->getStoreConfig(LSR::SC_REPLICATION_ITEM_RECIPE_BATCH_SIZE);
    }

    /**
     * @param $dealNo
     * @return array
     */
    public function getMainDealLine($dealNo)
    {
        return $this->replHierarchyHospDealRepository->getList(
            $this->searchCriteriaBuilder->addFilter('DealNo', $dealNo, 'eq')
                ->addFilter('Type', 'Item', 'eq')->create()
        )->getItems();
    }

    /**
     * Get selected custom option sort order
     *
     * @param $product
     * @param $optionId
     * @return int
     */
    public function getCustomOptionSortOrder($product, $optionId)
    {
        $sortOrder = 1;

        foreach ($product->getOptions() as $o) {
            if ($o->getOptionId() != $optionId) {
                continue;
            }
            $sortOrder = $o->getSortOrder();
            break;
        }

        return $sortOrder;
    }

    /**
     * Get selected custom option value sort order
     *
     * @param $product
     * @param $optionId
     * @param $optionValueTitle
     * @return int
     */
    public function getCustomOptionValueSortOrder($product, $optionId, $optionValueTitle)
    {
        $sortOrder = 1;

        foreach ($product->getOptions() as $o) {
            if ($o->getOptionId() != $optionId) {
                continue;
            }

            foreach ($o->getValues() as $value) {
                if ($value->getTitle() == $optionValueTitle) {
                    $sortOrder = $value->getSortOrder();
                    return $sortOrder;
                }
            }
        }

        return $sortOrder;
    }

    /**
     * Returns count is the subline type exists
     *
     * @param $selectedOrderHospSubLine
     * @param $typeOfLine
     * @return int|void
     */
    public function getOrderHosSubLineCount($selectedOrderHospSubLine, $typeOfLine)
    {
        return (isset($selectedOrderHospSubLine[$typeOfLine]) ? count($selectedOrderHospSubLine[$typeOfLine]) : 0);
    }

    /**
     * Returns total count of all the subline types
     *
     * @param $selectedOrderHospSubLine
     * @return int|void
     */
    public function getSelectedSubLinesCount($selectedOrderHospSubLine)
    {
        return $this->getOrderHosSubLineCount($selectedOrderHospSubLine, 'modifier') +
        $this->getOrderHosSubLineCount($selectedOrderHospSubLine, 'recipe') +
        $this->getOrderHosSubLineCount($selectedOrderHospSubLine, 'deal');
    }
}
