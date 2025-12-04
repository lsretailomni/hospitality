<?php

namespace Ls\Hospitality\Plugin\Omni\Email\Sender;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Invoice;
use \Ls\Hospitality\Model\LSR;

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
     * Around plugin for invoice email sender to replace increment id with ls order id for hospitality stores
     *
     * @param $subject
     * @param $proceed
     * @param Invoice $invoice
     * @param $forceSyncMode
     * @return mixed
     * @throws NoSuchEntityException
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
        } else {
            $result = $proceed($invoice, $forceSyncMode);
        }

        return $result;
    }
}
