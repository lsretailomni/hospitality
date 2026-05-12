<?php

namespace Ls\Hospitality\Plugin\Replication\Cron;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Replication\Cron\SyncOrders;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;

/**
 * Plugin for SyncOrders cron
 */
class SyncOrdersPlugin
{
    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param OrderSender $orderSender
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        OrderSender $orderSender,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
        $this->orderSender       = $orderSender;
        $this->logger            = $logger;
        $this->orderRepository   = $orderRepository;
    }

    /**
     * After plugin for execute method
     *
     * @param SyncOrders $subject
     * @param array $result
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterExecute(SyncOrders $subject, $result)
    {
        if (!$this->hospitalityHelper->getLSR()->isSSM()) {
            $stores = $this->hospitalityHelper->getLSR()->getAllStores();
        } else {
            $stores = [$this->hospitalityHelper->getLSR()->getAdminStore()];
        }

        if (!empty($stores)) {
            foreach ($stores as $store) {
                if ($this->hospitalityHelper->getLSR()->isHospitalityStore($store->getId())) {
                    $orders = $this->hospitalityHelper->getOrdersWithDocumentIdWithoutLsOrderId($store->getId());

                    if (!empty($orders)) {
                        foreach ($orders as $order) {
                            $this->hospitalityHelper->doHouseKeepingForGivenOrder($order);
                        }
                    }

                    $pendingEmailOrders = $this->hospitalityHelper->getOrdersWithDocumentIdWithoutEmailSent(
                        $store->getId()
                    );

                    if (!empty($pendingEmailOrders)) {
                        foreach ($pendingEmailOrders as $order) {
                            try {
                                $this->orderSender->send($order);
                                $order->addCommentToStatusHistory(
                                    __('Order confirmation email sent via cron order #%1 (LS Order ID: %2)',
                                        $order->getIncrementId(),
                                        $order->getData('ls_order_id') ?: 'N/A'
                                    )
                                )->setIsCustomerNotified(true);
                            } catch (\Exception $e) {
                                $order->addCommentToStatusHistory(
                                    __('Failed to send order confirmation email via cron: %1', $e->getMessage())
                                )->setIsCustomerNotified(false);
                            }
                            $this->orderRepository->save($order);
                        }
                    }
                }
            }

            return $result;
        }
    }
}
