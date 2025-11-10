<?php

namespace Ls\Hospitality\Plugin\Omni\Email\Sender;

use Magento\Sales\Model\Order\Shipment;

/**
 * Class ShipmentSender
 */
class ShipmentSender
{

    /**
     * @param $subject
     * @param Shipment $shipment
     * @param false $forceSyncMode
     * @return array
     */
    public function beforeSend($subject, Shipment $shipment, $forceSyncMode = false)
    {
        if (!empty($shipment->getOrder()->getLsOrderId())) {
            $shipment->getOrder()->setIncrementId($shipment->getOrder()->getLsOrderId());
        }
        return [$shipment, $forceSyncMode];
    }
}
