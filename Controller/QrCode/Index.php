<?php

namespace Ls\Hospitality\Controller\QrCode;

use \Ls\Hospitality\Helper\EncryptionHelper;
use Magento\Checkout\Model\Session\Proxy;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * QR Code Ordering route
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
     * @var EncryptionHelper
     */
    public $encryptionHelper;

    /**
     * @param PageFactory $resultPageFactory
     * @param EncryptionHelper $encryptionHelper
     * @param RequestInterface $request
     */
    public function __construct(
        PageFactory $resultPageFactory,
        EncryptionHelper $encryptionHelper,
        RequestInterface $request
    ) {

        $this->resultPageFactory = $resultPageFactory;
        $this->encryptionHelper  = $encryptionHelper;
        $this->request           = $request;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $storeId     = $this->request->getParam('store_id');
        $locationId  = $this->request->getParam('location_id');
        $tableNo     = $this->request->getParam('table_no');
        $orderSource = 'QR Code Ordering';
        if (!empty($storeId)) {
            $prepareComment = '';
            $storeId        = $this->encryptionHelper->decryptData($storeId);
            $locationId     = $this->encryptionHelper->decryptData($locationId);
            $tableNo        = $this->encryptionHelper->decryptData($tableNo);
            $prepareComment .= 'Store Id:' . $storeId . '\n';
            $prepareComment .= 'Location Id:' . $locationId . '\n';
            $prepareComment .= 'Table No:' . $tableNo . '\n';
            $prepareComment .= 'Order Source:' . $orderSource . '\n';
            $this->encryptionHelper->setQrCodeOrderingComment($prepareComment);
        }
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(
            __('QR Code Ordering')
        );
        return $resultPage;

    }
}
