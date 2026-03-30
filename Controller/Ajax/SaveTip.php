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
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Psr\Log\LoggerInterface;

/**
 * Ajax controller for saving tip information to quote
 */
class SaveTip extends Action implements HttpPostActionInterface
{
    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var RequestInterface */
    public $request;

    /** @var HospitalityHelper */
    public $hospitalityHelper;

    /** @var CheckoutSession */
    private $checkoutSession;
    
    /** @var CartRepositoryInterface */
    public $quoteRepository;

    /** @var QuoteResource */
    private $quoteResource;

    /** @var LoggerInterface */
    private $logger;

    /**
     * SaveTip constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param HospitalityHelper $hospitalityHelper
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param QuoteResource $quoteResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        HospitalityHelper $hospitalityHelper,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        QuoteResource $quoteResource,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->hospitalityHelper = $hospitalityHelper;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->quoteResource = $quoteResource;
        $this->logger = $logger;
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
                // Ensure dot decimal and cast
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

            // Save both ls_tip_amount
            try {
                $quote->setData('ls_tip_amount', $tipAmount);
                $quote->setData('base_ls_tip_amount', $tipAmount);

                // Ensure totals are recollected
                $quote->collectTotals();

                // Log before save
                $this->logger->info('SaveTip: saving quote id=' . $quote->getId() . ' tip=' . $tipAmount);

                // Save via repository first
                $this->quoteRepository->save($quote);

                // Also persist directly via resource model to ensure columns are written
                try {
                    $this->quoteResource->save($quote);
                } catch (\Throwable $innerEx) {
                    $this->logger->warning('Quote resource save failed: ' . $innerEx->getMessage());
                }

                // Persist specific columns (in case repository/resource mapping differs)
                try {
                    $this->quoteResource->saveAttribute($quote, 'ls_tip_amount');
                    $this->quoteResource->saveAttribute($quote, 'base_ls_tip_amount');
                } catch (\Throwable $innerEx) {
                    // Log but continue
                    $this->logger->warning('Could not save quote attribute via resource: ' . $innerEx->getMessage());
                }

                // Reload the quote from repository to ensure saved values are persisted
                $quote = $this->quoteRepository->get($quote->getId());               

            } catch (\Throwable $e) {
                $this->logger->error('Failed saving tip to quote: ' . $e->getMessage());
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Failed to save tip to quote: %1', $e->getMessage())
                ]);
            }

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
        } catch (\Exception $e) {
            $this->logger->error('Exception in SaveTip: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('Could not save tip: %1', $e->getMessage())
            ]);
        }
    }
}
