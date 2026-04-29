<?php
namespace Ls\Hospitality\Observer;

use Magento\Framework\DataObject\Copy;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * CopyTipToOrderObserver is responsible for copying tip 
 * information from quote to order during the conversion process.
 */
class CopyTipToOrderObserver implements ObserverInterface
{
    /**
     * @param Copy $objectCopyService
     */
    public function __construct(
        private Copy $objectCopyService
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();

        $this->objectCopyService->copyFieldsetToTarget(
            'sales_convert_quote',
            'to_order',
            $quote,
            $order
        );
    }
}