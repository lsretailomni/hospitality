<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Model\Resolver;

use \Ls\Hospitality\Helper\HospitalityHelper;

/**
 * Interceptor to intercept PlaceOrder resolver methods
 */
class PlaceOrderPlugin
{
    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * After plugin to set custom data in Order response
     *
     * @param mixed $subject
     * @param array $result
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterResolve($subject, $result)
    {
        if (isset($result['order']) && isset($result['order']['order_number'])) {
            $order                          = $this->hospitalityHelper->getOrderByMagId(
                $result['order']['order_number']
            );
            $result['order']['pickup_date'] = $this->hospitalityHelper->getOrderPickupDate(
                $order->getDocumentId()
            );
            $result['order']['pickup_time'] = $this->hospitalityHelper->getOrderPickupTime(
                $order->getDocumentId()
            );
            $result['order']['document_id'] = $order->getLsOrderId() ?: $order->getDocumentId();
        }

        return $result;
    }
}
