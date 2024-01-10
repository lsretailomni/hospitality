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
        if (isset($result['order']) && isset($result['order']['document_id'])) {
            $result['order']['pickup_date'] = $this->hospitalityHelper->getOrderPickupDate(
                $result['order']['document_id']
            );
            $result['order']['pickup_time'] = $this->hospitalityHelper->getOrderPickupTime(
                $result['order']['document_id']
            );
        }

        return $result;
    }
}
