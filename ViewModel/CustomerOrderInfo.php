<?php

namespace Ls\Hospitality\ViewModel;

use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Checkout\Model\Session\Proxy;

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
     */
    public function isHospitalityEnabled()
    {
        return $this->lsr->isHospitalityStore();
    }

    /**
     * To get order status info
     * @return array
     */
    public function getOrderStatusInfo()
    {
        $statusInfo = [];
        $storeId    = $this->lsr->getActiveWebStore();
        $documentId = $this->checkoutSession->getLastDocumentId();
        if (!empty($documentId)) {
            $webStore              = $this->lsr->getStoreConfig(LSR::SC_SERVICE_STORE, $storeId);
            $statusInfo['orderId'] = $documentId;
            $statusInfo['storeId'] = $webStore;
        }
        return $statusInfo;
    }
}
