<?php

namespace Ls\Hospitality\Plugin\Omni\Email\Sender;

use Magento\Sales\Model\Order;
use \Ls\Hospitality\Model\LSR;

/**
 * Class OrderSender
 */
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
     * @param $subject
     * @param $proceed
     * @param Order $order
     * @param $forceSyncMode
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
        }
        return $result;
    }
}
