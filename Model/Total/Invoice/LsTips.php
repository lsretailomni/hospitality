<?php

declare(strict_types=1);

namespace Ls\Hospitality\Model\Total\Invoice;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

/**
 * Adds the hospitality tip amount to the invoice grand total.
 *
 */
class LsTips extends AbstractTotal
{
    /**
     * Add the tip to the invoice grand total, on the invoice that completes the order.
     *
     * @param Invoice $invoice
     * @return $this
     */
    public function collect(Invoice $invoice)
    {
        parent::collect($invoice);

        $tipAmount = (float)$invoice->getOrder()->getLsTipAmount();

        if ($tipAmount <= 0 || !$invoice->isLast()) {
            return $this;
        }

        $invoice->setGrandTotal((float)$invoice->getGrandTotal() + $tipAmount);
        $invoice->setBaseGrandTotal((float)$invoice->getBaseGrandTotal() + $tipAmount);

        return $this;
    }
}
