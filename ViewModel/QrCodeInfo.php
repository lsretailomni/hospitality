<?php

namespace Ls\Hospitality\ViewModel;

use \Ls\Hospitality\Model\LSR;
use Magento\Customer\Model\Session\Proxy;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Class for handling QR Code Info
 */
class QrCodeInfo implements ArgumentInterface
{
    /**
     * @var LSR
     */
    private $lsr;

    /**
     * @var Proxy
     */
    private $customerSession;

    /**
     * @param LSR $lsr
     * @param Proxy $customerSession
     */
    public function __construct(
        LSR $lsr,
        Proxy $customerSession
    ) {
        $this->lsr             = $lsr;
        $this->customerSession = $customerSession;
    }

    /**
     * To get content block identifier
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getContentBlockIdentifier()
    {
        return $this->lsr->getStoreConfig(LSR::QRCODE_CONTENT_BLOCK, $this->lsr->getCurrentStoreId());
    }

    /**
     * To show table no
     *
     * @return array
     */
    public function getTableNo()
    {
        $tableNo = '';
        $params  = $this->customerSession->getData(LSR::LS_QR_CODE_ORDERING);
        if (!empty($params)) {
            if (array_key_exists('table_no', $params)) {
                $tableNo = $params['table_no'];
            }
        }
        return $tableNo;
    }

    /**
     * To return error
     *
     * @return array
     */
    public function getError()
    {
        $error  = '';
        $params = $this->customerSession->getData(LSR::LS_QR_CODE_ORDERING);
        if (!empty($params)) {
            if (array_key_exists('error', $params)) {
                $error = $params['error'];
                $this->customerSession->setData(LSR::LS_QR_CODE_ORDERING, null);
            }
        }

        return $error;
    }
}
