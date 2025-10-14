<?php
declare(strict_types=1);

namespace Ls\Hospitality\Controller\Ajax;

use GuzzleHttp\Exception\GuzzleException;
use \Ls\Customer\Block\Order\Info;
use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

/**
 * Ajax controller for calling hospitality order additional information
 */
class OrderInfo implements HttpGetActionInterface
{
    /**
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        public PageFactory $resultPageFactory,
        public JsonFactory $resultJsonFactory,
        public RequestInterface $request,
        public HospitalityHelper $hospitalityHelper
    ) {
    }

    /**
     * Executing the ajax function for order status info
     *
     * @return Json
     * @throws NoSuchEntityException|GuzzleException
     */
    public function execute()
    {
        if ($this->request->isXmlHttpRequest()) {
            $orderId         = $this->request->getParam('orderId');
            $storeId         = $this->request->getParam('storeId');
            $pickupOrderTime = $this->request->getParam('pickupOrderTime');
            if (!empty($orderId) && !empty($storeId)) {
                $status     = $this->hospitalityHelper->getKitchenOrderStatusDetails($orderId, $storeId);
                foreach ($status as &$statusInfo) {
                    $statusInfo['pickupOrderTime'] = $pickupOrderTime;
                }
                $result     = $this->resultJsonFactory->create();
                $resultPage = $this->resultPageFactory->create();
                $info       = $resultPage->getLayout()
                    ->createBlock(Info::class)
                    ->setTemplate('Ls_Hospitality::customer/order/view/info.phtml')
                    ->setData('data', $status)
                    ->toHtml();
                $result->setData(['output' => $info]);
                return $result;
            }
        }

        return null;
    }
}
