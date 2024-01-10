<?php
namespace Ls\Hospitality\Model\Order;

use \Ls\Hospitality\Api\Data\OrderCommentInterface;
use \Ls\Hospitality\Api\OrderCommentManagementInterface;
use \Ls\Hospitality\Api\GuestOrderCommentManagementInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;

/**
 * Order comment management save and vdlidate operations for guest users
 */
class GuestOrderCommentManagement implements GuestOrderCommentManagementInterface
{

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var OrderCommentManagementInterface
     */
    protected $orderCommentManagement;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param OrderCommentManagementInterface $orderCommentManagement
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        OrderCommentManagementInterface $orderCommentManagement
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->orderCommentManagement = $orderCommentManagement;
    }

    /**
     * {@inheritDoc}
     */
    public function saveOrderComment(
        $cartId,
        OrderCommentInterface $orderComment
    ) {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        return $this->orderCommentManagement->saveOrderComment($quoteIdMask->getQuoteId(), $orderComment);
    }
}
