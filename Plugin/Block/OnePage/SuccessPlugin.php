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
        $order      = $subject->getCheckoutSession()->getLastRealOrder();
        $orderId    = $subject->getCheckoutSession()->getLastOrderId();
        $documentId = $subject->getCheckoutSession()->getLastDocumentId();

        if ($orderId) {
            $subject->addData(
                [
                    'order_id'         => $orderId
                ]
            );
        }
    }
}
