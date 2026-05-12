<?php

namespace Ls\Hospitality\Plugin\Omni\Block\Adminhtml\Sales;

use \Ls\Omni\Block\Adminhtml\Sales\Totals;
use Magento\Framework\DataObject;

class TotalsPlugin
{
    /**
     * After plugin on initTotals to add Tip amount after subtotal
     *
     * @param Totals $subject
     * @param Totals $result
     * @return Totals
     */
    public function afterInitTotals(Totals $subject, Totals $result): Totals
    {
        $order = $subject->getOrder();
        $tipAmount = (float)$order->getData('ls_tip_amount');

        if ($tipAmount > 0) {
            $tipTotal = new DataObject([
                'code'       => 'ls_tip_amount',
                'value'      => $tipAmount,
                'base_value' => $tipAmount,
                'label'      => __('Tip'),
            ]);

            $subject->getParentBlock()->addTotal($tipTotal, 'subtotal');
        }

        return $result;
    }
}
