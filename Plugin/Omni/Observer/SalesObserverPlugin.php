<?php
namespace Ls\Hospitality\Plugin\Omni\Observer;

use \Ls\Omni\Observer\SalesObserver;
use Magento\Customer\Model\Address\AbstractAddress;
use Magento\Framework\Event\Observer;

class SalesObserverPlugin
{
    /**
     * Add selected tip amount to quote totals after Omni grand total calculation.
     *
     * @param SalesObserver $subject
     * @param callable $proceed
     * @param Observer $observer
     * @return mixed
     */
    public function aroundExecute(SalesObserver $subject, callable $proceed, Observer $observer)
    {
        $result             = $proceed($observer);

        $event = $observer->getEvent();
        if (!$event) {
            return $result;
        }

        $quote = $event->getQuote();
        $total = $event->getTotal();
        $shippingAssignment = $event->getShippingAssignment();
        if (!$quote || !$total || !$shippingAssignment || !$shippingAssignment->getShipping()) {
            return $result;
        }

        $address = $shippingAssignment->getShipping()->getAddress();
        if (!$address) {
            return $result;
        }

        $addressType = (string)$address->getAddressType();
        $isTargetAddress = ($quote->isVirtual() && $addressType == AbstractAddress::TYPE_BILLING)
            || (!$quote->isVirtual() && $addressType == AbstractAddress::TYPE_SHIPPING);

        if (!$isTargetAddress) {
            return $result;
        }

        $tipAmount = (float)$quote->getLsTipAmount();
        if ($tipAmount <= 0) {
            return $result;
        }        

        $total->setGrandTotal((float)$total->getGrandTotal() + $tipAmount);
        $total->setBaseGrandTotal((float)$total->getBaseGrandTotal() + $tipAmount);

        return $result;
    }
}

