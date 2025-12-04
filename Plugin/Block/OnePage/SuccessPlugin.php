<?php

namespace Ls\Hospitality\Plugin\Block\OnePage;

use Ls\Customer\Block\Onepage\Success;

class SuccessPlugin
{
    /**
     * For displaying order id
     *
     * @param Success $subject
     * @param callable $proceed
     * @return void
     */
    public function aroundPrepareBlockData(Success $subject, callable $proceed)
    {
        $proceed();
        $orderId    = ($subject->getCheckoutSession()->getLastLsOrderId()) ?? null;
        
        $subject->addData(
            [
                'order_id'         => $orderId
            ]
        );
       
    }
}
