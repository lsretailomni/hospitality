<?php

namespace Ls\Hospitality\Model\Total\Quote;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;

/**
 * Class LsTipsSelected
 * @package Ls\Omni\Model\Total\Quote
 */
class LsTipsSelected extends AbstractTotal
{

    /**
     * For fetching tips amount added
     *
     * @param Quote $quote
     * @param Total $total
     * @return array|null
     * @throws NoSuchEntityException
     */
    public function fetch(Quote $quote, Total $total)
    {
        $totals      = [];
        $tipsAmount = $quote->getLsTipAmount();
        if ($tipsAmount > 0) {            
            $totals[] = [
                'code'  => $this->getCode(),
                'title' => __('Tips Added'),
                'value' => $tipsAmount,
            ];            
        }
        return $totals;
    }
}
