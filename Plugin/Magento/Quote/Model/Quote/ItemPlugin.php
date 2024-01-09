<?php

namespace Ls\Hospitality\Plugin\Magento\Quote\Model\Quote;

use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Model\Order\CheckAvailability;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;

/**
 * Interceptor class to intercept quote_item and do current availability lookup
 */
class ItemPlugin
{
    /** @var LSR @var */
    private $lsr;

    /**
     * @var CheckAvailability
     */
    private $checkAvailability;

    /**
     * @param LSR $LSR
     * @param StockHelper $stockHelper
     */
    public function __construct(
        LSR $LSR,
        CheckAvailability $checkAvailability
    ) {
        $this->lsr               = $LSR;
        $this->checkAvailability = $checkAvailability;
    }

    /**
     * After plugin intercepting addQty of each quote_item
     *
     * @param Item $subject
     * @param $result
     * @return mixed
     * @throws LocalizedException
     */
    public function afterAddQty(Item $subject, $result)
    {
        if ($this->lsr->isLSR($this->lsr->getCurrentStoreId()) && (!$result->getParentItem())) {
            return $this->checkAvailability->validateQty(true,$result->getQty(), $result);
        }

        return $result;
    }
}
