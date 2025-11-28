<?php

namespace Ls\Hospitality\Plugin\Omni\Email\Sender;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use \Ls\Hospitality\Model\LSR;

class OrderSender
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
     * Around plugin for order email sender to replace increment id with ls order id for hospitality stores
     *
     * @param $subject
     * @param $proceed
     * @param Order $order
     * @param $forceSyncMode
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function aroundSend($subject, $proceed, Order $order, $forceSyncMode = false)
    {
        if ($this->lsr->isHospitalityStore($order->getStoreId())) {
            $incrementId = $order->getIncrementId();
            if (!empty($order->getLsOrderId())) {
                $order->setIncrementId($order->getLsOrderId());
            }
            $result = $proceed($order, $forceSyncMode);
            $order->setIncrementId($incrementId);
        } else {
            $result = $proceed($order, $forceSyncMode);
        }

        return $result;
    }
}
