<?php

namespace Ls\Hospitality\Plugin\Checkout\Model;

use \Ls\Hospitality\Model\LSR;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\QuoteRepository;

/**
 * Class ShippingInformationManagement for service mode
 */
class ShippingInformationManagement
{
    /** @var QuoteRepository */
    public $quoteRepository;

    /**
     * @var DateTime
     */
    public $dateTime;

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @param QuoteRepository $quoteRepository
     * @param DateTime $dateTime
     * @param LSR $lsr
     */
    public function __construct(
        QuoteRepository $quoteRepository,
        DateTime $dateTime,
        LSR $lsr
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->dateTime        = $dateTime;
        $this->lsr             = $lsr;
    }

    /**
     * @param \Magento\Checkout\Model\ShippingInformationManagement $subject
     * @param $cartId
     * @param ShippingInformationInterface $addressInformation
     * @throws NoSuchEntityException
     */
    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        $extAttributes      = $addressInformation->getExtensionAttributes();
        $serviceMode        = $extAttributes->getServiceMode();
        $pickupDate         = $extAttributes->getPickupDate();
        $pickupTimeslot     = $extAttributes->getPickupTimeslot();
        $quote              = $this->quoteRepository->getActive($cartId);
        $pickupDateTimeslot = '';
        $quote->setServiceMode($serviceMode);
        if (!empty($pickupDate) && !empty($pickupTimeslot)) {
            $pickupDateFormat   = $this->lsr->getStoreConfig(LSR::PICKUP_DATE_FORMAT);
            $pickupTimeFormat   = $this->lsr->getStoreConfig(LSR::PICKUP_TIME_FORMAT);
            $pickupDateTimeslot = $pickupDate . ' ' . $pickupTimeslot;
            $pickupDateTimeslot = $this->dateTime->date(
                $pickupDateFormat . ' ' . $pickupTimeFormat,
                strtotime($pickupDateTimeslot));
        }
        $quote->setPickupDateTimeslot($pickupDateTimeslot);
    }
}
