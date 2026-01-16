<?php

namespace Ls\Hospitality\Plugin\Replication\Cron;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Replication\Cron\SyncOrders;
use Magento\Framework\Exception\NoSuchEntityException;

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
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
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
            }
        }
        return $result;
    }
}
