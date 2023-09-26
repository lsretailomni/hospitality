<?php
namespace Ls\Hospitality\Plugin\Block\Adminhtml;

use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Block\Adminhtml\Order\View\Info;

/**
 * Display order comment in sales order admin
 */
class SalesOrderViewInfo
{
    /**
     * @param Info $subject
     * @param $result
     * @return mixed|string
     * @throws LocalizedException
     */
    public function afterToHtml(
        Info $subject,
        $result
    ) {
        $commentBlock = $subject->getLayout()->getBlock('ls_order_comments');
        if ($commentBlock !== false && $subject->getNameInLayout() == 'order_info') {
            $commentBlock->setOrderComment($subject->getOrder()->getData(LSR::LS_ORDER_COMMENT));
            $result = $result . $commentBlock->toHtml();
        }

        return $result;
    }
}
