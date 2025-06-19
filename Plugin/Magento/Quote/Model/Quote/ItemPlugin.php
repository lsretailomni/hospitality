<?php

namespace Ls\Hospitality\Plugin\Magento\Quote\Model\Quote;

use GuzzleHttp\Exception\GuzzleException;
use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Model\Order\CheckAvailability;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;

/**
 * Interceptor class to intercept quote_item and do current availability lookup
 */
class ItemPlugin
{
    /**
     * @param LSR $lsr
     * @param CheckAvailability $checkAvailability
     */
    public function __construct(
        public LSR $lsr,
        public CheckAvailability $checkAvailability
    ) {
    }

    /**
     * After plugin intercepting addQty of each quote_item
     *
     * @param Item $subject
     * @param Item $result
     * @return Item
     * @throws LocalizedException|GuzzleException
     */
    public function afterAddQty(Item $subject, $result)
    {
        if ($this->lsr->isLSR($this->lsr->getCurrentStoreId()) &&
            (!$result->getParentItem()) &&
            $this->lsr->isHospitalityStore()
        ) {
            $this->checkAvailability->validateQty(true, $result);
        }

        return $result;
    }
}
