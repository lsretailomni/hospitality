<?php

namespace Ls\Hospitality\ViewModel;

use \Ls\Hospitality\Model\LSR;
use Magento\Checkout\Model\Session\Proxy;
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
    private $checkoutSession;

    /**
     * @param LSR $lsr
     * @param Proxy $checkoutSession
     */
    public function __construct(
        LSR $lsr,
        Proxy $checkoutSession
    ) {
        $this->lsr               = $lsr;
        $this->checkoutSession   = $checkoutSession;
    }

    /**
     * To get content block identifier
     * @return array
     * @throws NoSuchEntityException
     */
    public function getContentBlockIdentifier()
    {
        return $this->lsr->getStoreConfig(LSR::QRCODE_CONTENT_BLOCK, $this->lsr->getCurrentStoreId());
    }
}
