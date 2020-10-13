<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use Exception;
use \Ls\Core\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity\OrderHosp;
use Psr\Log\LoggerInterface;

/**
 * ItemHelper Plugin
 */
class ItemHelper
{
    /**
     * @var LoggerInterface
     */
    public $logger;

    /** @var  LSR $lsr */
    public $lsr;

    /**
     * ItemHelper constructor.
     * @param LoggerInterface $logger
     * @param LSR $Lsr
     */
    public function __construct(
        LoggerInterface $logger,
        LSR $Lsr
    ) {
        $this->logger = $logger;
        $this->lsr    = $Lsr;
    }

    /**
     * @param \Ls\Omni\Helper\ItemHelper $subject
     * @param callable $proceed
     * @param $quote
     * @param $basketData
     * @return mixed
     */
    public function aroundSetDiscountedPricesForItems(\Ls\Omni\Helper\ItemHelper $subject, callable $proceed, $quote, $basketData)
    {
        try {
            if ($this->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
                return $proceed($quote, $basketData);
            }
            $itemList = $subject->cart->getQuote()->getAllVisibleItems();
            foreach ($itemList as $item) {
                $orderLines     = $basketData->getOrderLines()->getOrderHospLine();
                $oldItemVariant = [];
                $itemSku        = explode("-", $item->getSku());
                // @codingStandardsIgnoreLine
                if (count($itemSku) < 2) {
                    $itemSku[1] = null;
                }
                if (is_array($orderLines)) {
                    foreach ($orderLines as $line) {
                        if ($itemSku[0] == $line->getItemId() && $itemSku[1] == $line->getVariantId()) {
                            $unitPrice = $line->getAmount() / $line->getQuantity();
                            if (!empty($oldItemVariant[$line->getItemId()][$line->getVariantId()]['Amount'])) {
                                // @codingStandardsIgnoreLine
                                $item->setCustomPrice($oldItemVariant[$line->getItemId()][$line->getVariantId()]['Amount'] + $line->getAmount());
                                $item->setDiscountAmount(
                                // @codingStandardsIgnoreLine
                                    $oldItemVariant[$line->getItemId()][$line->getVariantId()]['Discount'] + $line->getDiscountAmount()
                                );
                                $item->setOriginalCustomPrice($line->getPrice());
                            } else {
                                if ($line->getDiscountAmount() > 0) {
                                    $item->setCustomPrice($unitPrice);
                                    $item->setDiscountAmount($line->getDiscountAmount());
                                    $item->setOriginalCustomPrice($line->getPrice());
                                } elseif ($line->getAmount() != $item->getProduct()->getPrice()) {
                                    $item->setCustomPrice($unitPrice);
                                    $item->setOriginalCustomPrice($line->getPrice());
                                } else {
                                    $item->setCustomPrice(null);
                                    $item->setDiscountAmount(null);
                                    $item->setOriginalCustomPrice(null);
                                }
                            }
                        }
                        // @codingStandardsIgnoreStart
                        if (!empty($oldItemVariant[$line->getItemId()][$line->getVariantId()]['Amount'])) {
                            $oldItemVariant[$line->getItemId()][$line->getVariantId()]['Amount']    =
                                $oldItemVariant[$line->getItemId()][$line->getVariantId()]['Amount'] + $line->getAmount();
                            $oldItemVariant[$line->getItemId()][$line->getVariantId()] ['Discount'] =
                                $oldItemVariant[$line->getItemId()][$line->getVariantId()]['Discount'] + $line->getDiscountAmount();
                        } else {
                            $oldItemVariant[$line->getItemId()][$line->getVariantId()]['Amount']   = $line->getAmount();
                            $oldItemVariant[$line->getItemId()][$line->getVariantId()]['Discount'] = $line->getDiscountAmount();
                        }
                        // @codingStandardsIgnoreEnd
                    }
                } else {
                    if ($orderLines->getDiscountAmount() > 0) {
                        $item->setCustomPrice($orderLines->getAmount());
                        $item->setDiscountAmount($orderLines->getDiscountAmount());
                        $item->setOriginalCustomPrice($orderLines->getPrice());
                    } else {
                        $item->setCustomPrice(null);
                        $item->setDiscountAmount(null);
                        $item->setOriginalCustomPrice(null);
                    }
                }
                $item->getProduct()->setIsSuperMode(true);
                $item->calcRowTotal();
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
     * @param \Ls\Omni\Helper\ItemHelper $subject
     * @param callable $proceed
     * @param $item
     * @param $orderData
     * @param int $type
     * @return array|null
     */
    public function aroundGetOrderDiscountLinesForItem(\Ls\Omni\Helper\ItemHelper $subject, callable $proceed, $item, $orderData, $type = 1)
    {
        try {
            if ($this->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
                return $proceed($item, $orderData, $type);
            }
            $discountInfo = [];
            $customPrice  = 0;
            if ($type == 2) {
                $itemSku = $item->getItemId();
                $itemSku = explode("-", $itemSku);
                if (count($itemSku) < 2) {
                    $itemSku[1] = $item->getVariantId();
                }
                $customPrice = $item->getDiscountAmount();
            } else {
                $itemSku = $item->getSku();
                $itemSku = explode("-", $itemSku);
                if (count($itemSku) < 2) {
                    $itemSku[1] = '';
                }
                $customPrice = $item->getCustomPrice();
            }
            $check        = false;
            $basketData   = [];
            $discountText = __("Save");
            if ($orderData instanceof OrderHosp) {
                $basketData     = $orderData->getOrderLines();
            }
            foreach ($basketData as $basket) {
                if ($basket->getItemId() == $itemSku[0] && $basket->getVariantId() == $itemSku[1]) {
                    if ($customPrice > 0 && $customPrice != null) {
                        $discountsLines = $basket->getDiscountLines();
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
