<?php

namespace Ls\Hospitality\Helper;

use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\KOTStatus;
use \Ls\Omni\Client\Ecommerce\Entity\HospOrderStatusResponse as HospOrderStatusResponse;
use \Ls\Omni\Client\Ecommerce\Entity\ImageSize;
use \Ls\Omni\Client\Ecommerce\Operation;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\SubLineType;
use \Ls\Omni\Client\Ecommerce\Entity\OrderHospLine;
use \Ls\Omni\Client\ResponseInterface;
use Ls\Omni\Helper\CacheHelper;
use \Ls\Omni\Helper\ItemHelper;
use \Ls\Omni\Helper\LoyaltyHelper;
use \Ls\Omni\Helper\OrderHelper;
use \Ls\Replication\Api\ReplHierarchyHospDealLineRepositoryInterface;
use \Ls\Replication\Api\ReplHierarchyHospDealRepositoryInterface;
use \Ls\Replication\Api\ReplImageLinkRepositoryInterface;
use \Ls\Replication\Api\ReplItemUnitOfMeasureRepositoryInterface as ReplItemUnitOfMeasure;
use \Ls\Replication\Helper\ReplicationHelper;
use \Ls\Replication\Model\ReplImageLinkSearchResults;
use \Ls\Replication\Model\ReplItemModifierRepository;
use \Ls\Replication\Model\ReplItemRecipeRepository;
use \Ls\Replication\Model\ResourceModel\ReplHierarchyHospDeal\CollectionFactory as DealCollectionFactory;
use \Ls\Replication\Model\ResourceModel\ReplHierarchyHospDealLine\CollectionFactory as DealLineCollectionFactory;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Catalog\Helper\Product\Configuration;
use Magento\Catalog\Model\Product\Interceptor;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductRepository;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\Information;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Url;
use Magento\Sales\Model\ResourceModel\Order;
use Zend_Db_Select_Exception;

/**
 * Useful helper functions for Hospitality
 *
 */
class HospitalityHelper extends AbstractHelper
{
    public const DESTINATION_FOLDER = 'ls/swatch';

    public const ADDRESS_ATTRIBUTE_MAPPER
        = [
            'firstname' => 'name',
            'lastname'  => 'name',
            'telephone' => 'phone',
            'street'    => ['street_line1', 'street_line2']
        ];

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
     * @var Filesystem
     */
    public $filesystem;

    /**
     * @var UploaderFactory
     */
    public $uploaderFactory;

    /** @var LoyaltyHelper */
    public $loyaltyHelper;

    /** @var File */
    public $file;

    /** @var ReplImageLinkRepositoryInterface */
    public $replImageLinkRepositoryInterface;

    /** @var ProductCustomOptionRepositoryInterface */
    public $optionRepository;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var Registry
     */
    public $registry;

    /**
     * @var ReplHierarchyHospDealLineRepositoryInterface
     */
    public $replHierarchyHospDealLineRepository;

    /**
     * @var Information
     */
    public $storeInfo;

    /** @var AddressInterfaceFactory */
    public $addressFactory;

    /**
     * @var AttributeRepositoryInterface
     */
    public $attributeRepository;

    /**
     * @var SerializerJson
     */
    public $serializerJson;

    /**
     * @var OrderHelper
     */
    public $orderHelper;

    /**
     * @var ItemHelper
     */
    public $itemHelper;

    /**
     * @var OrderRepositoryInterface
     */
    public $orderRepository;

    /**
     * @var QrCodeHelper
     */
    public $qrCodeHelper;

    /**
     * @var CustomerSession
     */
    public $customerSession;

    /**
     * @var ImageHelper
     */
    public $imageHelper;

    /**
     * @var Url
     */
    public $productUrlBuilder;

