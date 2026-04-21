<?php

namespace Ls\Hospitality\Plugin\Omni\Email\Sender;

use Magento\Sales\Model\Order\Shipment;
use \Ls\Hospitality\Model\LSR;

/**
 * Class ShipmentSender
 */
class ShipmentSender
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
     * @param Shipment $shipment
     * @param false $forceSyncMode
     * @return array
     */
    public function beforeSend($subject, Shipment $shipment, $forceSyncMode = false)
    {
        if ($this->lsr->isHospitalityStore($shipment->getOrder()->getStoreId())) {
            if (!empty($shipment->getOrder()->getLsOrderId())) {
                $shipment->getOrder()->setIncrementId($shipment->getOrder()->getLsOrderId());
            }
        }
        return [$shipment, $forceSyncMode];
    }
}
