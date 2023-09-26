<?php
namespace Ls\Hospitality\Model\Order\Data;

use \Ls\Hospitality\Api\Data\OrderCommentInterface;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Api\AbstractSimpleObject;

/**
 * Order comment class to set and get comment
 */
class OrderComment extends AbstractSimpleObject implements OrderCommentInterface
{

    /**
     * @return string|null
     */
    public function getComment()
    {
        return $this->_get(LSR::LS_ORDER_COMMENT);
    }

    /**
     * @param string $comment
     * @return $this
     */
    public function setComment($comment)
    {
        return $this->setData(LSR::LS_ORDER_COMMENT, $comment);
    }
}
