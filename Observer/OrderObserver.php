<?php

namespace Ls\Hospitality\Observer;

use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer for saving order id in hospitality
 */
class OrderObserver implements ObserverInterface
{
    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        LoggerInterface $logger
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
        $this->logger            = $logger;
    }

    /**
     * Execute observer to save ls_order_id
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $this->hospitalityHelper->saveHospOrderId($order, true);
    }
}
