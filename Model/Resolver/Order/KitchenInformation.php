<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Order;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver to fetch order kitchen information
 */
class KitchenInformation implements ResolverInterface
{
    /**
     * @var LSR
     */
    public $hospitalityLsr;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @param LSR $hospitalityLsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        LSR $hospitalityLsr,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->hospitalityLsr    = $hospitalityLsr;
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        if (empty($args['order_id'])) {
            throw new GraphQlInputException(__('Required parameter "order_id" is missing'));
        }
        $results  = [];
        $orderId  = $args['order_id'];
        $webStore = $this->hospitalityLsr->getActiveWebStore();
        $status   = $this->hospitalityHelper->getKitchenOrderStatusDetails($orderId, $webStore);
        foreach ($status as $statusInfo) {
            $results[] = [
                'status_code'            => $statusInfo['status'],
                'status_description'     => $statusInfo['status_description'],
                'display_estimated_time' => $this->hospitalityLsr->displayEstimatedDeliveryTime(),
                'estimated_time'         => $statusInfo['production_time'] . ' Minutes',
                'pickup_date'            => $this->hospitalityHelper->getOrderPickupDate($orderId),
                'pickup_time'            => $this->hospitalityHelper->getOrderPickupTime($orderId),
                'queue_counter'          => $statusInfo['q_counter'],
                'kot_no'                 => $statusInfo['kot_no'],
                'order_items'            => $statusInfo['lines'],
                'order_items_count'      => count($statusInfo['lines']) . " "
            ];
        }

        return $results;
    }
}
