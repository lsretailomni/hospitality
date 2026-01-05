<?php

namespace Ls\Hospitality\Plugin\Magento\Quote\Model\Quote;

use \Ls\Hospitality\Model\LSR;
use \Ls\Core\Model\LSR as LSRAlias; 
use \Ls\Hospitality\Model\Order\CheckAvailability;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;

/**
 * Interceptor class to intercept quote_item and do current availability lookup
 */
class ItemPlugin
{
    /** @var LSR @var */
    private $lsr;

    /**
     * @var LSRAlias
     */
    private $lsrAlias;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CheckAvailability
     */
    private $checkAvailability;

    /**
     * @param LSR $LSR
     * @param CheckAvailability $checkAvailability
     */
    public function __construct(
        LSR $LSR,
        LSRAlias $lsrAlias,
        LoggerInterface $logger,
        CheckAvailability $checkAvailability
    ) {
        $this->lsr               = $LSR;
        $this->lsrAlias          = $lsrAlias;
        $this->logger            = $logger;
        $this->checkAvailability = $checkAvailability;
    }

    /**
     * After plugin intercepting addQty of each quote_item
     *
     * @param Item $subject
     * @param object $result
     * @return mixed|void
     * @throws LocalizedException
     */
    public function afterAddQty(Item $subject, $result)
    {
        if ($this->lsr->isHospitalityStore()) {
            if (!$this->lsr->isLSR($this->lsr->getCurrentStoreId())) {
                $websiteId          = $this->lsrAlias->getCurrentWebsiteId();
                $errMsg             = $this->lsrAlias->getWebsiteConfig(LSR::LS_ERROR_MESSAGE_ON_BASKET_FAIL, $websiteId);
                $this->logger->critical($errMsg);
                if ($this->lsrAlias->getDisableProcessOnBasketFailFlag()) {
                    throw new InputException(
                        __($errMsg)
                    );
                }
            }
            
            if (!$result->getParentItem()) {
                $this->checkAvailability->validateQty(true, $result->getQty(), $result);
            }
        }
        return $result;
    }
}
