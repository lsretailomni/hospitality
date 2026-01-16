<?php

namespace Ls\Hospitality\Plugin\Omni\Observer;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Observer\OrderObserver;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;

/**
 * Plugin for OrderObserver to add hospitality order handling
 */
class OrderObserverPlugin
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
     * Around execute plugin for OrderObserver
     *
     * @param OrderObserver $subject
     * @param callable $proceed
     * @param Observer $observer
     * @return mixed
     */
    public function aroundExecute(
        OrderObserver $subject,
        callable $proceed,
        Observer $observer
    ) {
        // Execute the original observer
        $result = $proceed($observer);
        try {
            $order = $observer->getEvent()->getData('order');

            if (empty($order->getIncrementId())) {
                $orderIds = $observer->getEvent()->getOrderIds();
                if (!empty($orderIds)) {
                    $this->hospitalityHelper->doHouseKeepingForGivenOrder($order);
                }
            } else {
                $this->hospitalityHelper->doHouseKeepingForGivenOrder($order);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error saving hospitality order ID: ' . $e->getMessage());
        }

        return $result;
    }
}
