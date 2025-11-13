<?php

namespace Ls\Hospitality\Plugin\Omni\Email\Sender;

use Magento\Sales\Model\Order\Invoice;
use \Ls\Hospitality\Model\LSR;

/**
 * Class InvoiceSender
 */
class InvoiceSender
{
    /**
     * @var LSR
     */
    private $lsr;

    /**
     * @param LSR $lsr
     */
    public function __construct(
        LSR $lsr
    ) {
        $this->lsr = $lsr;
    }

    /**
     * @param $subject
     * @param $proceed
     * @param Invoice $invoice
     * @param $forceSyncMode
     * @return mixed
     */
    public function aroundSend($subject, $proceed, Invoice $invoice, $forceSyncMode = false)
    {
        if ($this->lsr->isHospitalityStore($invoice->getOrder()->getStoreId())) {
            $incrementId = $invoice->getOrder()->getIncrementId();
            if (!empty($invoice->getOrder()->getLsOrderId())) {
                $invoice->getOrder()->setIncrementId($invoice->getOrder()->getLsOrderId());
            }
            $result = $proceed($invoice, $forceSyncMode);
            $invoice->getOrder()->setIncrementId($incrementId);
        }
        return $result;
    }
}
