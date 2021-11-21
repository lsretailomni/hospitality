<?php
namespace Ls\Hospitality\Api;

use \Ls\Hospitality\Api\Data\OrderCommentInterface;

/**
 * Interface for saving the checkout comment for orders of logged in users
 * @api
 */
interface OrderCommentManagementInterface
{
    /**
     * @param int $cartId
     * @param OrderCommentInterface $orderComment
     * @return string
     */
    public function saveOrderComment(
        $cartId,
        OrderCommentInterface $orderComment
    );
}
