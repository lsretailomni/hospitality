<?php

namespace Ls\Hospitality\Model\Order;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Helper\Data;
use \Ls\Omni\Helper\ItemHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;

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
     */
    public function __construct(
        public ProductRepositoryInterface $productRepository,
        public LSR $lsr,
        public ItemHelper $itemHelper,
        public HospitalityHelper $hospitalityHelper,
        public CheckoutSession $checkoutSession,
        public LoggerInterface $logger,
        public Data $dataHelper
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
                $items = [$item];
                $itemsCount = 1;
            } else {
                $itemsCount = $this->checkoutSession->getQuote()->getItemsCount();
                $items = $this->checkoutSession->getQuote()->getAllVisibleItems();
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
            $itemIds = array_unique(array_merge($itemIds, array_keys($checkAvailabilityCollection)));
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
        $options = $this->hospitalityHelper->getCustomOptionsFromQuoteItem($item);
        if ($options) {
            foreach ($options as $option) {
                if (isset($option['ls_modifier_recipe_id'])
                    && $option['ls_modifier_recipe_id'] != LSR::LSR_RECIPE_PREFIX) {
                    $qty = 1;
                    $modifier = current($this->hospitalityHelper->getModifierByDescription($option['value']));
                    if (!$modifier) {
                        return;
                    }
                    $modifierItemId = (string)$modifier->getItemNo();
                    $code = $modifier->getInfocodeCode();
                    $unitOfMeasure = $modifier->getUnitOfMeasure();

                    if (in_array($modifierItemId, $checkAvailabilityCollection)) {
                        $qty = $checkAvailabilityCollection[$modifierItemId]['qty'] + $qty;
                    }

                    $checkAvailabilityCollection[$modifierItemId] = [
                        'item_id' => $modifierItemId,
                        'name' => $modifier->getDescription(),
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

                        throw new ValidatorException(__($message));
                    }
                }
            }
        }
    }
}
