<?php

namespace Ls\Hospitality\Observer\Adminhtml;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\Template;

/**
 * Class BlockObserver for service mode */
class BlockObserver implements ObserverInterface
{

    /** @var Template */
    private $coreTemplate;

    /**
     * BlockObserver constructor.
     * @param Template $coreTemplate
     */
    public function __construct(
        Template $coreTemplate
    ) {
        $this->coreTemplate = $coreTemplate;
    }

    /**
     * @param EventObserver $observer
     * @return $this|void
     */

    public function execute(EventObserver $observer)
    {
        if ($observer->getElementName() == 'order_shipping_view') {
            $shippingInfoBlock = $observer->getLayout()->getBlock($observer->getElementName());
            $order             = $shippingInfoBlock->getOrder();

            if ($order->getShippingMethod() != 'clickandcollect_clickandcollect') {
                return $this;
            }
            $serviceMode = $this->coreTemplate
                ->setServiceMode($order->getServiceMode())
                ->setTemplate('Ls_Hospitality::order/view/service-mode.phtml')
                ->toHtml();
            $html        = $observer->getTransport()->getOutput() . $serviceMode;
            $observer->getTransport()->setOutput($html);
        }
    }
}
