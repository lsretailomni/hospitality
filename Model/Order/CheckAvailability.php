<?php

namespace Ls\Hospitality\Model\Order;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Helper\CacheHelper;
use \Ls\Omni\Helper\Data;
use \Ls\Omni\Helper\ItemHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\ResourceModel\Product\Option\CollectionFactory as OptionCollectionFactory;

/**
 * Checking current availability of items, deals and modifiers.
 */
class CheckAvailability
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param LSR $lsr
     * @param ItemHelper $itemHelper
     * @param HospitalityHelper $hospitalityHelper
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     * @param Data $dataHelper
     * @param CacheHelper $cacheHelper
     * @param OptionCollectionFactory $optionCollectionFactory
     */

    public function __construct(
        public ProductRepositoryInterface $productRepository,
        public LSR $lsr,
        public ItemHelper $itemHelper,
        public HospitalityHelper $hospitalityHelper,
        public CheckoutSession $checkoutSession,
        public LoggerInterface $logger,
        public Data $dataHelper,
        public CacheHelper $cacheHelper,
        public OptionCollectionFactory $optionCollectionFactory
    ) {
    }

    /**
     * Api call to check the current availability of items
     *
     * @param string $storeId
     * @param array $itemIds
     * @return array
     * @throws NoSuchEntityException
     */
    public function availability($storeId, $itemIds)
    {
        return $this->dataHelper->fetchGivenTableData(
            'LSC Current Availability',
            '',
            [
                [
                    'filterName' => 'Store No.',
                    'filterValue' => $storeId
                ],
                [
                    'filterName' => 'No.',
                    'filterValue' => implode('|', $itemIds)
                ]
            ]
        );
    }

    /**
     * Validate current availability of modifiers and deals
     *
     * @param bool $isItem
     * @param Item $item
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ValidatorException
     */
    public function validateQty($isItem = false, $item = null)
    {
        $checkAvailabilityCollection = $itemIds = [];
        if ($this->lsr->isCheckAvailabilityEnabled()) {
            if ($isItem) {
                $items      = [$item];
                $itemsCount = 1;
            } else {
                $itemsCount = $this->checkoutSession->getQuote()->getItemsCount();
                $items      = $this->checkoutSession->getQuote()->getAllVisibleItems();
            }
            if ($itemsCount > 0) {
                foreach ($items as $item) {
                    $itemQty = $item->getQty();
                    list($itemId, , $unitOfMeasure) = $this->itemHelper->getComparisonValues(
                        $item->getSku()
                    );
                    if (in_array($itemId, $checkAvailabilityCollection)) {
                        $itemQty = $checkAvailabilityCollection[$itemId]['qty'] + $itemQty;
                    }
                    $checkAvailabilityCollection[$itemId] = [
                        'item_id' => $itemId,
                        'name' => $item->getName(),
                        'qty' => $itemQty,
                        'uom' => $unitOfMeasure,
                    ];
                    $this->setModifiersForCheckingAvailability(
                        $item,
                        $checkAvailabilityCollection
                    );
                    $itemIds[] = $itemId;
                }
            }
            $itemIds        = array_unique(array_merge($itemIds, array_keys($checkAvailabilityCollection)));
            $responseResult = $this->availability($this->lsr->getActiveWebStore(), $itemIds);

            if ($responseResult) {
                $this->processResponse($checkAvailabilityCollection, $responseResult);
            }
        }
    }

    /**
     * Set modifiers for check availability
     *
     * @param Item $item
     * @param array $checkAvailabilityCollection
     * @return void
     */
    public function setModifiersForCheckingAvailability(
        $item,
        &$checkAvailabilityCollection
    ) {
        $product = $item->getProduct();
        $options = $this->hospitalityHelper->getCustomOptionsFromQuoteItem($item);
        if ($options) {
            foreach ($options as $option) {
                if ((isset($option['ls_modifier_recipe_id']) ||
                        $product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE))
                    && $option['ls_modifier_recipe_id'] != LSR::LSR_RECIPE_PREFIX
                ) {
                    $qty      = 1;
                    $itemId   = $product->getData(LSR::LS_ITEM_ID_ATTRIBUTE_CODE);
                    $modifier = current($this->hospitalityHelper->getModifierByDescription($option['value']));
                    $source   = $modifier ?:
                        current($this->hospitalityHelper->getDealLineByDescription($option['value'], $itemId));
                    $modifierItemId = "";
                    $unitOfMeasure  = "";
                    if ($source) {
                        $modifierItemId = $modifier ? $source->getTriggerCode() : $source->getItemNo();
                        $unitOfMeasure  = $source->getUnitOfMeasure();
                    }
                    $code  = $modifier ? $source->getCode() : $source->getDealLineCode();

                    if (in_array($modifierItemId, $checkAvailabilityCollection)) {
                        $qty = $checkAvailabilityCollection[$modifierItemId]['qty'] + $qty;
                    }

                    $checkAvailabilityCollection[$modifierItemId] = [
                        'item_id' => $modifierItemId,
                        'name' => $source->getDescription(),
                        'product_name' => $item->getName(),
                        'qty' => $qty,
                        'is_modifier' => true,
                        'code' => $code,
                        'uom' => $unitOfMeasure,
                    ];
                }
            }
        }
    }

    /**
     * Process and validate the response get from check availability Api
     *
     * @param array $checkAvailabilityCollection
     * @param array $responseResult
     * @return void
     * @throws ValidatorException
     */
    public function processResponse(
        $checkAvailabilityCollection,
        $responseResult
    ) {
        $message = '';

        foreach ($responseResult as $result) {
            if (in_array($result['No.'], array_column($checkAvailabilityCollection, 'item_id'))) {
                $record = $checkAvailabilityCollection[$result['No.']];

                if (empty($result['Unit of Measure']) ||
                    ($record['uom'] == $result['Unit of Measure'])
                ) {
                    $qty       = $record['qty'];
                    $resultQty = (int)$result['Available Qty.'];

                    if ($qty > $resultQty || $resultQty == 0) {
                        $name = $record['name'];

                        if (isset($record['is_modifier'])) {
                            $code        = $record['code'];
                            $productName = $record['product_name'];
                            $message     .= __(
                                '%1 modifier option %2 (%3) has quantity of %4 which is greater then currently available quantity %5. Please select different option for this modifier.',
                                $productName,
                                $code,
                                $name,
                                $qty,
                                $resultQty
                            );
                        } else {
                            $message .= __(
                                'Product %1 has quantity of %2 which is greater then current available quantity %3. Please adjust the product quantity.',
                                $name,
                                $qty,
                                $resultQty
                            );
                        }
                        $this->hospitalityHelper->clearCheckAvailabilityCachedContent($this->lsr->getStoreId());
                        throw new ValidatorException(__($message));
                    }
                }
            }
        }
    }

    /**
     * Check if modifiers are available in current availability response
     *
     * @param $customOption
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function checkModifierAvailability(&$customOption)
    {
        if (!$customOption) {
            return $customOption;
        }

        if (isset($customOption['ls_modifier_recipe_id'])
            && $customOption['ls_modifier_recipe_id'] != LSR::LSR_RECIPE_PREFIX) {
            if ($customOption->getValues() == null) {
                return $customOption;
            }
            $itemId  = $customOption->getProduct()->getData(LSR::LS_ITEM_ID_ATTRIBUTE_CODE);
            $storeId = $this->lsr->getCurrentStoreId();
            $checkAvailabilityCollection = $this->checkCatalogAvailability($storeId);
            foreach ($customOption->getValues() as &$value) {
                $modifierItemId = "";
                $unitOfMeasure  = "";
                $modifier = current($this->hospitalityHelper->getModifierByDescription($value['title']));
                $source   = $modifier ?: current($this->hospitalityHelper->getDealLineByDescription($value['title'], $itemId));

                if ($source) {
                    $modifierItemId = $modifier ? $source->getTriggerCode() : $source->getItemNo();
                    $unitOfMeasure  = $source->getUnitOfMeasure();
                }

                if ($modifierItemId &&
                    $unitOfMeasure &&
                    isset($checkAvailabilityCollection[$modifierItemId][$unitOfMeasure])
                ) {
                    $availableQty = (int)$checkAvailabilityCollection[$modifierItemId][$unitOfMeasure];
                    if ($availableQty <= 0) {
                        $value['is_available'] = false;
                    } else {
                        $value['is_available'] = true;
                    }
                }
            }
        }

        return $customOption;
    }

    /**
     * Check items availability based on store id
     *
     * @param $storeId
     * @return array|bool
     * @throws NoSuchEntityException
     */
    public function checkCatalogAvailability($storeId = null)
    {
        if ($storeId) {
            $this->lsr->setStoreId($storeId);
        }

        $webStore = $this->lsr->getActiveWebStore();
        $cacheKey = LSR::LS_HOSP_CHECK_AVAILABILITY . $storeId;

        $cachedData = $this->cacheHelper->getCachedContent($cacheKey);
        if ($cachedData) {
            return $cachedData;
        }

        $availabilityRequestArray = [];
        $responseResult           = $this->availability(
            $webStore,
            $availabilityRequestArray
        );

        $availabilityMap = [];
        if ($responseResult) {
            foreach ($responseResult as $result) {
                $itemId                         = $result['No.'];
                $uom                            = $result['Unit of Measure'];
                $qty                            = (int)$result['Available Qty.'];
                $availabilityMap[$itemId][$uom] = $qty;
            }
        }

        $this->cacheHelper->persistContentInCache(
            $cacheKey,
            $availabilityMap,
            [LSR::LS_HOSP_CHECK_AVAILABILITY],
            1800
        );

        return $availabilityMap;
    }

    /**
     * Check if modifiers are available in current availability response
     *
     * @param $customOption
     * @return boolean
     * @throws NoSuchEntityException
     */
    public function checkModifierAvailabilityForGraphQl(&$customOption)
    {
        if (!$customOption) {
            return $customOption;
        }

        $modifierItemId = "";
        $unitOfMeasure  = "";
        $itemId         = $customOption->getProduct()->getData(LSR::LS_ITEM_ID_ATTRIBUTE_CODE);
        $modifier       = current($this->hospitalityHelper->getModifierByDescription($customOption['title']));
        $source         = $modifier ?: current($this->hospitalityHelper->getDealLineByDescription($customOption['title'], $itemId));

        if ($source) {
            $modifierItemId = $modifier ? $source->getTriggerCode() : $source->getItemNo();
            $unitOfMeasure  = $source->getUnitOfMeasure();
        }

        $storeId = $this->lsr->getCurrentStoreId();
        $checkAvailabilityCollection = $this->checkCatalogAvailability($storeId);

        if ($modifierItemId &&
            $unitOfMeasure &&
            isset($checkAvailabilityCollection[$modifierItemId][$unitOfMeasure])
        ) {
            $availableQty = (int)$checkAvailabilityCollection[$modifierItemId][$unitOfMeasure];
            if ($availableQty <= 0) {
                return false;
            } else {
                return true;
            }
        }

        return true;
    }
}
