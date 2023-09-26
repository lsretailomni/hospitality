<?php

namespace Ls\Hospitality\ViewModel;

use \Ls\Hospitality\Model\LSR;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Class for handling customer order additional info
 */
class CustomerOrderInfo implements ArgumentInterface
{
    /**
     * @var LSR
     */
    private $lsr;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * CustomerOrderInfo constructor.
     * @param LSR $lsr
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        LSR $lsr,
        CheckoutSession $checkoutSession
    ) {
        $this->lsr               = $lsr;
        $this->checkoutSession   = $checkoutSession;
    }

    /**
     * For checking is hospitality store.
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isHospitalityEnabled()
    {
        return $this->lsr->isHospitalityStore();
    }

    /**
     * To get order status info
     * @return array
     * @throws NoSuchEntityException
     */
    public function getOrderStatusInfo()
    {
        $statusInfo = [];
        if ($this->lsr->showOrderTrackingInfoOnSuccessPage()) {
            $webStore           = $this->lsr->getActiveWebStore();
            $documentId         = $this->checkoutSession->getLastDocumentId();
            $pickupDateTimeslot = $this->checkoutSession->getPickupDateTimeslot();
            if (!empty($documentId)) {
                $statusInfo['orderId']            = $documentId;
                $statusInfo['storeId']            = $webStore;
                $statusInfo['pickupDateTimeslot'] = $pickupDateTimeslot;
            }
        }
        return $statusInfo;
    }
}
