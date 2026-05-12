<?php

namespace Ls\Hospitality\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use \Ls\Hospitality\Model\LSR;

/**
 * Class CurrentAvailabilityObserver to set product salability based on lsr_current_availability attribute
 */
class CurrentAvailabilityObserver implements ObserverInterface
{
    /**
     * @var LSR
     */
    private $lsr;

    /**
     * @param LSR $lsr
     */
    public function __construct(LSR $lsr)
    {
        $this->lsr = $lsr;
    }

    /**
     * Check ls_current_availability attribute to determine salability
     */
    public function execute(Observer $observer)
    {
        if (!$this->lsr->isHospitalityStore()) {
            return;
        }

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getProduct();

        $isUnavailable = $product->getData(LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE);

        if ($isUnavailable === null) {
            return;
        }

        $product->setStatus(!($isUnavailable));
        $product->unsetData('is_salable');
        $product->isAvailable();
    }
}
