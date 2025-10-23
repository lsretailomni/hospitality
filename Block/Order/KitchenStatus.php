<?php

namespace Ls\Hospitality\Block\Order;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Block for Check kitchen status form
 */
class KitchenStatus extends Template
{
    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get ajax URL for order info
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('hospitality/ajax/orderInfo');
    }
}
