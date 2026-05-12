<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Omni\Helper;

use \Ls\Core\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
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
}
