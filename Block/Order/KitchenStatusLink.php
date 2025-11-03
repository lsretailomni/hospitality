<?php

namespace Ls\Hospitality\Block\Order;

use \Ls\Core\Model\LSR;
use Magento\Framework\View\Element\Html\Link;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Block for Kitchen Status Link in the Footer
 */
class KitchenStatusLink extends Link
{
    /**
     * @var LSR
     */
    private $lsr;

    /**
     * @param Context $context
     * @param LSR $lsr
     * @param array $data
     */
    public function __construct(
        Context $context,
        LSR $lsr,
        array $data = []
    ) {
        $this->lsr = $lsr;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _toHtml()
    {
        $industry = $this->lsr->getCurrentIndustry(
            $this->lsr->getCurrentStoreId()
        );

        if ($industry !== LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return '';
        }

        return parent::_toHtml();
    }
}
