<?php

namespace Ls\Hospitality\Controller\Adminhtml\System\Config;

use Exception;
use \Ls\Core\Model\LSR;
use \Ls\Omni\Helper\StoreHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

/**
 * Get sales type controller
 */
class GetSalesType extends Action
{

    /**
     * @var JsonFactory
     */
    public $resultJsonFactory;

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var StoreHelper
     */
    public $storeHelper;

    /**
     * GetSalesType constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param LSR $lsr
     * @param StoreHelper $storeHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        LSR $lsr,
        StoreHelper $storeHelper,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->lsr               = $lsr;
        $this->storeHelper       = $storeHelper;
        $this->logger            = $logger;
        parent::__construct($context);
    }

    /**
     * Collect sales type data for web store
     *
     * @return Json
     */
    public function execute()
    {
        $option_array = [];
        try {
            $baseUrl    = $this->getRequest()->getParam('baseUrl');
            $storeId    = $this->getRequest()->getParam('storeId');
            $salesTypes = null;
            if ($this->lsr->validateBaseUrl($baseUrl) && $storeId != "") {
                $salesTypes = $this->storeHelper->getSalesType('', $storeId, $baseUrl);
            }
            if (!empty($salesTypes)) {
                $salesTypeArray = $salesTypes->getHospSalesTypes();
                $option_array   = [['value' => '', 'label' => __('Please select sales type')]];
                foreach ($salesTypeArray as $salesType) {
                    $option_array[] = ['value' => $salesType->getCode(), 'label' => $salesType->getDescription()];
                }
            } else {
                $option_array = [['value' => '', 'label' => __('No sales type found for the selected store')]];
            }
        } catch (Exception $e) {
            $this->logger->critical($e);
        }
        /** @var Json $result */
        $result = $this->resultJsonFactory->create();
        return $result->setData(['success' => true, 'salesType' => $option_array]);
    }

    /**
     * Authorize to access ajax call
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Ls_Core::config');
    }
}
