<?php

namespace Ls\Hospitality\Block\Order;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Escaper;

/**
 * Block for Check kitchen status form
 */
class KitchenStatus extends Template
{
    /**
     * @var Escaper
     */
    public $escaper;

    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        array $data = []
    ) {
        $this->escaper = $escaper;
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

    /**
     * Get escaper
     *
     * @return Escaper
     */
    public function getEscaper()
    {
        return $this->escaper;
    }
}
