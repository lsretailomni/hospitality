<?php

namespace Ls\Hospitality\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Email template vars set values
 */
class Emailtemplatevars implements ObserverInterface
{
    /**
     * Pass additional information to email
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $transport = $observer->getTransport();
        $order     = $transport->getOrder();
        $comment   = $order->getLsOrderComment();
        if (!empty($comment)) {
            $transport['order_data'] = [
                'customer_name'         => $order->getCustomerName(),
                'is_not_virtual'        => $order->getIsNotVirtual(),
                'email_customer_note'   => $comment,
                'frontend_status_label' => $order->getFrontendStatusLabel()
            ];
        }
    }
}
