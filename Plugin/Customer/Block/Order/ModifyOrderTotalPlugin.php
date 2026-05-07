<?php

namespace Ls\Hospitality\Plugin\Customer\Block\Order;

use \Ls\Customer\Block\Order\Totals;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Add extra to order totals block in customer order detail
 */
class ModifyOrderTotalPlugin
{
    /**
     * Modify the output of the Totals block
     *
     * @param Totals $subject
     * @param string $result
     * @return string
     * @throws NoSuchEntityException
     */
    public function afterToHtml(Totals $subject, $result)
    {
        $orderLines = $subject->getLines();
        $fee        = 0.0;

        // First: try to get tip from LS Central sales entry lines via TIPS_ITEM_ID
        foreach ($orderLines as $key => $line) {
            if ($line->getItemId() == $subject->lsr->getStoreConfig(
                    LSR::TIPS_ITEM_ID,
                    $subject->lsr->getCurrentStoreId()
                )) {
                $fee = $line->getAmount();
                break;
            }
        }

        if (!$fee) {
            $magOrder = $subject->getMagOrder();
            if ($magOrder) {
                $fee = (float)$magOrder->getData('ls_tip_amount');
            }
        }

        if ($fee) {
            $tipHtml              = '<tr class="tip">
            <th colspan="4" class="mark" scope="row">
                ' . __('Tip') . '
            </th>
            <td class="amount" data-th="' . __('Tip') . '">
                <span class="price">
                    ' . $subject->escapeHtml($subject->getFormattedPrice($fee)) . '
                </span>
            </td>
        </tr>';
            $subtotalHtmlPosition = strpos($result, 'class="subtotal"');

            if ($subtotalHtmlPosition !== false) {
                $endOfSubtotalRow = strpos($result, '</tr>', $subtotalHtmlPosition) + 5;
                $result           = substr($result, 0, $endOfSubtotalRow) . $tipHtml . substr($result,
                        $endOfSubtotalRow);
            }
            $result = str_replace(
                (string)__('Subtotal (Inc.Tax)'),
                (string)__('Subtotal (Inc.Tax &amp; Tip)'),
                $result
            );
        }

        return $result;
    }
}
