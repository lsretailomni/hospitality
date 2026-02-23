<?php

namespace Ls\Hospitality\Controller\Ajax;

use \Ls\Customer\Block\Order\Info;
use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Ajax controller for calling hospitality order additional information
 */
class OrderInfo implements HttpGetActionInterface
{

    /**
     * @var PageFactory
     */
    public $resultPageFactory;

    /**
     * @var JsonFactory
     */
    public $resultJsonFactory;

    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * OrderInfo constructor.
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param HospitalityHelper $hospitalityHelper
     * @param CheckoutSession $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        HospitalityHelper $hospitalityHelper,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $request;
        $this->hospitalityHelper = $hospitalityHelper;
        $this->checkoutSession   = $checkoutSession;
        $this->orderRepository   = $orderRepository;
    }

    /**
     * Executing the ajax function for order status info
     * @return Json
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        if ($this->request->isXmlHttpRequest()) {
            $orderId         = $this->request->getParam('orderId');
            $storeId         = $this->request->getParam('storeId');
            $pickupOrderTime = $this->request->getParam('pickupOrderTime');
            if (empty($orderId)) {
                $lastOrder = $this->checkoutSession->getLastRealOrder();
                if ($lastOrder && $lastOrder->getId()) {
                    $order   = $this->orderRepository->get($lastOrder->getId());
                    $orderId = $order->getDocumentId();
                    if (empty($storeId)) {
                        $this->hospitalityHelper->getLSR()->setStoreId($order->getStoreId());
                        $storeId = $this->hospitalityHelper->getLSR()->getActiveWebStore();
                    }
                }
            }

            if (empty($storeId)) {
                $storeId = $this->hospitalityHelper->getLSR()->getActiveWebStore();
            }

            $result     = $this->resultJsonFactory->create();
            $resultPage = $this->resultPageFactory->create();

            if (!empty($orderId) && !empty($storeId)) {
                $status = $this->hospitalityHelper->getKitchenOrderStatusDetails($orderId, $storeId);
                foreach ($status as &$statusInfo) {
                    $statusInfo['pickupOrderTime'] = $pickupOrderTime;
                }
                $info = $resultPage->getLayout()
                    ->createBlock(Info::class)
                    ->setTemplate('Ls_Hospitality::customer/order/view/info.phtml')
                    ->setData('data', $status)
                    ->toHtml();
                $result->setData(['output' => $info]);
                return $result;
            } else {
                // Return default message when orderId is empty
                $defaultHtml = '<div class="hosp-info-container">' .
                    '<div class="block-content">' .
                    '<div class="custom-box">' .
                    '<span>' . __('We are processing your payment and will notify you once payment has been processed.') . '</span>' .
                    '</div>' .
                    '</div>' .
                    '</div>';
                $result->setData(['output' => $defaultHtml]);
                return $result;
            }
        }
    }
}
