<?php

namespace Ls\Hospitality\Controller\Ajax;

use \Ls\Customer\Block\Order\Info;
use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Ajax controller for saving tip information to quote
 */
class SaveTip extends Action implements HttpPostActionInterface
{
    /**
     * SaveTip constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param HospitalityHelper $hospitalityHelper
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        public Context $context,
        private JsonFactory $resultJsonFactory,
        public RequestInterface $request,
        public HospitalityHelper $hospitalityHelper,
        public CheckoutSession $checkoutSession,
        private LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Executing the ajax function for saving tip on quote
     *
     * @return \Magento\Framework\Controller\Result\Json
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            // Accept only AJAX POST for saving tip
            if (!$this->request->isXmlHttpRequest() || !$this->request->isPost()) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Invalid request method.')
                ]);
            }

            // Accept 'tip' or 'tip_amount'
            $tipParam = $this->request->getParam('tip');
            if ($tipParam === null) {
                $tipParam = $this->request->getParam('tip_amount'); //for custom tip
            }

            // Normalize numeric value
            $tipAmount = 0.0;
            if ($tipParam !== null && $tipParam !== '') {
                $tipAmount = (float)str_replace([','], ['.'], $tipParam);
            }

            // Basic validation: non-negative and reasonable upper limit (e.g., 1000000)
            if ($tipAmount < 0 || $tipAmount > 1000000) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Invalid tip amount')
                ]);
            }

            // Save to quote
            $quote = $this->checkoutSession->getQuote();
            if (!$quote) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Quote not available')
                ]);
            }
            
            $quote = $this->hospitalityHelper->saveTipsToQuote($quote, $tipAmount);
            
            if($quote) {
                // Return success and updated totals to allow frontend to refresh summary
                $grandTotal = (float)$quote->getGrandTotal();
                $baseGrandTotal = (float)$quote->getBaseGrandTotal();
                $savedTip = $quote->getData('ls_tip_amount');
                $savedBaseTip = $quote->getData('base_ls_tip_amount');

                return $resultJson->setData([
                    'success' => true,
                    'message' => __('Tip amount saved successfully.'),
                    'tip' => $tipAmount,
                    'saved_tip' => $savedTip,
                    'saved_base_tip' => $savedBaseTip,
                    'grand_total' => $grandTotal,
                    'base_grand_total' => $baseGrandTotal
                ]);
            } else {   
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Could not save tip. Please try again later.')
                ]);
            } 
        } catch (\Exception $e) {
            $this->logger->error('Exception in SaveTip: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('Could not save tip: %1', $e->getMessage())
            ]);
        }
    }
}
