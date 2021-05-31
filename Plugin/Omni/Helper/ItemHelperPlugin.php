<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use Exception;
use \Ls\Core\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Client\Ecommerce\Entity\OrderHosp;
use \Ls\Omni\Client\Ecommerce\Entity\SalesEntry;
use \Ls\Omni\Helper\ItemHelper;
use Psr\Log\LoggerInterface;

/**
 * ItemHelper plugin responsible for intercepting required methods
 */
class ItemHelperPlugin
{
    /**
     * @var LoggerInterface
     */
    public $logger;

    /** @var  LSR $lsr */
    public $lsr;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * ItemHelper constructor.
     * @param LoggerInterface $logger
     * @param LSR $Lsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        LoggerInterface $logger,
        LSR $Lsr,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->logger            = $logger;
        $this->lsr               = $Lsr;
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * Around plugin for Comparing one_list lines with quote_item items and set correct prices
     *
     * @param ItemHelper $subject
     * @param callable $proceed
     * @param $quote
     * @param $basketData
     * @return mixed
     */
    public function aroundSetDiscountedPricesForItems(
        ItemHelper $subject,
        callable $proceed,
        $quote,
        $basketData
    ) {
        try {
            if ($this->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
                return $proceed($quote, $basketData);
            }

            $orderLines    = [];
            $quoteItemList = $subject->cart->getQuote()->getAllVisibleItems();

            if (count($quoteItemList)) {
                $orderLines = $basketData->getOrderLines()->getOrderHospLine();
            }

            foreach ($quoteItemList as $quoteItem) {
                $baseUnitOfMeasure = $quoteItem->getProduct()->getData('uom');
                list($itemId, $variantId, $uom) = $subject->getComparisonValues($quoteItem);

                foreach ($orderLines as $index => $line) {
                    if ($subject->isValid($line, $itemId, $variantId, $uom, $baseUnitOfMeasure)) {
                        $unitPrice = $line->getAmount() / $line->getQuantity();
                        if ($line->getDiscountAmount() > 0) {
                            $quoteItem->setCustomPrice($unitPrice);
                            $quoteItem->setDiscountAmount($line->getDiscountAmount());
                            $quoteItem->setOriginalCustomPrice($unitPrice);
                        } elseif ($line->getAmount() != $quoteItem->getProduct()->getPrice()) {
                            $quoteItem->setCustomPrice($unitPrice);
                            $quoteItem->setOriginalCustomPrice($unitPrice);
                        } else {
                            $quoteItem->setCustomPrice(null);
                            $quoteItem->setDiscountAmount(null);
                            $quoteItem->setOriginalCustomPrice(null);
                        }
                        $quoteItem->setTaxAmount($line->getTaxAmount())
                            ->setBaseTaxAmount($line->getTaxAmount())
                            ->setPriceInclTax($unitPrice)
                            ->setBasePriceInclTax($unitPrice)
                            ->setRowTotal($line->getNetAmount())
                            ->setBaseRowTotal($line->getNetAmount())
                            ->setRowTotalInclTax($line->getAmount())
                            ->setBaseRowTotalInclTax($line->getAmount());
                        unset($orderLines[$index]);
                        break;
                    }
                }
                $quoteItem->getProduct()->setIsSuperMode(true);
                // @codingStandardsIgnoreLine
                $subject->itemResourceModel->save($quoteItem);
            }

            if ($quote->getId()) {
                $cartQuote = $subject->cart->getQuote();

                if (isset($basketData)) {
                    $pointDiscount  = $cartQuote->getLsPointsSpent() * $subject->loyaltyHelper->getPointRate();
                    $giftCardAmount = $cartQuote->getLsGiftCardAmountUsed();
                    $cartQuote->getShippingAddress()->setGrandTotal(
                        $basketData->getTotalAmount() - $giftCardAmount - $pointDiscount
                    );
                }
                $couponCode = $subject->checkoutSession->getCouponCode();
                $cartQuote->setCouponCode($couponCode);
                $cartQuote->getShippingAddress()->setCouponCode($couponCode);
                $cartQuote->setTotalsCollectedFlag(false)->collectTotals();
                $subject->quoteResourceModel->save($cartQuote);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Around plugin for comparing orderLines with discountLines and get discounted prices on cart page
     * or order detail page
     *
     * @param ItemHelper $subject
     * @param callable $proceed
     * @param $item
     * @param $orderData
     * @param int $type
     * @return array|null
     */
    public function aroundGetOrderDiscountLinesForItem(
        ItemHelper $subject,
        callable $proceed,
        $item,
        $orderData,
        $type = 1
    ) {
        $check             = false;
        $baseUnitOfMeasure = "";
        $discountInfo      = $orderLines = $discountsLines = [];
        $discountText      = __("Save");

        try {
            if ($this->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
                return $proceed($item, $orderData, $type);
            }

            if ($type == 2) {
                $itemId      = $item->getItemId();
                $variantId   = $item->getVariantId();
                $uom         = $item->getUomId();
                $customPrice = $item->getDiscountAmount();
            } else {
                $baseUnitOfMeasure = $item->getProduct()->getData('uom');
                list($itemId, $variantId, $uom) = $subject->getComparisonValues($item);
                $customPrice = $item->getCustomPrice();
            }

            if ($orderData instanceof SalesEntry) {
                $orderLines     = $orderData->getLines();
                $discountsLines = $orderData->getDiscountLines();
            } elseif ($orderData instanceof OrderHosp) {
                $orderLines     = $orderData->getOrderLines();

                if ($orderData->getOrderDiscountLines() && !empty($orderData->getOrderDiscountLines())) {
                    $discountsLines = $orderData->getOrderDiscountLines()->getOrderDiscountLine();
                } else {
                    $discountsLines = [];
                }
            }

            foreach ($orderLines as $line) {
                if ($subject->isValid($line, $itemId, $variantId, $uom, $baseUnitOfMeasure)) {
                    if ($customPrice > 0 && $customPrice != null) {
                        foreach ($discountsLines as $orderDiscountLine) {
                            if ($line->getLineNumber() == $orderDiscountLine->getLineNumber()) {
                                if (!in_array($orderDiscountLine->getDescription() . '<br />', $discountInfo)) {
                                    $discountInfo[] = $orderDiscountLine->getDescription() . '<br />';
                                }
                            }
                            $check = true;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        if ($check == true) {
            return [implode($discountInfo), $discountText];
        } else {
            return null;
        }
    }
}
