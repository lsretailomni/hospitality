<?php

namespace Ls\Hospitality\Plugin\Customer\ViewModel;

use \Ls\Customer\ViewModel\ItemRenderer;
use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Format items for hospitality
 */
class ItemRendererPlugin
{
    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @param LSR $lsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        LSR $lsr,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->lsr = $lsr;
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * Get matched line and discount info
     *
     * @param ItemRenderer $subject
     * @param callable $proceed
     * @param $orderItem
     * @return array
     * @throws NoSuchEntityException
     */
    public function aroundGetDiscountInfoGivenOrderItem(
        ItemRenderer $subject,
        callable $proceed,
        $orderItem
    ) {
        if (!$this->lsr->isHospitalityStore()) {
            return $proceed($orderItem);
        }
        $discount = [];
        $line = null;
        $currentOrder = $subject->getOrder();

        if ($currentOrder) {
            if (empty($this->lines)) {
                $subject->lines = $currentOrder->getLscMemberSalesDocLine();
            }
            list($itemId, $variantId, $uom) = $subject->itemHelper->getComparisonValues(
                $orderItem->getSku()
            );
            $baseUnitOfMeasure = $orderItem->getProduct()->getData('uom');

            foreach ($subject->lines as $index => $line) {
                if ($subject->itemHelper->isValid($orderItem, $line, $itemId, $variantId, $uom, $baseUnitOfMeasure)) {
                    $discount = $subject->itemHelper->getOrderDiscountLinesForItem($line, $currentOrder, 2);
                    $options = $orderItem->getProductOptions();
                    $optionsCheck = ($options) ? isset($options['options']) : null;

                    if ($optionsCheck) {
                        $lineNo = $line->getLineNo();

                        if ($orderItem->getProduct()->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE)) {
                            $mainDealLine = current($this->hospitalityHelper->getMainDealLine($itemId));
                            $mainDealItemId = $mainDealLine ? $mainDealLine->getNo() : null;

                            if ($mainDealItemId) {
                                foreach ($subject->lines as $orderLine) {
                                    if ($mainDealItemId == $orderLine->getNumber() &&
                                        $lineNo == $orderLine->getParentLine()
                                    ) {
                                        $lineNo = $orderLine->getLineNo();
                                        break;
                                    }
                                }
                            }
                        }

                        foreach ($subject->lines as $orderLine) {
                            if ($orderLine->getParentLine() != 0 &&
                                $orderLine->getParentLine() !== $orderLine->getLineNo() &&
                                $lineNo == $orderLine->getParentLine()
                            ) {
                                $line->setAmount($line->getAmount() + $orderLine->getAmount());
                            }
                        }
                    }

                    unset($subject->lines[$index]);
                    break;
                } else {
                    $line = null;
                }
            }
        }

        return [$discount, $line];
    }
}
