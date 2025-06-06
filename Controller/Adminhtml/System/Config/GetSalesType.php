<?php

namespace Ls\Hospitality\Controller\Adminhtml\System\Config;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use \Ls\Core\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Operation\GetStores_GetStores;
use \Ls\Omni\Helper\Data;
use \Ls\Omni\Helper\StoreHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class GetSalesType extends Action
{
    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param LSR $lsr
     * @param StoreHelper $storeHelper
     * @param LoggerInterface $logger
     * @param Data $helper
     */
    public function __construct(
        public Context         $context,
        public JsonFactory     $resultJsonFactory,
        public LSR             $lsr,
        public StoreHelper     $storeHelper,
        public LoggerInterface $logger,
        public Data            $helper,
    ) {
        parent::__construct($context);
    }

    /**
     * Collect sales type data for web store
     *
     * @return Json
     * @throws GuzzleException
     */
    public function execute()
    {
        $options = [];
        try {
            $storeId = $this->getRequest()->getParam('storeId');
            $baseUrl = $this->getRequest()->getParam('baseUrl');
            $scopeId = $this->getRequest()->getParam('scopeId');
            $tenant = $this->getRequest()->getParam('tenant');
            $clientId = $this->getRequest()->getParam('client_id');
            $clientSecret = $this->getRequest()->getParam('client_secret');
            $companyName = $this->getRequest()->getParam('company_name');
            $environmentName = $this->getRequest()->getParam('environment_name');

            $baseUrl = $this->helper->getBaseUrl($baseUrl);
            $connectionParams = [
                'tenant' => $tenant,
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
                'environmentName' => $environmentName,
            ];
            $salesTypes = null;
            if ($this->lsr->validateBaseUrl(
                $baseUrl,
                $connectionParams,
                ['company' => $companyName],
                $scopeId
            )) {
                $webStoreOperation = new GetStores_GetStores(
                    $baseUrl,
                    $connectionParams,
                    $companyName,
                );
                $webStoreOperation->setOperationInput(
                    ['storeGetType' => '0', 'searchText' => $storeId, 'includeDetail' => false]
                );
                $stores = current($webStoreOperation->execute()->getRecords());
                $salesTypes = $stores->getLscSalesType();
            }
            if (!empty($salesTypes)) {
                $options = [['value' => '', 'label' => __('Please select sales type')]];
                foreach ($salesTypes as $salesType) {
                    $options[] = ['value' => $salesType->getCode(), 'label' => $salesType->getDescription()];
                }
            } else {
                $options = [['value' => '', 'label' => __('No sales type found for the selected store')]];
            }
        } catch (Exception $e) {
            $this->logger->critical($e);
        }
        $result = $this->resultJsonFactory->create();

        return $result->setData(['success' => true, 'salesType' => $options]);
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
