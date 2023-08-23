<?php

namespace Ls\Hospitality\Model\Order;

use \Ls\Hospitality\Api\Data\OrderCommentInterface;
use \Ls\Hospitality\Api\OrderCommentManagementInterface;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Order comment management save and vdlidate operations
 */
class OrderCommentManagement implements OrderCommentManagementInterface
{
    /**
     * Quote repository.
     *
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var LSR
     */
    protected $hospLsr;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param LSR $hospLsr
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        LSR $hospLsr
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->hospLsr         = $hospLsr;
    }

    /**
     * @param int $cartId
     * @param OrderCommentInterface $orderComment
     * @return string|null
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws ValidatorException
     */
    public function saveOrderComment(
        $cartId,
        OrderCommentInterface $orderComment
    ) {
        $quote = $this->quoteRepository->getActive($cartId);
        if (!$quote->getItemsCount()) {
            throw new NoSuchEntityException(__('Cart %1 doesn\'t contain products', $cartId));
        }
        $comment = $orderComment->getComment();

        $this->validateComment($comment);

        try {
            $quote->setData(LSR::LS_ORDER_COMMENT, $comment);
            $this->quoteRepository->save($quote);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('The order comment could not be saved'));
        }

        return $comment;
    }

    /**
     * @param string $comment
     * @throws ValidatorException|NoSuchEntityException
     */
    protected function validateComment($comment)
    {
        $maxLength = $this->hospLsr->getMaximumCharacterLength();
        if ($maxLength && (mb_strlen($comment) > $maxLength)) {
            throw new ValidatorException(__('Comment is too long'));
        }
    }
}
