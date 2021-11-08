<?php

namespace Ls\Hospitality\Api\Data;

/**
 * Order comment interface
 */
interface OrderCommentInterface
{
    /**
     * @return string|null
     */
    public function getComment();

    /**
     * @param string $comment
     * @return null
     */
    public function setComment($comment);
}
