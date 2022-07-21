<?php

namespace Ls\Hospitality\Controller\QrCode;

use \Ls\Hospitality\Helper\QrCodeHelper;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * QR Code Ordering controller
 */
class Index implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    public $resultPageFactory;

    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * @var QrCodeHelper
     */
    public $qrCodeHelper;

    /**
     * @param PageFactory $resultPageFactory
     * @param QrCodeHelper $qrCodeHelper
     * @param RequestInterface $request
     */
    public function __construct(
        PageFactory $resultPageFactory,
        QrCodeHelper $qrCodeHelper,
        RequestInterface $request
    ) {

        $this->resultPageFactory = $resultPageFactory;
        $this->qrCodeHelper      = $qrCodeHelper;
        $this->request           = $request;
    }

    /**
     * For saving qr code ordering values in session
     *
     * @return ResponseInterface|ResultInterface|Page
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $storeId     = $this->request->getParam('?store_no');

        if (!$storeId) {
            $storeId     = $this->request->getParam('store_id');
        }
        $params      = $this->request->getParams();
        if (!empty($storeId) && $this->qrCodeHelper->validateStoreId($storeId)) {
            $this->qrCodeHelper->setQrCodeOrderingInSession($params);
        } else {
            $error['error'] = __('Store not found.');
            $this->qrCodeHelper->setQrCodeOrderingInSession($error);
        }
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(
            __('QR Code Ordering')
        );
        return $resultPage;
    }
}
