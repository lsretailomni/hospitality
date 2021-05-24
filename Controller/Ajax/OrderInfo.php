<?php

namespace Ls\Hospitality\Controller\Ajax;

use \Ls\Hospitality\Block\Customer\Order\Info;
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
     * OrderInfo constructor.
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->resultPageFactory     = $resultPageFactory;
        $this->resultJsonFactory     = $resultJsonFactory;
        $this->request               = $request;
        $this->hospitalityHelper     = $hospitalityHelper;
    }

    /**
     * Executing the ajax function
     * @return Json
     * @throws NoSuchEntityException
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
                    ->createBlock(Info::class)
                    ->setTemplate('Ls_Hospitality::customer/order/view/info.phtml')
                    ->setData('data', $status)
                    ->toHtml();
                $result->setData(['output' => $info]);
                return $result;
            }
        }
    }
}
