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
     * Around plugin for setting discount prices for items
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

            $itemlist = $subject->cart->getQuote()->getAllVisibleItems();

            foreach ($itemlist as $item) {
                $orderLines        = $basketData->getOrderLines()->getOrderHospLine();
                $oldItemVariant    = [];
                $itemSku           = explode("-", $item->getSku());
                $baseUnitOfMeasure = $item->getProduct()->getData('uom');
                $uom               = $subject->getUom($itemSku, $baseUnitOfMeasure);

                if (is_array($orderLines)) {

                    foreach ($orderLines as $index => $line) {
                        ++$index;

                        if ($itemSku[0] == $line->getItemId() &&
                            $itemSku[1] == $line->getVariantId() &&
                            $uom == $line->getUomId() &&
                            $this->hospitalityHelper->isSameAsSelectedLine($line, $item, $index)
                        ) {
                            $unitPrice = $this->hospitalityHelper->getAmountGivenLine($line) / $line->getQuantity();

                            if (!empty($oldItemVariant[$line->getItemId()][$line->getVariantId()][$line->getUomId()]['Amount'])) {
                                // @codingStandardsIgnoreLine
                                $item->setCustomPrice($oldItemVariant[$line->getItemId()][$line->getVariantId()][$line->getUomId()] ['Amount'] + $this->hospitalityHelper->getAmountGivenLine($line));
                                $item->setDiscountAmount(
                                // @codingStandardsIgnoreLine
                                    $oldItemVariant[$line->getItemId()][$line->getVariantId()][$line->getUomId()]['Discount'] + $line->getDiscountAmount()
                                );
                                $item->setOriginalCustomPrice($this->hospitalityHelper->getPriceGivenLine($line));
                            } else {
                                if ($line->getDiscountAmount() > 0) {
                                    $item->setCustomPrice($unitPrice);
                                    $item->setDiscountAmount($line->getDiscountAmount());
                                    $item->setOriginalCustomPrice($this->hospitalityHelper->getPriceGivenLine($line));
                                } elseif ($this->hospitalityHelper->getAmountGivenLine($line) != $item->getProduct()->getPrice()) {
                                    $item->setCustomPrice($unitPrice);
                                    $item->setOriginalCustomPrice($this->hospitalityHelper->getPriceGivenLine($line));
                                } else {
                                    $item->setCustomPrice(null);
                                    $item->setDiscountAmount(null);
                                    $item->setOriginalCustomPrice(null);
                                }
                            }
                            $item->setTaxAmount($line->getTaxAmount())
                                ->setBaseTaxAmount($line->getTaxAmount())
                                ->setPriceInclTax($unitPrice)
                                ->setBasePriceInclTax($unitPrice)
                                ->setRowTotal($line->getNetAmount())
                                ->setBaseRowTotal($line->getNetAmount())
                                ->setRowTotalInclTax($line->getAmount())
                                ->setBaseRowTotalInclTax($line->getAmount());
                        }

                        // @codingStandardsIgnoreStart
                        if (!empty($oldItemVariant[$line->getItemId()][$line->getVariantId()][$line->getUomId()]['Amount'])) {
                            $oldItemVariant[$line->getItemId()][$line->getVariantId()] [$line->getUomId()]['Amount']    =
                                $oldItemVariant[$line->getItemId()][$line->getVariantId()] [$line->getUomId()]['Amount'] + $this->hospitalityHelper->getAmountGivenLine($line);
                            $oldItemVariant[$line->getItemId()][$line->getVariantId()] [$line->getUomId()] ['Discount'] =
                                $oldItemVariant[$line->getItemId()][$line->getVariantId()] [$line->getUomId()] ['Discount'] + $line->getDiscountAmount();
                        } else {
                            $oldItemVariant[$line->getItemId()][$line->getVariantId()] [$line->getUomId()]['Amount']   = $this->hospitalityHelper->getAmountGivenLine($line);
                            $oldItemVariant[$line->getItemId()][$line->getVariantId()] [$line->getUomId()]['Discount'] = $line->getDiscountAmount();
                        }
                        // @codingStandardsIgnoreEnd
                    }
                } else {
                    if ($orderLines->getDiscountAmount() > 0) {
                        $item->setCustomPrice($this->hospitalityHelper->getAmountGivenLine($orderLines));
                        $item->setDiscountAmount($orderLines->getDiscountAmount());
                        $item->setOriginalCustomPrice($this->hospitalityHelper->getPriceGivenLine($orderLines));
                    } else {
                        $item->setCustomPrice(null);
                        $item->setDiscountAmount(null);
                        $item->setOriginalCustomPrice(null);
                    }
                }
                $item->getProduct()->setIsSuperMode(true);
                // @codingStandardsIgnoreLine
                $subject->itemResourceModel->save($item);
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
                $cartQuote->collectTotals();
                $subject->quoteResourceModel->save($cartQuote);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Around plugin for getting the orderDiscountLines for item
     *
     * @param ItemHelper $subject
     * @param callable $proceed
     * @param $item
     * @param $orderData
     * @param int $type
     * @return array|null
     */
    public function aroundGetOrderDiscountLinesForItem(ItemHelper $subject, callable $proceed, $item, $orderData, $type = 1)
    {
        try {
            if ($this->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
                return $proceed($item, $orderData, $type);
            }

            $discountInfo = [];
            $customPrice  = 0;
            $uom  = '';

            if ($type == 2) {
                $itemSku = $item->getItemId();
                $itemSku = explode("-", $itemSku);

                if (count($itemSku) < 2) {
                    $itemSku[1] = $item->getVariantId();
                }

                $uom = $item->getUomId();
                $customPrice = $item->getDiscountAmount();
            } else {
                $itemSku = $item->getSku();
                $itemSku = explode("-", $itemSku);
                if (count($itemSku) < 2) {
                    $itemSku[1] = '';
                }
                // @codingStandardsIgnoreLine
                $customPrice = $item->getCustomPrice();
                $baseUnitOfMeasure = $item->getProduct()->getData('uom');
                $uom               = $subject->getUom($itemSku, $baseUnitOfMeasure);
            }

            $check        = false;
            $basketData   = [];
            $discountText = __("Save");

            if ($orderData instanceof SalesEntry) {
                $basketData     = $orderData->getLines();
                $discountsLines = $orderData->getDiscountLines();
            } elseif ($orderData instanceof OrderHosp) {
                $basketData     = $orderData->getOrderLines();
                $discountsLines = $orderData->getOrderDiscountLines()->getOrderDiscountLine();
            }

            foreach ($basketData as $basket) {
                if ($basket->getItemId() == $itemSku[0] && $basket->getVariantId() == $itemSku[1] && $uom == $basket->getUomId()) {

                    if ($customPrice > 0 && $customPrice != null) {
                        // @codingStandardsIgnoreLine
                        foreach ($discountsLines as $orderDiscountLine) {
                            if ($basket->getLineNumber() == $orderDiscountLine->getLineNumber()) {
                                if (!in_array($orderDiscountLine->getDescription() . '<br />', $discountInfo)) {
                                    $discountInfo[] = $orderDiscountLine->getDescription() . '<br />';
                                }
                            }
                            $check = true;
                        }
                    }
                }
            }

            if ($check == true) {
                return [implode($discountInfo), $discountText];
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
