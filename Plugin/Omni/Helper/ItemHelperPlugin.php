<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use Exception;
use \Ls\Core\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Client\Ecommerce\Entity\GetSelectedSalesDoc_GetSelectedSalesDoc;
use \Ls\Omni\Helper\ItemHelper;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * ItemHelper plugin responsible for intercepting required methods
 */
class ItemHelperPlugin
{
    /**
     * @param LoggerInterface $logger
     * @param LSR $lsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        public LoggerInterface $logger,
        public LSR $lsr,
        public HospitalityHelper $hospitalityHelper
    ) {
    }

    /**
     * Compare one_list lines with quote_item items and set correct prices
     *
     * @param ItemHelper $subject
     * @param callable $proceed
     * @param $quote
     * @param $basketData
     * @param int $type
     * @return mixed
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException|LocalizedException
     */
    public function aroundCompareQuoteItemsWithOrderLinesAndSetRelatedAmounts(
        ItemHelper $subject,
        callable $proceed,
        &$quote,
        $basketData,
        $type = 1
    ) {
        if ($this->lsr->getCurrentIndustry($quote->getStore()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($quote, $basketData, $type);
        }

        $orderLines = [];
        $quoteItemList = $quote->getAllVisibleItems();

        if (count($quoteItemList) && !empty($basketData)) {
            $orderLines = $basketData->getMobiletransactionline();
        }

        foreach ($quoteItemList as $quoteItem) {
            $bundleProduct = $customPrice = $taxAmount = $rowTotal = $rowTotalIncTax = $priceInclTax = 0;
            $children = [];

            if ($quoteItem->getProductType() == Type::TYPE_BUNDLE) {
                $children = $quoteItem->getChildren();
                $bundleProduct = 1;
            } else {
                $children[] = $quoteItem;
            }

            foreach ($children as $child) {
                foreach ($orderLines as $index => $line) {
                    if (is_numeric($line->getId()) ?
                        $child->getItemId() == $line->getId() :
                        $subject->isSameItem($child, $line)
                    ) {
                        $totalUnitPrice = $this->hospitalityHelper->getAmountGivenLine(
                            $line,
                            $basketData->getMobiletransactionsubline() ?? []
                        );
                        $unitPrice = $totalUnitPrice / $line->getQuantity();
                        $subject->setRelatedAmountsAgainstGivenQuoteItem($line, $child, $unitPrice, $type);
                        unset($orderLines[$index]);
                        break;
                    }
                }
                $child->getProduct()->setIsSuperMode(true);
                try {
                    // @codingStandardsIgnoreLine
                    $subject->itemResourceModel->save($child);
                } catch (LocalizedException $e) {
                    $this->logger->critical("Error saving SKU:-" . $child->getSku() . " - " . $e->getMessage());
                }

                $customPrice += $child->getCustomPrice();
                $priceInclTax += $child->getPriceInclTax();
                $taxAmount += $child->getTaxAmount();
                $rowTotal += $child->getRowTotal();
                $rowTotalIncTax += $child->getRowTotalInclTax();
            }

            if ($bundleProduct == 1) {
                $quoteItem->setCustomPrice($customPrice);
                $quoteItem->setRowTotal($rowTotal);
                $quoteItem->setRowTotalInclTax($rowTotalIncTax);
                $quoteItem->setTaxAmount($taxAmount);
                $quoteItem->setPriceInclTax($priceInclTax);
                $quoteItem->getProduct()->setIsSuperMode(true);
                try {
                    // @codingStandardsIgnoreLine
                    $subject->itemResourceModel->save($quoteItem);
                } catch (LocalizedException $e) {
                    $this->logger->critical(
                        "Error saving Quote Item:-" . $quoteItem->getSku() . " - " . $e->getMessage()
                    );
                }
            }
        }
    }

    /**
     * Around plugin for comparing orderLines with discountLines and get discounted prices on cart page
     * or order detail page
     *
     * @param ItemHelper $subject
     * @param callable $proceed
     * @param object $item
     * @param object $orderData
     * @param int $type
     * @param int $graphQlRequest
     * @return array|null
     */
    public function aroundGetOrderDiscountLinesForItem(
        ItemHelper $subject,
        callable $proceed,
        $item,
        $orderData,
        $type = 1,
        $graphQlRequest = 0
    ) {
        $check             = false;
        $baseUnitOfMeasure = "";
        $discountInfo      = $orderLines = $discountsLines = [];
        $discountText      = __("Save");

        try {
            if ($this->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
                return $proceed($item, $orderData, $type, $graphQlRequest);
            }

            if ($type == 2) {
                $itemId      = $item->getNumber();
                $variantId   = $item->getVariantCode();
                $uom         = $item->getUnitOfMeasure();
                $customPrice = $item->getDiscountAmount();
            } else {
                $baseUnitOfMeasure = $item->getProduct()->getData('uom');
                list($itemId, $variantId, $uom) = $subject->getComparisonValues(
                    $item->getSku()
                );
                $customPrice = $item->getCustomPrice();
            }
            
            if ($orderData instanceof GetSelectedSalesDoc_GetSelectedSalesDoc) {
                $orderLines     = $orderData->getLscMemberSalesDocLine();
                if (!empty($orderData->getLscMemberSalesDocDiscLine())) {
                    $discountsLines = $orderData->getLscMemberSalesDocDiscLine();
                }
            }
            //Need to remove after testing different scenarios
//            if ($orderData instanceof SalesEntry) {
//                $orderLines     = $orderData->getLines();
//                $discountsLines = $orderData->getDiscountLines();
//            } elseif ($orderData instanceof OrderHosp) {
//                $orderLines = $orderData->getOrderLines();
//
//                if (!empty($orderData->getOrderDiscountLines())) {
//                    $discountsLines = $orderData->getOrderDiscountLines()->getOrderDiscountLine();
//                }
//            }

            foreach ($orderLines as $line) {
                if ($subject->isValid($item, $line, $itemId, $variantId, $uom, $baseUnitOfMeasure)) {
                    if ($customPrice > 0 && $customPrice != null) {
                        foreach ($discountsLines as $orderDiscountLine) {
                            if ($line->getLineNo() == $orderDiscountLine->getDocumentLineNo()) {
                                if (!in_array($orderDiscountLine->getDescription() . '<br />', $discountInfo)) {
                                    if (!$graphQlRequest) {
                                        $discountInfo[] = $orderDiscountLine->getDescription() . '<br />';
                                    } else {
                                        $discountInfo[] = [
                                            'description' => $orderDiscountLine->getDescription(),
                                            'value'       => $orderDiscountLine->getDiscountAmount()
                                        ];
                                    }
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

        if ($check) {
            if (!$graphQlRequest) {
                return [implode($discountInfo), $discountText];
            }
            return [$discountInfo, $discountText];
        } else {
            return null;
        }
    }
}
