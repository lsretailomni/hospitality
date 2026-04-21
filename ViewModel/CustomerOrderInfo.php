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
        $this->lsr             = $lsr;
        $this->checkoutSession = $checkoutSession;
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
    public function getOrderStatusInfo($order = null)
    {
        $statusInfo = [];

        if ($this->lsr->showOrderTrackingInfoOnSuccessPage()) {
            $webStore           = $this->lsr->getActiveWebStore();
            $documentId         = $this->checkoutSession->getLastDocumentId();
            $pickupDateTimeslot = $this->checkoutSession->getPickupDateTimeslot();

            if (!empty($order)) {
                $documentId = $order->getDocumentId();
                if (!empty($documentId)) {
                    if ($this->checkoutSession->getLastDocumentId()) {
                        $this->checkoutSession->unsLastDocumentId();
                    }
                }
            }

            if (!empty($documentId)) {
                $statusInfo['orderId']            = $documentId;
                $statusInfo['storeId']            = $webStore;
                $statusInfo['pickupDateTimeslot'] = $pickupDateTimeslot;
            }
        }

        return $statusInfo;
    }

    /**
     * Get refresh interval from admin configuration
     *
     * @return int Interval in milliseconds
     * @throws NoSuchEntityException
     */
    public function getRefreshInterval()
    {
        $seconds = $this->lsr->getStoreConfig(
            LSR::REFRESH_KITCHEN_STATUS_INTERVAL,
            $this->lsr->getCurrentStoreId()
        );

        return max((int)$seconds * 1000, 10000);
    }

    /**
     * Check if auto refresh is enabled from admin configuration
     *
     * @return array|string
     * @throws NoSuchEntityException
     */
    public function isAutoRefreshEnabled()
    {
        return $this->lsr->getStoreConfig(
            LSR::ENABLE_REFRESH_KITCHEN_STATUS_INTERVAL,
            $this->lsr->getCurrentStoreId()
        );
    }
}