    /**
     * @var Order
     */
    private $orderResourceModel;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var CacheHelper
     */
    public $cacheHelper;

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
     * @param Filesystem $filesystem
     * @param UploaderFactory $uploaderFactory
     * @param LoyaltyHelper $loyaltyHelper
     * @param File $file
     * @param ReplImageLinkRepositoryInterface $replImageLinkRepository
     * @param ProductCustomOptionRepositoryInterface $optionRepository
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param ReplHierarchyHospDealLineRepositoryInterface $replHierarchyHospDealLineRepository
     * @param Information $storeInfo
     * @param AddressInterfaceFactory $addressFactory
     * @param AttributeRepositoryInterface $attributeRepository
     * @param SerializerJson $serializerJson
     * @param OrderHelper $orderHelper
     * @param ItemHelper $itemHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param QrCodeHelper $qrCodeHelper
     * @param CustomerSession $customerSession
     * @param ImageHelper $imageHelper
     * @param Url $productUrlBuilder
     * @param Order $orderResourceModel
     * @param OrderSender $orderSender
     * @param CacheHelper $cacheHelper
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
        LSR $lsr,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory,
        LoyaltyHelper $loyaltyHelper,
        File $file,
        ReplImageLinkRepositoryInterface $replImageLinkRepository,
        ProductCustomOptionRepositoryInterface $optionRepository,
        StoreManagerInterface $storeManager,
        Registry $registry,
        ReplHierarchyHospDealLineRepositoryInterface $replHierarchyHospDealLineRepository,
        Information $storeInfo,
        AddressInterfaceFactory $addressFactory,
        AttributeRepositoryInterface $attributeRepository,
        SerializerJson $serializerJson,
        OrderHelper $orderHelper,
        ItemHelper $itemHelper,
        OrderRepositoryInterface $orderRepository,
        QrCodeHelper $qrCodeHelper,
        CustomerSession $customerSession,
        ImageHelper $imageHelper,
        Url $productUrlBuilder,
        Order $orderResourceModel,
        OrderSender $orderSender,
        CacheHelper $cacheHelper
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
        $this->replHierarchyHospDealRepository            = $replHierarchyHospDealRepository;
        $this->resourceConnection                         = $resourceConnection;
        $this->lsr                                        = $lsr;
        $this->filesystem                                 = $filesystem;
        $this->uploaderFactory                            = $uploaderFactory;
        $this->loyaltyHelper                              = $loyaltyHelper;
        $this->file                                       = $file;
        $this->replImageLinkRepositoryInterface           = $replImageLinkRepository;
        $this->optionRepository                           = $optionRepository;
        $this->storeManager                               = $storeManager;
        $this->registry                                   = $registry;
        $this->replHierarchyHospDealLineRepository        = $replHierarchyHospDealLineRepository;
        $this->storeInfo                                  = $storeInfo;
        $this->addressFactory                             = $addressFactory;
        $this->attributeRepository                        = $attributeRepository;
        $this->serializerJson                             = $serializerJson;
        $this->orderHelper                                = $orderHelper;
        $this->itemHelper                                 = $itemHelper;
        $this->orderRepository                            = $orderRepository;
        $this->qrCodeHelper                               = $qrCodeHelper;
        $this->customerSession                            = $customerSession;
        $this->imageHelper                                = $imageHelper;
        $this->productUrlBuilder                          = $productUrlBuilder;
        $this->orderResourceModel                         = $orderResourceModel;
        $this->orderSender                                = $orderSender;
        $this->cacheHelper                                = $cacheHelper;
    }

    /**
     * Creating selected sublines from quoteItem
     *
     * @param $quoteItem
     * @param $lineNumber
     * @return array
     * @throws NoSuchEntityException
     */
    public function getSelectedOrderHospSubLineGivenQuoteItem($quoteItem, $lineNumber)
    {
        $lineNumber *= 10000;

        /** @var Interceptor $product */
        $product = $quoteItem->getProduct();
        list($lsrId, , $uom) = $this->itemHelper->getItemAttributesGivenQuoteItem($quoteItem);

        /**
         * Business Logic ***
         * For configurable based products, we are storing values based on UoM Description
         * However in the Modifiers, we are storing data based on UoM Code.
         * So in order to have a proper filter, we need to check if UoM is not empty then get the Code for specific item
         * based on description.
         */
        $mainDealLine = null;

        $selectedOptionsOfQuoteItem = $this->configurationHelper->getCustomOptions($quoteItem);
        $selectedOrderHospSubLine   = [];

        if ($product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE)) {
            $mainDealLine = current($this->getMainDealLine($lsrId));

            if ($mainDealLine) {
                $selectedOrderHospSubLine['deal'][] = [
                    'DealLineId' => $mainDealLine->getLineNo(),
                    'LineNumber' => $lineNumber
                ];
            }

            foreach ($selectedOptionsOfQuoteItem as $index => $option) {
                if (isset($option['ls_modifier_recipe_id'])) {
                    continue;
                }
                $dealLineId                         = $this->getCustomOptionSortOrder($product, $option['option_id']);
                $dealModLineId                      = $this->getCustomOptionValueSortOrder(
                    $product,
                    $option['option_id'],
                    trim($option['value'])
                );
                $uom                                = $this->getDealLineUomGivenData(
                    $product->getData(LSR::LS_ITEM_ID_ATTRIBUTE_CODE),
                    $dealLineId,
                    $dealModLineId
                );
                $selectedOrderHospSubLine['deal'][] = [
                    'DealLineId'    => $dealLineId,
                    'DealModLineId' => $dealModLineId,
                    'uom'           => $uom
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
            $decodedValue = htmlspecialchars_decode($option['value'], ENT_QUOTES);

            foreach (array_map('trim', explode(',', $decodedValue)) as $optionValue) {
                if ($itemSubLineCode == LSR::LSR_RECIPE_PREFIX) {
                    if ($product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE) && $mainDealLine) {
                        $recipeData['DealLineId']      = $mainDealLine->getLineNo();
                        $recipeData['ParentSubLineId'] = $lineNumber;
                        $recipeData['price']           = $option['price'] ?? null;
                        $recipe                        = $this->getRecipe($mainDealLine->getNo(), $optionValue);
                    } else {
                        $recipe = $this->getRecipe($lsrId, $optionValue);
                    }

                    if (!empty($recipe)) {
                        $itemId                               = reset($recipe)->getItemNo();
                        $recipeData['ItemId']                 = $itemId;
                        $selectedOrderHospSubLine['recipe'][] = $recipeData;
                    }
                } else {
                    $mainDealLineNo = null;
                    if ($product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE)) {
                        $uom            = null;
                        $lsrId          = $mainDealLine->getNo();
                        $mainDealLineNo = $mainDealLine->getLineNo();
                    }
                    $formattedItemSubLineCode = $this->getItemSubLineCode($itemSubLineCode);
                    $itemModifier             = $this->getItemModifier(
                        $lsrId,
                        $formattedItemSubLineCode,
                        $optionValue
                    );

                    if (!empty($itemModifier)) {
                        $subCode = reset($itemModifier)->getSubCode();
                        $selectedOrderHospSubLine['modifier'][]
                                 = [
                            'ModifierGroupCode' => $formattedItemSubLineCode,
                            'ModifierSubCode'   => $subCode,
                            'DealLineId'        => $mainDealLineNo,
                            'ParentSubLineId'   => ($product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE)) ?
                                $lineNumber : '',
                            'price'             => $option['price'] ?? null,
                        ];
                    }
                }
            }
        }

        return $selectedOrderHospSubLine;
    }

    /**
     * Get amount from given line
     *
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
     * Get price from given line
     *
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
     * Comparison between quote selected sublines and omni sublines
     *
     * @param OrderHospLine $line
     * @param $item
     * @param $index
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isSameAsSelectedLine(OrderHospLine $line, $item, $index)
    {
        $selectedOrderHospSubLine = $this->getSelectedOrderHospSubLineGivenQuoteItem($item, $index);
        $selectedCount            = $this->getSelectedSubLinesCount($selectedOrderHospSubLine);

        if ($selectedCount != count($line->getSubLines()->getOrderHospSubLine())) {
            return false;
        }

        foreach ($line->getSubLines() as $omniSubLine) {
            $found = false;

            if ($omniSubLine->getType() == SubLineType::MODIFIER) {
                if ((int)$omniSubLine->getQuantity()) {
                    if (!empty($selectedOrderHospSubLine['modifier'])) {
                        foreach ($selectedOrderHospSubLine['modifier'] as $quoteSubLine) {
                            if ($omniSubLine->getModifierGroupCode() == $quoteSubLine['ModifierGroupCode']
                                && $omniSubLine->getModifierSubCode() == $quoteSubLine['ModifierSubCode']) {
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
                                if (isset($quoteSubLine['DealModLineId'])
                                    && $omniSubLine->getDealModifierLineId() == $quoteSubLine['DealModLineId']) {
                                    $found = true;
                                    break;
                                }
                            } else {
                                $found = true;
                                break;
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
     * Get item sublines
     *
     * @param $label
     * @return mixed|string
     */
    public function getItemSubLineCode($label)
    {
        $subString = explode(LSR::LSR_ITEM_MODIFIER_PREFIX, $label);

        return end($subString);
    }

    /**
     * Get item modifier
     *
     * @param $navId
     * @param $code
     * @param $value
     * @param $uom
     * @return mixed
     */
    public function getItemModifier($navId, $code, $value)
    {
        // removing this for now.
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('nav_id', $navId)
            ->addFilter('Description', $value)
            ->addFilter('Code', $code);

        $itemModifier = $this->itemModifierRepository->getList(
            $searchCriteria->setPageSize(1)
                ->setCurrentPage(1)
                ->create()
        );

        return $itemModifier->getItems();
    }

    /**
     * Get unit of measure by description
     *
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
     * Get recipe
     *
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
     * Get recipe by line number
     *
     * @param $recipeNo
     * @param $LineNo
     * @return mixed
     */
    public function getRecipeByLineNumber($recipeNo, $LineNo)
    {
        $recipe = $this->recipeRepository->getList(
            $this->searchCriteriaBuilder->addFilter('RecipeNo', $recipeNo)
                ->addFilter('LineNo', $LineNo)
                ->setPageSize(1)->setCurrentPage(1)
                ->create()
        );

        return $recipe->getItems();
    }

    /**
     * Get modifier by description
     *
     * @param $value
     * @return mixed
     */
    public function getModifierByDescription($value)
    {
        $modifier = $this->itemModifierRepository->getList(
            $this->searchCriteriaBuilder->addFilter('Description', $value)
                ->setPageSize(1)->setCurrentPage(1)
                ->create()
        );

        return $modifier->getItems();
    }


    /**
     * Get custom options from quote item
     *
     * @param $quoteItem
     * @return array
     */
    public function getCustomOptionsFromQuoteItem($quoteItem)
    {
        return $this->configurationHelper->getCustomOptions($quoteItem);
    }


    /**
     * @param $recipeNo
     * @param $value
     * @return mixed
     */
    public function getCustomOptions($value)
    {
        $modifier = $this->itemModifierRepository->getList(
            $this->searchCriteriaBuilder->addFilter('Description', $value)
                ->setPageSize(1)->setCurrentPage(1)
                ->create()
        );

        return $modifier->getItems();
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
            ['field' => 'main_table.scope_id', 'value' => $store->getWebsiteId(), 'condition_type' => 'eq']
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
            null
        );
        $collection2->getSelect()->group('main_table.DealNo')
            ->reset(Select::COLUMNS)
            ->columns(['main_table.DealNo']);

        $filters1 = [
            ['field' => 'main_table.scope_id', 'value' => $store->getWebsiteId(), 'condition_type' => 'eq'],
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
            null
        );
        $collection1->getSelect()->group('main_table.DealNo')
            ->reset(Select::COLUMNS)
            ->columns(['main_table.DealNo']);
        $select = $this->resourceConnection->getConnection()->select()->union(
            [$collection1->getSelect(), $collection2->getSelect()]
        );

        $query = $select->__toString();

        return $this->resourceConnection->getConnection()->fetchAll($select);
    }

    /**
     * Get item modifier batch size
     *
     * @return string
     */
    public function getItemModifiersBatchSize()
    {
        return $this->lsr->getStoreConfig(LSR::SC_REPLICATION_ITEM_MODIFIER_BATCH_SIZE);
    }

    /**
     * Get item recipe batch size
     *
     * @return string
     */
    public function getItemRecipeBatchSize()
    {
        return $this->lsr->getStoreConfig(LSR::SC_REPLICATION_ITEM_RECIPE_BATCH_SIZE);
    }

    /**
     * Get main deal line
     *
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
     * Get All Deals Given Main Item Sku And scope
     *
     * @param $product
     * @param $scopeId
     * @param $replInvStatus
     * @return mixed
     */
    public function getAllDealsGivenMainItemSku($product, $scopeId, $replInvStatus)
    {
        $mainItemSku = $product ? $product->getSku() : $replInvStatus->getSku();
        return $this->replHierarchyHospDealRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('No', $mainItemSku)
                ->addFilter('Type', 'Item')
                ->addFilter('scope_id', $scopeId)
                ->create()
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
                    return $value->getSortOrder();
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

    /**
     * Getting the kitchen order status information
     *
     * @param $orderId
     * @param $webStore
     * @return HospOrderStatusResponse|ResponseInterface|null
     * @throws NoSuchEntityException
     */
    public function getKitchenOrderStatus($orderId, $webStore)
    {
        $response = null;

        if ($this->lsr->isLSR($this->lsr->getCurrentStoreId())) {
            if (version_compare($this->lsr->getOmniVersion(), '4.19', '>')) {
                $operation = new Operation\HospOrderStatus();
                $request   = new Entity\HospOrderStatus();
            } else {
                $request   = new Entity\HospOrderKotStatus();
                $operation = new Operation\HospOrderKotStatus();
            }
            $request->setOrderId($orderId);
            $request->setStoreId($webStore);
            $response = $operation->execute($request);
        }

        return $response;
    }

    /**
     * Get status detail from status mapping
     *
     * @param $orderId
     * @param $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getKitchenOrderStatusDetails($orderId, $storeId, $updateSession = true)
    {
        $status      = $productionTime = $statusDescription = $qCounter = $kotNo = $tableNo = $receiptNo = '';
        $resultArray = [];
        $linesData   = [];
        $order       = $this->getOrderByOrderId($orderId);
        if (!empty($order)) {
            $orderId = $order->getData('document_id');
        }
        if (empty($order)) {
            $order = $this->getOrderByDocumentId($orderId);
        }
        if (empty($order)) {
            $order = $this->getOrderByMagId($orderId);
            if ($order) {
                $orderId = $order->getData('document_id');
            }
        }
        if ($order) {
            $qrcodeInfo = $order->getData(LSR::LS_QR_CODE_ORDERING);
            if ($qrcodeInfo) {
                $qrcodeParams = $this->serializerJson->unserialize($qrcodeInfo);
                $tableNo      = $qrcodeParams['table_no'];
            }

            $itemQtyMap = [];
            foreach ($order->getAllVisibleItems() as $orderItem) {
                $itemId = $orderItem->getProduct()->getData(LSR::LS_ITEM_ID_ATTRIBUTE_CODE);
                if ($itemId) {
                    if (!isset($itemQtyMap[$itemId])) {
                        $itemQtyMap[$itemId] = 0;
                    }
                    $itemQtyMap[$itemId] += $orderItem->getQtyOrdered();
                }
            }

            $response = $this->getKitchenOrderStatus(
                $orderId,
                $storeId
            );

            if (!empty($response)) {
                if (version_compare($this->lsr->getOmniVersion(), '4.19', '>')) {
                    $orderStatusResult = $response->getHospOrderStatusResult();
                    $orderHospStatus   = method_exists($orderStatusResult, 'getOrderHospStatus') ?
                        $orderStatusResult->getOrderHospStatus() : null;
                    if (is_array($orderHospStatus)) {
                        foreach ($orderHospStatus as $resp) {
                            $status    = $resp->getStatus();
                            $qCounter  = $resp->getQueueCounter();
                            $kotNo     = $resp->getKotNo();
                            $receiptNo = $resp->getReceiptNo();
                            if ($this->lsr->displayEstimatedDeliveryTime()) {
                                $productionTime = $resp->getProductionTime();
                            }

                            if (array_key_exists($status, $this->lsr->kitchenStatusMapping())) {
                                if ($status != KOTStatus::SENT && $status != KOTStatus::STARTED) {
                                    $productionTime = 0;
                                }
                                $statusDescription = $this->lsr->kitchenStatusMapping()[$status];
                            }
                            $lines   = $resp->getLines()->getOrderHospStatusLine();
                            $itemIds = [];
                            foreach ($lines as $line) {
                                $itemIds[] = $line->getNumber();
                            }

                            $productsData = $this->itemHelper->getProductsInfoByItemIds($itemIds);
                            $productMap   = [];
                            foreach ($productsData as $product) {
                                if ($product->getVisibility() == Visibility::VISIBILITY_NOT_VISIBLE) {
                                    continue;
                                }
                                $productMap[$product->getData(LSR::LS_ITEM_ID_ATTRIBUTE_CODE)] = [
                                    'productName'   => $product->getName(),
                                    'imageUrl'      => $this->getProductImageUrl($product),
                                    'imagePath'     => $product->getImage(),
                                    'productUrl'    => $this->productUrlBuilder->getUrl($product),
                                    'productUrlKey' => $product->getUrlKey()
                                ];
                            }

                            $itemCounts = [];
                            foreach ($lines as $line) {
                                $itemId = $line->getNumber();
                                if (!isset($itemCounts[$itemId])) {
                                    $itemCounts[$itemId] = 1;
                                } else {
                                    $itemCounts[$itemId]++;
                                }
                            }

                            $linesData = [];
                            foreach ($itemCounts as $itemId => $quantity) {
                                if ($itemId) {
                                    $productName = isset($productMap[$itemId]) ?
                                        $productMap[$itemId]['productName'] : $itemId;
                                    $imageUrl    = isset($productMap[$itemId]) ? $productMap[$itemId]['imageUrl'] : '';
                                    $imagePath   = isset($productMap[$itemId]) ? $productMap[$itemId]['imagePath'] : '';
                                    $linesData[] = [
                                        'itemId'        => $itemId,
                                        'productName'   => $productName,
                                        'imageUrl'      => $imageUrl,
                                        'imagePath'     => $imagePath,
                                        'quantity'      => (int)(isset($itemQtyMap[$itemId]) ? $itemQtyMap[$itemId] : $quantity),
                                        'productUrl'    => isset($productMap[$itemId]) ?
                                            $productMap[$itemId]['productUrl'] : '',
                                        'productUrlKey' => isset($productMap[$itemId]) ?
                                            $productMap[$itemId]['productUrlKey'] . ".html" : ''
                                    ];
                                }
                            }
                            $resultArray[] = [
                                'status'             => $status,
                                'status_description' => $statusDescription,
                                'production_time'    => $productionTime,
                                'q_counter'          => $qCounter,
                                'kot_no'             => $kotNo,
                                'lines'              => $linesData,
                                'table_no'           => $tableNo,
                                'receipt_no'         => $receiptNo
                            ];
                        }
                    } else {
                        $status    = $orderStatusResult->getStatus();
                        $qCounter  = $orderStatusResult->getQueueCounter();
                        $kotNo     = $orderStatusResult->getKotNo();
                        $receiptNo = $orderStatusResult->getReceiptNo();

                        if ($this->lsr->displayEstimatedDeliveryTime()) {
                            $productionTime = $orderStatusResult->getProductionTime();
                        }
                        if (array_key_exists($status, $this->lsr->kitchenStatusMapping())) {
                            if ($status != KOTStatus::SENT && $status != KOTStatus::STARTED) {
                                $productionTime = 0;
                            }
                            $statusDescription = $this->lsr->kitchenStatusMapping()[$status];
                        }
                        $resultArray[] = [
                            'status'             => $status,
                            'status_description' => $statusDescription,
                            'production_time'    => $productionTime,
                            'q_counter'          => $qCounter,
                            'kot_no'             => $kotNo,
                            'lines'              => $linesData,
                            'table_no'           => $tableNo,
                            'receipt_no'         => $receiptNo
                        ];
                    }
                } else {
                    $status = $response->getHospOrderKotStatusResult()->getStatus();
                    if (array_key_exists($status, $this->lsr->kitchenStatusMapping())) {
                        $statusDescription = $this->lsr->kitchenStatusMapping()[$status];
                    }
                    $resultArray[] = [
                        'status'             => $status,
                        'status_description' => $statusDescription,
                        'lines'              => $linesData,
                        'table_no'           => $tableNo
                    ];
                }
            }
        }

        if (!empty($resultArray) && isset($resultArray[0]['q_counter']) && empty($order->getLsOrderId())) {
            $receiptNo = $resultArray[0]['q_counter'];
            $order->setData('ls_order_id', $receiptNo);
            if ($updateSession) {
                $this->qrCodeHelper->getCheckoutSessionObject()->unsLastLsOrderId();
                $this->qrCodeHelper->getCheckoutSessionObject()->setLastLsOrderId($receiptNo);
            }
            $this->orderResourceModel->save($order);
        }

        return $resultArray;
    }

    /**
     * Returning LSR object to be used on different place
     *
     * @return LSR
     */
    public function getLSR()
    {
        return $this->lsr;
    }

    /**
     * Upload File
     *
     * @param $fileInfo
     * @return string|null
     * @throws FileSystemException
     */
    public function uploadFile($fileInfo)
    {
        $media    = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $fileName = null;

        if (is_array($fileInfo)) {
            $uploader   = $this->uploaderFactory->create(['fileId' => $fileInfo]);
            $workingDir = $media->getAbsolutePath(self::DESTINATION_FOLDER);
            $uploader->save($workingDir);
            $fileName = self::DESTINATION_FOLDER . DIRECTORY_SEPARATOR . $uploader->getUploadedFileName();

        }

        return $fileName;
    }

    /**
     * Get Image
     *
     * @param string $imageId
     * @return string
     * @throws NoSuchEntityException
     */
    public function getImage($imageId = '')
    {
        $image     = '';
        $imageSize = [
            'height' => \Ls\Core\Model\LSR::DEFAULT_IMAGE_HEIGHT,
            'width'  => \Ls\Core\Model\LSR::DEFAULT_IMAGE_WIDTH
        ];
        /** @var ImageSize $imageSizeObject */
        $imageSizeObject = $this->loyaltyHelper->getImageSize($imageSize);
        $result          = $this->loyaltyHelper->getImageById($imageId, $imageSizeObject);
        if (!empty($result) && !empty($result['format']) && !empty($result['image'])) {
            //check if directory exists or not and if it has the proper permission or not
            $offerpath = $this->getMediaPathtoStore();
            // @codingStandardsIgnoreStart
            if (!is_dir($offerpath)) {
                $this->file->mkdir($offerpath, 0775);
            }
            $format      = $result['format'] ? strtolower($result['format']) : 'jpg';
            $imageName   = $this->replicationHelper->oSlug($imageId);
            $output_file = "{$imageName}.$format";
            $file        = "{$offerpath}{$output_file}";
            if (!$this->file->fileExists($file)) {
                $base64     = $result['image'];
                $image_file = fopen($file, 'wb');
                fwrite($image_file, base64_decode($base64));
                fclose($image_file);
            }
            // @codingStandardsIgnoreEnd
            $image = self::DESTINATION_FOLDER . DIRECTORY_SEPARATOR . "{$output_file}";
        }
        return $image;
    }

    /**
     * Return the media path of the swatches
     *
     * @return string
     */
    public function getMediaPathtoStore()
    {
        $mediaDirectory = $this->loyaltyHelper->getMediaPathtoStore();
        return $mediaDirectory . self::DESTINATION_FOLDER . DIRECTORY_SEPARATOR;
    }

    /**
     * Get First Available Option Value Image Path
     *
     * @param $optionValues
     * @return mixed|null
     */
    public function getFirstAvailableOptionValueImagePath($optionValues)
    {
        foreach ($optionValues as $value) {
            if (!$value->getSwatch()) {
                continue;
            }
            break;
        }

        return $value ? $value->getSwatch() : null;
    }

    /**
     * Get Image given Item
     *
     * @param $sku
     * @param $scopeId
     * @return false|mixed|null
     */
    public function getImageGivenItem($sku, $scopeId)
    {
        $replImage = null;
        // Check for all images.
        $filtersForAllImages  = [
            ['field' => 'KeyValue', 'value' => $sku, 'condition_type' => 'eq'],
            ['field' => 'TableName', 'value' => 'Item', 'condition_type' => 'eq'],
            ['field' => 'scope_id', 'value' => $scopeId, 'condition_type' => 'eq']
        ];
        $criteriaForAllImages = $this->replicationHelper->buildCriteriaForDirect(
            $filtersForAllImages,
            1,
            false
        );
        /** @var ReplImageLinkSearchResults $newImagestoProcess */
        $newImagesToProcess = $this->replImageLinkRepositoryInterface->getList($criteriaForAllImages);

        if ($newImagesToProcess->getTotalCount() > 0) {
            $replImage = current($newImagesToProcess->getItems());
        }

        return $replImage;
    }

    /**
     * Get Current product
     *
     * @return mixed|null
     */
    public function getCurrentProduct()
    {
        return $this->registry->registry('current_product');
    }

    /**
     * Get deal line uom
     *
     * @param $sku
     * @param $dealLineId
     * @param $dealModLineId
     * @return null
     * @throws NoSuchEntityException
     */
    public function getDealLineUomGivenData($sku, $dealLineId, $dealModLineId)
    {
        $uom                        = null;
        $filterForDealLine          = [
            ['field' => 'DealNo', 'value' => $sku, 'condition_type' => 'eq'],
            ['field' => 'DealLineNo', 'value' => $dealLineId, 'condition_type' => 'eq'],
            ['field' => 'LineNo', 'value' => $dealModLineId, 'condition_type' => 'eq'],
            ['field' => 'scope_id', 'value' => $this->lsr->getCurrentWebsiteId(), 'condition_type' => 'eq']
        ];
        $criteria                   = $this->replicationHelper->buildCriteriaForDirect($filterForDealLine, 1);
        $replHierarchyHospDealLines = $this->replHierarchyHospDealLineRepository->getList($criteria);

        if ($replHierarchyHospDealLines->getTotalCount() > 0) {
            $dealLine = current($replHierarchyHospDealLines->getItems());
            $uom      = $dealLine->getUnitOfMeasure();
        }

        return $uom;
    }

    /**
     * Get meal item main item sku
     *
     * @param $sku
     * @return null
     */
    public function getMealMainItemSku($sku)
    {
        $mainDealLine = current($this->getMainDealLine($sku));

        return $mainDealLine ? $mainDealLine->getNo() : null;
    }

    /**
     * Get product given sku
     *
     * @param $sku
     * @return mixed|null
     */
    public function getProductFromRepositoryGivenSku($sku)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('sku', $sku)->create();
        $productList    = $this->productRepository->getList($searchCriteria)->getItems();

        return array_pop($productList);
    }

    /**
     * Get products by Item Ids
     *
     * @param string $itemId
     * @return array|ProductInterface[]
     */
    public function getProductsByItemId($itemId)
    {
        return current($this->itemHelper->getProductsInfoByItemIds([$itemId]));
    }

    /**
     * Get store information
     *
     * @return DataObject
     * @throws NoSuchEntityException
     */
    public function getStoreInformation()
    {
        $store = $this->storeManager->getStore();

        return $this->storeInfo->getStoreInformationObject($store);
    }

    /**
     * Get anonymous address
     *
     * @param array $anonymousOrderRequiredAttributes
     * @return AddressInterface
     * @throws NoSuchEntityException
     */
    public function getAnonymousAddress($anonymousOrderRequiredAttributes)
    {
        $storeInformation = $this->getStoreInformation();
        $address          = $this->addressFactory->create();

        foreach ($anonymousOrderRequiredAttributes as $addressAttribute) {
            if ($addressAttribute == 'email') {
                continue;
            } elseif (isset(self::ADDRESS_ATTRIBUTE_MAPPER[$addressAttribute])) {
                if (is_array(self::ADDRESS_ATTRIBUTE_MAPPER[$addressAttribute])) {
                    $streets = [];

                    foreach (self::ADDRESS_ATTRIBUTE_MAPPER[$addressAttribute] as $at) {

                        if ($storeInformation->getData($at)) {
                            $streets[] = $storeInformation->getData($at);
                        }
                    }
                    $address->setData($addressAttribute, $streets);
                } else {
                    $address->setData(
                        $addressAttribute,
                        $storeInformation->getData(self::ADDRESS_ATTRIBUTE_MAPPER[$addressAttribute])
                    );
                }
            } else {
                $address->setData($addressAttribute, $storeInformation->getData($addressAttribute));
            }
        }

        $address->setShippingMethod('flatrate_flatrate');

        return $address;
    }

    /**
     * Get all address attributes
     *
     * @return AttributeInterface[]
     */
    public function getAllAddressAttributes()
    {
        return $this->attributeRepository->getList(
            AddressMetadataInterface::ENTITY_TYPE_ADDRESS,
            $this->searchCriteriaBuilder->create()
        )->getItems();
    }

    /**
     * Get all address attributes code
     *
     * @return AttributeInterface[]
     */
    public function getAllAddressAttributesCodes()
    {
        $addressAttributes = $this->getAllAddressAttributes();

        foreach ($addressAttributes as &$addressAttribute) {
            $addressAttribute = $addressAttribute->getAttributeCode();
        }

        return $addressAttributes;
    }

    /**
     * Get customer email for anonymous orders
     *
     * @return mixed
     */
    public function getAnonymousOrderCustomerEmail()
    {
        return $this->scopeConfig->getValue(
            'trans_email/ident_custom1/email',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get anonymous order prefill attributes
     *
     * @param array $anonymousOrderRequiredAttributes
     * @return array
     */
    public function getAnonymousOrderPrefillAttributes($anonymousOrderRequiredAttributes)
    {
        $prefillAttributes   = [];
        $addressAttributes   = $this->getAllAddressAttributes();
        $removeCheckoutSteps = $this->lsr->getStoreConfig(
            Lsr::ANONYMOUS_REMOVE_CHECKOUT_STEPS,
            $this->lsr->getStoreId()
        );
        foreach ($addressAttributes as $addressAttribute) {
            if (isset($anonymousOrderRequiredAttributes[$addressAttribute->getAttributeCode()])
                && $anonymousOrderRequiredAttributes[$addressAttribute->getAttributeCode()] == '1'
                && $removeCheckoutSteps
            ) {
                continue;
            }
            $prefillAttributes[] = $addressAttribute->getAttributeCode();
        }

        return $prefillAttributes;
    }

    /**
     * Get formatted address attributes config
     *
     * @param int $storeId
     * @return array
     */
    public function getformattedAddressAttributesConfig($storeId)
    {
        $config = $this->serializerJson->unserialize($this->lsr->getStoreConfig(
            Lsr::ANONYMOUS_ORDER_REQUIRED_ADDRESS_ATTRIBUTES,
            $storeId
        ));

        $formattedConfig = [];

        foreach ($config as $value) {
            $formattedConfig [$value['address_attribute_code']] = $value['is_required'] ?? '0';
        }

        return $formattedConfig;
    }

    /**
     * Get order pickup date time slot given document_id
     *
     * @param string $documentId
     * @return mixed
     */
    public function getOrderPickupDateTimeSlotGivenDocumentId($documentId)
    {
        $magentoOrder = $this->orderHelper->getMagentoOrderGivenDocumentId($documentId);
        return ($magentoOrder) ? $magentoOrder->getData('pickup_date_timeslot') : '';
    }

    /**
     * Get order pickup date
     *
     * @param string $documentId
     * @return string
     */
    public function getOrderPickupDate($documentId)
    {
        $dateTime = $this->getOrderPickupDateTimeSlotGivenDocumentId($documentId);

        return $dateTime ? explode(' ', $dateTime)[0] : '';
    }

    /**
     * Get order pickup date
     *
     * @param string $documentId
     * @return string
     */
    public function getOrderPickupTime($documentId)
    {
        $dateTime = $this->getOrderPickupDateTimeSlotGivenDocumentId($documentId);

        return $dateTime ? explode(' ', $dateTime)[1] . ' ' . explode(' ', $dateTime)[2] : '';
    }

    /**
     * Fake lines order status webhook
     *
     * @param array $data
     * @return void
     * @throws NoSuchEntityException
     */
    public function fakeOrderLinesStatusWebhook(&$data)
    {
        $magentoOrder = $this->getOrderByDocumentId($data['OrderId']);

        if (!empty($magentoOrder) && $this->lsr->isHospitalityStore($magentoOrder->getStoreId())) {
            $lineNo = 10000;
            $index  = $qtyOrdered = 0;
            $status = $data['HeaderStatus'];

            foreach ($magentoOrder->getAllVisibleItems() as $orderItem) {
                list($itemId, $variantId, $uom) = $this->itemHelper->getComparisonValues(
                    $orderItem->getSku(),
                    $orderItem->getProductId()
                );

                $qtyOrdered += $orderItem->getQtyOrdered();

                while ($index <= $qtyOrdered - 1) {
                    $data['Lines'][$index] = $this->getLine(
                        $orderItem->getPrice() / $orderItem->getQtyOrdered(),
                        $itemId,
                        $uom,
                        $variantId,
                        $status,
                        1,
                        '',
                        '',
                        $lineNo
                    );

                    $lineNo += 10000;
                    $index++;
                }
            }
            $isOffline = $magentoOrder->getPayment()->getMethodInstance()->isOffline();
            if (!$isOffline) {
                $data['Amount'] = $magentoOrder->getGrandTotal();
            }
            $isClickAndCollectOrder = $this->isClickAndcollectOrder($magentoOrder);

            if (!$isClickAndCollectOrder && $magentoOrder->getShippingAmount() > 0) {
                $data['Lines'][$index] = $this->getLine(
                    $magentoOrder->getShippingAmount(),
                    $this->lsr->getStoreConfig(LSR::LSR_SHIPMENT_ITEM_ID, $magentoOrder->getStoreId()),
                    '',
                    '',
                    $status,
                    $qtyOrdered,
                    '',
                    '',
                    $lineNo
                );
            }
        }
    }

    /**
     * Fix lines order status webhook for group ordering
     *
     * @param $data
     * @param $magentoOrder
     * @return array
     * @throws NoSuchEntityException
     */
    public function fixOrderLinesStatusWebhookGroupOrdering($data, $magentoOrder)
    {
        $itemLines = [];
        if (!empty($magentoOrder) && $this->lsr->isHospitalityStore($magentoOrder->getStoreId())) {
            $lineNo     = 10000;
            $index      = 1;
            $qtyOrdered = 0;
            $status     = $data['HeaderStatus'];
            foreach ($magentoOrder->getAllVisibleItems() as $orderItem) {

                [$itemId, $variantId, $uom] = $this->itemHelper->getComparisonValues(
                    $orderItem->getSku(),
                    $orderItem->getProductId()
                );
                $totalQtyOrdered = $orderItem->getQtyOrdered();
                foreach ($data['Lines'] as &$line) {
                    if ($line['ItemId'] == $itemId && $totalQtyOrdered <= $index) {
                        $line['Quantity']        = 1;
                        $line['Amount']          = $orderItem->getQtyOrdered() > 0
                            ? $orderItem->getPrice() / $orderItem->getQtyOrdered() : 0;
                        $line['NewStatus']       = $status;
                        $line['UnitOfMeasureId'] = $uom;
                        $index++;
                        $lineNo       += 10000;
                        $itemLines [] = $line;
                    }
                }
            }
            $isClickAndCollectOrder = $this->isClickAndcollectOrder($magentoOrder);
            if (!$isClickAndCollectOrder && $magentoOrder->getShippingAmount() > 0) {
                $data['Lines'][] = $this->getLine(
                    $magentoOrder->getShippingAmount(),
                    $this->lsr->getStoreConfig(LSR::LSR_SHIPMENT_ITEM_ID, $magentoOrder->getStoreId()),
                    '',
                    '',
                    $status,
                    $qtyOrdered,
                    '',
                    '',
                    ($lineNo + 10000)
                );
            }
        }

        return $itemLines;
    }


    /**
     * Fix order lines status
     *
     * @param $data
     * @return void
     */
    public function fixOrderLinesStatus(&$data)
    {
        $status       = $data['HeaderStatus'];
        $magentoOrder = $this->getOrderByDocumentId($data['OrderId']);
        foreach ($magentoOrder->getAllVisibleItems() as $orderItem) {
            list($itemId, $variantId, $uom) = $this->itemHelper->getComparisonValues(
                $orderItem->getSku(),
                $orderItem->getProductId()
            );
            foreach ($data['Lines'] as &$line) {
                if ($line['Quantity'] == 0 || $line['NewStatus'] == null) {
                    $line['Quantity']  = 1;
                    $line['NewStatus'] = $status;
                }
                if (empty($line['UnitOfMeasureId']) && $itemId == $line['ItemId']) {
                    $line['UnitOfMeasureId'] = $uom;
                }
                if ($line['Amount'] == 0 && $itemId == $line['ItemId'] && $uom == $line['UnitOfMeasureId']) {
                    $line['Quantity']  = 1;
                    $line['NewStatus'] = $status;
                    $line['Amount']    = $orderItem->getQtyOrdered() > 0
                        ? $orderItem->getPrice() / $orderItem->getQtyOrdered() : 0;
                }
            }
        }
    }

    /**
     * Get orders by document id
     *
     * @param $documentId
     * @param $all
     * @return false|\Magento\Sales\Api\Data\OrderInterface|OrderSearchResultInterface | \Magento\Sales\Api\Data\OrderInterface[]
     */
    public function getOrderByDocumentId($documentId)
    {
        try {
            $order      = false;
            $order      = $this->orderRepository->getList(
                $this->searchCriteriaBuilder->addFilter('document_id', $documentId)->create()
            );
            $orderArray = $order->getItems();
            $order      = end($orderArray);
        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
        }

        return $order;
    }

    /**
     * Get orders by order id
     *
     * @param $documentId
     * @param $all
     * @return false|\Magento\Sales\Api\Data\OrderInterface|OrderSearchResultInterface | \Magento\Sales\Api\Data\OrderInterface[]
     */
    public function getOrderByOrderId($documentId)
    {
        try {
            $order      = false;
            $order      = $this->orderRepository->getList(
                $this->searchCriteriaBuilder->addFilter('ls_order_id', $documentId)->create()
            );
            $orderArray = $order->getItems();
            $order      = end($orderArray);
        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
        }

        return $order;
    }

    /**
     * Get order by magento order id
     *
     * @param $orderId
     * @return false|OrderSearchResultInterface|mixed
     */
    public function getOrderByMagId($orderId)
    {
        try {
            $order      = false;
            $order      = $this->orderRepository->getList(
                $this->searchCriteriaBuilder->addFilter('increment_id', $orderId)->create()
            );
            $orderArray = $order->getItems();
            $order      = end($orderArray);
        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
        }

        return $order;
    }

    /**
     * Check is click and collect order
     *
     * @param mixed $magentoOrder
     * @return bool
     */
    public function isClickAndcollectOrder($magentoOrder)
    {
        return $magentoOrder->getShippingMethod() == 'clickandcollect_clickandcollect';
    }

    /**
     * Get Line
     *
     * @param mixed $amount
     * @param mixed $itemId
     * @param mixed $uom
     * @param mixed $variantId
     * @param mixed $status
     * @param mixed $qty
     * @param mixed $prevStatus
     * @param mixed $extLineStatus
     * @param mixed $lineNo
     * @return array
     */
    public function getLine($amount, $itemId, $uom, $variantId, $status, $qty, $prevStatus, $extLineStatus, $lineNo)
    {
        return [
            'Amount'          => $amount,
            'ItemId'          => $itemId,
            'UnitOfMeasureId' => $uom,
            'VariantId'       => $variantId,
            'NewStatus'       => $status,
            'Quantity'        => $qty,
            'PrevStatus'      => $prevStatus,
            'ExtLineStatus'   => $extLineStatus,
            'LineNo'          => $lineNo
        ];
    }

    /**
     * Remove Checkout Step enabled
     *
     * @param $quote
     * @return int
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function removeCheckoutStepEnabled($quote = null)
    {
        $storeId                   = $this->storeManager->getStore()->getId();
        $removeCheckoutStepEnabled = $this->lsr->getStoreConfig(
            Lsr::ANONYMOUS_REMOVE_CHECKOUT_STEPS,
            $storeId
        );
        if (empty($quote)) {
            $quote = $this->qrcodeHelperObject()->getCheckoutSessionObject()->getQuote();
        }
        $qrCodeParams = $quote->getData(LSR::LS_QR_CODE_ORDERING);
        if (empty($qrCodeParams)) {
            $qrCodeParams = $this->qrcodeHelperObject()->getQrCodeOrderingInSession();
        }

        return $removeCheckoutStepEnabled & !empty($qrCodeParams);
    }

    /**
     * Get remove checkout steps configuration
     *
     * @return int
     * @throws NoSuchEntityException
     */
    public function getRemoveCheckoutStepEnabled()
    {
        $storeId                   = $this->storeManager->getStore()->getId();
        $removeCheckoutStepEnabled = $this->lsr->getStoreConfig(
            Lsr::ANONYMOUS_REMOVE_CHECKOUT_STEPS,
            $storeId
        );

        return $removeCheckoutStepEnabled;
    }

    /**
     * Return qrcode helper class object
     *
     * @return QrCodeHelper
     */
    public function qrcodeHelperObject()
    {
        return $this->qrCodeHelper;
    }

    /**
     * Get Product Image URL
     *
     * @param $product
     * @return string|null
     */
    public function getProductImageUrl($product)
    {
        if ($product->getSmallImage()) {
            return $this->imageHelper->init($product, 'product_small_image')
                ->setImageFile($product->getSmallImage())
                ->getUrl();
        }

        return null;
    }

    /**
     * Format items for sales entries
     *
     * @param $subject
     * @param $items
     * @param $magOrder
     * @return array
     */
    public function getItems($subject, $items, $magOrder)
    {
        $itemsArray  = [];
        $childrenKey = 'subitems';
        foreach ($items as $item) {
            $data = [
                'amount'                 => $item->getAmount(),
                'click_and_collect_line' => $item->getClickAndCollectLine(),
                'discount_amount'        => $item->getDiscountAmount(),
                'discount_percent'       => $item->getDiscountPercent(),
                'item_description'       => $item->getItemDescription(),
                'item_id'                => $item->getItemId(),
                'item_image_id'          => $item->getItemImageId(),
                'line_number'            => $item->getLineNumber(),
                'line_type'              => $item->getLineType(),
                'net_amount'             => $item->getNetAmount(),
                'net_price'              => $item->getNetPrice(),
                'parent_line'            => $item->getParentLine(),
                'price'                  => $item->getPrice(),
                'quantity'               => $item->getQuantity(),
                'store_id'               => $item->getStoreId(),
                'tax_amount'             => $item->getTaxAmount(),
                'uom_id'                 => $item->getUomId(),
                'variant_description'    => $item->getVariantDescription(),
                'variant_id'             => $item->getVariantId(),
            ];
            if ($magOrder) {
                $data['custom_options'] = $this->formatCustomOptions($magOrder, $item->getItemId(), $subject);
            }
            $lineNumber = $item->getLineNumber();
            $parentLine = $item->getParentLine();
            if (empty($parentLine) || $lineNumber == $parentLine) {
                if (!empty($itemsArray) && array_key_exists($lineNumber, $itemsArray)) {
                    $tempArray[$lineNumber]                = $data;
                    $tempArray [$lineNumber][$childrenKey] = $itemsArray[$lineNumber][$childrenKey];
                    $itemsArray[$lineNumber]               = $tempArray[$lineNumber];
                    $tempArray                             = null;
                } else {
                    $itemsArray [$lineNumber] = $data;
                }
            } else {
                $itemsArray[$parentLine][$childrenKey][$lineNumber] = $data;
            }
        }

        $itemsArray = $this->sortItemsAsParentChild($itemsArray, $childrenKey);

        return $this->sumTotalItemsAmount($itemsArray, $childrenKey);
    }

    /**
     * Adding up prices for subitems
     *
     * @param $itemsArray
     * @param $childrenKey
     * @return array
     */
    public function sumTotalItemsAmount($itemsArray, $childrenKey)
    {
        foreach ($itemsArray as $mainKey => $arrayData) {
            $lineType = $arrayData['line_type'];
            $amount   = $arrayData['amount'];
            if (array_key_exists($childrenKey, $arrayData)) {
                foreach ($arrayData[$childrenKey] as $key => $value) {
                    if ($lineType == Entity\Enum\LineType::DEAL) {
                        if (array_key_exists($childrenKey, $value)) {
                            foreach ($value[$childrenKey] as $subitems) {
                                $amount += $subitems['amount'];
                            }
                        }
                    } else {
                        $amount += $value['amount'];
                    }
                }
            }
            $itemsArray[$mainKey]['amount'] = $amount;
        }

        return $itemsArray;
    }

    /**
     * Sorting items
     *
     * @param $itemsArray
     * @param $childrenKey
     * @return array
     */
    public function sortItemsAsParentChild($itemsArray, $childrenKey)
    {
        foreach ($itemsArray as $mainKey => $arrayData) {
            if (array_key_exists($childrenKey, $arrayData)) {
                foreach ($arrayData[$childrenKey] as $key => $value) {
                    if (array_key_exists($key, $itemsArray)) {
                        $itemsArray[$mainKey][$childrenKey][$key][$childrenKey] = $itemsArray[$key][$childrenKey];
                        unset($itemsArray[$key]);
                    }
                }
            }
        }

        return $itemsArray;
    }

    /**
     * Get custom options from magento
     *
     * @param $magOrder
     * @param $id
     * @param $subject
     * @return array
     */
    public function formatCustomOptions($magOrder, $id, $subject)
    {
        $outputOptions = [];
        if (!empty($magOrder)) {
            $items   = $magOrder->getAllVisibleItems();
            $counter = 0;
            foreach ($items as $item) {
                list($itemId) = $subject->itemHelper->getComparisonValues(
                    $item->getSku()
                );
                if ($itemId == $id) {
                    $options = $item->getProductOptions();
                    if (isset($options['options']) && !empty($options['options'])) {
                        foreach ($options['options'] as $option) {
                            $outputOptions[$counter]['label'] = $option['label'];
                            $outputOptions[$counter]['value'] = $option['value'];
                            $counter++;
                        }
                    }
                }
            }
        }

        return $outputOptions;
    }

    /**
     * Return serialize json class object
     *
     * @return QrCodeHelper
     */
    public function getJson()
    {
        return $this->serializerJson;
    }

    /**
     * Set hospitality order id
     *
     * @param OrderInterface $order
     * @return void
     * @throws NoSuchEntityException
     */
    public function saveHospOrderId(OrderInterface $order)
    {
        try {
            $documentId = $order->getDocumentId();

            if ($documentId) {
                $webStore      = $this->lsr->getActiveWebStore();
                $statusDetails = $this->getKitchenOrderStatusDetails($documentId, $webStore);

                if (!empty($statusDetails) && isset($statusDetails[0]['q_counter'])) {
                    $receiptNo = $statusDetails[0]['q_counter'];
                    $order->setData('ls_order_id', $receiptNo);
                    $this->orderResourceModel->save($order);
                }
            }
        } catch (\Exception $e) {
            $this->_logger->error('Error processing order Hospitality Order Id: ' . $e->getMessage());
        }
    }

    /**
     * Do housekeeping for given order
     *
     * @param OrderInterface $order
     * @return void
     * @throws NoSuchEntityException
     */
    public function doHouseKeepingForGivenOrder(OrderInterface $order)
    {
        if ($this->lsr->isHospitalityStore($order->getStoreId())) {
            $this->saveHospOrderId($order);
            $this->clearCheckAvailabilityCachedContent($order->getStoreId());
            $productIds = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $productIds[] = $item->getProductId();
            }

            $this->clearFpcCacheForGivenProducts($productIds);
        }
    }

    /**
     * Clear check availability cached content
     *
     * @param int $storeId
     * @return void
     */
    public function clearCheckAvailabilityCachedContent($storeId)
    {
        $cacheKey = LSR::LS_HOSP_CHECK_AVAILABILITY . $storeId;
        $this->cacheHelper->removeCachedContent($cacheKey);
    }

    /**
     * Clear FPC cache for given products
     *
     * @param array $productIds
     * @return void
     */
    public function clearFpcCacheForGivenProducts($productIds)
    {
        $this->replicationHelper->flushFpcCacheAgainstIds($productIds);
    }

    /**
     * Get orders with document_id set and ls_order_id null
     *
     * @param int $storeId
     * @return \Magento\Sales\Api\Data\OrderInterface[]
     */
    public function getOrdersWithDocumentIdWithoutLsOrderId($storeId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('store_id', $storeId, 'eq')
            ->addFilter('document_id', true, 'notnull')
            ->addFilter('ls_order_id', true, 'null')
            ->create();

        return $this->orderRepository->getList($searchCriteria)->getItems();
    }

    /**
     * Verifies the validity of basket data and determines if order creation should be disabled.
     *
     * @param mixed $basketData The basket data to be verified.
     * @return bool True if the basket data is valid or if order creation is allowed; false otherwise.
     * @throws NoSuchEntityException
     */
    public function verifyBasketSync($basketData)
    {
        if (!$this->lsr->isLSR($this->lsr->getCurrentStoreId())) {
            return false;
        }

        $websiteId              = $this->lsr->getCurrentWebsiteId();
        $disableOrderCreateFlag = $this->lsr->getWebsiteConfig(LSR::LS_DISABLE_ORDER_CREATE_ON_BASKET_FAIL, $websiteId);

        if ($disableOrderCreateFlag == 1 && $basketData == null) {
            return false;
        }

        return true;
    }
}
