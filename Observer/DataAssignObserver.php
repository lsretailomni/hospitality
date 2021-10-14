<?php

namespace Ls\Hospitality\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Class DataAssignObserver for assigning service mode value to order
 */
class DataAssignObserver implements ObserverInterface
{

    /**
     * @param Observer $observer
     * @return DataAssignObserver
     */
    public function execute(Observer $observer)
    {
        $quote = $observer->getQuote();
        $order = $observer->getOrder();

        if ($quote->getServiceMode()) {
            $order->setServiceMode($quote->getServiceMode());
        }

        if ($quote->getPickupDateTimeslot()) {
            $order->setPickupDateTimeslot($quote->getPickupDateTimeslot());
        }

        return $this;
    }
}
