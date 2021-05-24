<?php

namespace Ls\Hospitality\Block\Customer\Order;

use \Ls\Customer\Block\Order\Info as CustomerInfo;

/**
 * Block for rendering different additional information for order
 */
class Info extends CustomerInfo
{
    /**
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('hospitality/ajax/OrderInfo');
    }

    // @codingStandardsIgnoreStart
    protected function _prepareLayout()
    {
        $this->pageConfig->getTitle()->set('');
    }
    // @codingStandardsIgnoreEnd
}
