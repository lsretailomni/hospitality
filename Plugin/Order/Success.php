<?php

namespace Ls\Hospitality\Plugin\Order;

use Magento\Checkout\Controller\Onepage\Success as OrderSuccess;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use \Ls\Hospitality\Model\LSR;

/**
 * To intercept order success controller.
 */
class Success
{
    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @var LSR
     */
    private $lsr;

    /**
     * Success constructor.
     * @param PageFactory $resultPageFactory
     * @param LSR $lsr
     */
    public function __construct(
        PageFactory $resultPageFactory,
        LSR $lsr
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->lsr               = $lsr;
    }

    /**
     * @param OrderSuccess $subject
     * @param $result
     * @return Page
     */
    public function afterExecute(OrderSuccess $subject, $result)
    {
        $storeId = $this->lsr->getActiveWebStore();
        if ($this->lsr->getCurrentIndustry($storeId) == LSR::LS_INDUSTRY_VALUE_HOSPITALITY &&
            $this->lsr->isLSR($storeId)) {
            $resultPage = $this->resultPageFactory->create();
            return $resultPage;
        }
        return $result;
    }
}
