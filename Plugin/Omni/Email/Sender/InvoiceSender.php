<?php

namespace Ls\Hospitality\Plugin\Omni\Email\Sender;

use Magento\Sales\Model\Order\Invoice;

/**
 * Class InvoiceSender
 */
class InvoiceSender
{
    /**
     * @param $subject
     * @param $proceed
     * @param Invoice $invoice
     * @param $forceSyncMode
     * @return mixed
     */
    public function aroundSend($subject, $proceed, Invoice $invoice, $forceSyncMode = false)
    {
        $incrementId = $invoice->getOrder()->getIncrementId();
        if (!empty($invoice->getOrder()->getLsOrderId())) {
            $invoice->getOrder()->setIncrementId($invoice->getOrder()->getLsOrderId());
        }
        $result = $proceed($invoice, $forceSyncMode);
        $invoice->getOrder()->setIncrementId($incrementId);
        return $result;
    }
}
