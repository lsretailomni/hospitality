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
        $this->orderSender = $orderSender;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
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
        // Process orders that were just synced
        $orders = $this->hospitalityHelper->getOrdersWithDocumentIdWithoutLsOrderId($subject->store->getId());

        if (!empty($orders)) {
            foreach ($orders as $order) {
                $this->hospitalityHelper->doHouseKeepingForGivenOrder($order);
                try {
                    $reloadedOrder = $this->orderRepository->get($order->getEntityId());
                    if (!$reloadedOrder->getEmailSent()) {
                        $this->orderSender->send($reloadedOrder);
                        $this->logger->info(
                            sprintf(
                                'Order confirmation email sent for order #%s (LS Order ID: %s)',
                                $reloadedOrder->getIncrementId(),
                                $reloadedOrder->getData('ls_order_id') ?: 'N/A'
                            )
                        );
                    }
                } catch (\Exception $e) {
                    $this->logger->error(
                        sprintf(
                            'Failed to send order confirmation email for order #%s: %s',
                            $order->getIncrementId(),
                            $e->getMessage()
                        )
                    );
                }
            }
        }
        return $result;
    }
}
