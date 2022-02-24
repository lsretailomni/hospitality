<?php

namespace Ls\Hospitality\ViewModel;

use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Checkout\Model\Session\Proxy;
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
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @var Proxy
     */
    private $checkoutSession;

    /**
     * CustomerOrderInfo constructor.
     * @param HospitalityHelper $hospitalityHelper
     * @param LSR $lsr
     * @param Proxy $checkoutSession
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        LSR $lsr,
        Proxy $checkoutSession
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
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
