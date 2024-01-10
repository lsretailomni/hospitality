<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Quote;

use \Ls\Hospitality\Api\Data\OrderCommentInterfaceFactory;
use \Ls\Hospitality\Api\GuestOrderCommentManagementInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

/**
 * Resolver class responsible for setting ls_order_comment on cart
 */
class SetLsOrderCommentsOnCart implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    public $getCartForUser;

    /**
     * @var OrderCommentInterfaceFactory
     */
    public $orderCommentInterfaceFactory;

    /**
     * @var GuestOrderCommentManagementInterface
     */
    public $guestOrderCommentManagement;

    /**
     * @param GetCartForUser $getCartForUser
     * @param OrderCommentInterfaceFactory $orderCommentInterfaceFactory
     * @param GuestOrderCommentManagementInterface $guestOrderCommentManagement
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        OrderCommentInterfaceFactory $orderCommentInterfaceFactory,
        GuestOrderCommentManagementInterface $guestOrderCommentManagement
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->orderCommentInterfaceFactory = $orderCommentInterfaceFactory;
        $this->guestOrderCommentManagement = $guestOrderCommentManagement;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        $maskedCartId = $args['input']['cart_id'];

        if (!isset($args['input']['ls_order_comment'])) {
            throw new GraphQlInputException(__('Required parameter "ls_order_comment" is missing'));
        }
        $lsOrderComment = $args['input']['ls_order_comment'];
        $storeId      = (int)$context->getExtensionAttributes()->getStore()->getId();
        $currentUserId = $context->getUserId();
        $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
        $orderComment = $this->orderCommentInterfaceFactory->create();
        $orderComment->setComment($lsOrderComment);
        $this->guestOrderCommentManagement->saveOrderComment($maskedCartId, $orderComment);

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }
}
