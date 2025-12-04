<?php

namespace Ls\Hospitality\Plugin\Omni\Email\Sender;

use Ls\Hospitality\Helper\HospitalityHelper;
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
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @param LSR $lsr
     */
    public function __construct(
        LSR $lsr,
        HospitalityHelper $hospitalityHelper,
    ) {
        $this->lsr = $lsr;
        $this->hospitalityHelper = $hospitalityHelper;
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
            } else {
                //Block sending order confirmation email if order does not have Q counter no
                return false;
            }
            $result = $proceed($order, $forceSyncMode);
            $order->setIncrementId($incrementId);
        }
        return $result;
    }
}
