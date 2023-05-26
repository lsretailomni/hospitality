<?php

namespace Ls\Hospitality\Plugin\Model;

use \Ls\Hospitality\Model\LSR;
use Magento\Quote\Model\Quote;

/**
 * Interceptor to intercept Quote method
 */
class QuotePlugin
{

    /**
     * Before plugin to set qrcode data in customer cart
     *
     * @param Quote $subject
     * @param Quote $quote
     * @return void
     */
    public function beforeMerge(Quote $subject, Quote $quote)
    {
        $qrcode = $quote->getData(LSR::LS_QR_CODE_ORDERING);
        if ($qrcode) {
            $subject->setData(LSR::LS_QR_CODE_ORDERING, $qrcode);
        }
    }
}
