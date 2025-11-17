<?php

namespace Ls\Hospitality\Plugin\Omni\Controller\Adminhtml;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Controller\Adminhtml\Order\Request;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Plugin for Request controller
 */
class RequestPlugin
{
    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
        $this->orderRepository = $orderRepository;
    }

    /**
     * After plugin for execute method
     *
     * @param Request $subject
     * @param Redirect $result
     * @return Redirect
     */
    public function afterExecute(Request $subject, $result)
    {
        $orderId = $subject->getRequest()->getParam('order_id');
        $order = $this->orderRepository->get($orderId);
        $this->hospitalityHelper->saveHospOrderId($order, false);
        return $result;
    }
}
