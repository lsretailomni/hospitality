<?php

namespace Ls\Hospitality\Observer;

use Exception;
use \Ls\Core\Model\LSR as LSRAlias;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Helper\BasketHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Psr\Log\LoggerInterface;

/**
 * This observer is responsible for checks before place order
 */
class BeforePlaceOrderObserver implements ObserverInterface
{
    /**
     * @var BasketHelper
     */
    private $basketHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LSR
     */
    private $lsr;

    /**
     * @var LSRAlias
     */
    private $lsrAlias;

    /***
     * @param BasketHelper $basketHelper
     * @param LoggerInterface $logger
     * @param LSR $LSR
     * @param LSRAlias $lsrAlias
     */
    public function __construct(
        BasketHelper $basketHelper,
        LoggerInterface $logger,
        LSR $lsr,
        LSRAlias $lsrAlias
    ) {
        $this->basketHelper       = $basketHelper;
        $this->logger             = $logger;
        $this->lsr                = $lsr;
        $this->lsrAlias           = $lsrAlias;
    }

    /**
     * Executes the observer logic for processing order data during checkout.
     *
     * @param Observer $observer The observer instance containing the event and order data.
     * @return $this
     * @throws InputException If LSR is enabled, oneListCalculation is empty, and an order document ID is missing,
     *                        this exception is thrown with an error message retrieved from configuration.
     */
    public function execute(Observer $observer)
    {
        if (!$this->lsr->isHospitalityStore()) {
            return;
        }
        
        $check              = false;
        $order              = $observer->getEvent()->getData('order');

        $oneListCalculation = $this->basketHelper->getOneListCalculationFromCheckoutSession();
        
        /*
        * Adding condition to only process if LSR is enabled.
        */
        if ($this->lsrAlias->isLSR(
            $this->lsrAlias->getCurrentStoreId(),
            false,
            $this->lsrAlias->getOrderIntegrationOnFrontend()
        )) {
            if (empty($oneListCalculation) && empty($order->getDocumentId())) {
                $websiteId = $this->lsrAlias->getCurrentWebsiteId();
                $errMsg = $this->lsrAlias->getWebsiteConfig(LSR::LS_ERROR_MESSAGE_ON_BASKET_FAIL, $websiteId);
                $this->logger->critical($errMsg);
                if ($this->lsrAlias->getDisableProcessOnBasketFailFlag()) {
                    throw new InputException(
                        __($errMsg)
                    );
                }
            }
        }
        return $this;
    }
}
