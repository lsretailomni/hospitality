<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Webhooks\Model\Order;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Webhooks\Model\Order\Status;
use Magento\Framework\Exception\NoSuchEntityException;

class StatusPlugin
{
    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(HospitalityHelper $hospitalityHelper)
    {
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * Before plugin to fake lines in case of hospitality
     *
     * @param Status $subject
     * @param array $data
     * @return array
     * @throws NoSuchEntityException
     */
    public function beforeProcess(Status $subject, $data)
    {
        if (!empty($data) && !empty($data['OrderId']) && !empty($data['HeaderStatus'] && empty($data['Lines']))) {
            $this->hospitalityHelper->fakeOrderLinesStatusWebhook($data);
        }

        return [$data];
    }
}
