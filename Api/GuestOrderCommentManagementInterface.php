<?php

namespace Ls\Hospitality\Api;

use Ls\Hospitality\Api\Data\OrderCommentInterface;
use Magento\Checkout\Api\Data\PaymentDetailsInterface;

/**
 * Interface for saving the checkout comment for guest orders
 */
interface GuestOrderCommentManagementInterface
{
    /**
     * @param string $cartId
     * @param OrderCommentInterface $orderComment
     * @return PaymentDetailsInterface
     */
    public function saveOrderComment(
        $cartId,
        OrderCommentInterface $orderComment
    );
}
