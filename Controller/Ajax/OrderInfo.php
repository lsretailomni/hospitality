<?php

namespace Ls\Hospitality\Controller\Ajax;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use \Ls\Hospitality\Helper\HospitalityHelper;

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
     * @var RedirectFactory
     */
    public $resultRedirectFactory;

    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * OrderInfo constructor.
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param RedirectFactory $resultRedirectFactory
     * @param RequestInterface $request
     */
    public function __construct(
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        RedirectFactory $resultRedirectFactory,
        RequestInterface $request,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->resultPageFactory     = $resultPageFactory;
        $this->resultJsonFactory     = $resultJsonFactory;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->request               = $request;
        $this->hospitalityHelper     = $hospitalityHelper;
    }

    /**
     * Executing the ajax function
     * @return Json
     */
    public function execute()
    {
        if ($this->request->isXmlHttpRequest()) {
            $orderId = $this->request->getParam('orderId');
            $storeId = $this->request->getParam('storeId');
            if (!empty($orderId) && !empty($storeId)) {
                $status     = $this->hospitalityHelper->getKitchenOrderStatusDetails($orderId, $storeId);
                $result     = $this->resultJsonFactory->create();
                $resultPage = $this->resultPageFactory->create();
                $info       = $resultPage->getLayout()
                    ->createBlock('Ls\Hospitality\Block\Customer\Order\Info')
                    ->setTemplate('Ls_Hospitality::customer/order/view/info.phtml')
                    ->setData('data', $status)
                    ->toHtml();
                $result->setData(['output' => $info]);
                return $result;
            }
        }
    }
}
