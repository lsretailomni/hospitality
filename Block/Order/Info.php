<?php

namespace Ls\Hospitality\Block\Order;

use \Ls\Core\Model\LSR;
use \Ls\Hospitality\Model\LSR as HospitalityLsr;
use \Ls\Omni\Helper\OrderHelper;
use \Ls\Omni\Helper\Data as DataHelper;
use Magento\Customer\Model\Session\Proxy as CustomerSession;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\View\Element\Template\Context;

/**
 * Overriding the Info block to change the page title on hospitality
 */
class Info extends \Ls\Customer\Block\Order\Info
{
    /**
     * @var HospitalityLsr
     */
    public $hospitalityLsr;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param CountryFactory $countryFactory
     * @param PriceHelper $priceHelper
     * @param OrderRepository $orderRepository
     * @param OrderHelper $orderHelper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CustomerSession $customerSession
     * @param HttpContext $httpContext
     * @param LSR $lsr
     * @param DataHelper $dataHelper
     * @param HospitalityLsr $hospitalityLsr
     * @param array $data
     */
    public function __construct(
        TemplateContext $context,
        Registry $registry,
        CountryFactory $countryFactory,
        Data $priceHelper,
        OrderRepository $orderRepository,
        OrderHelper $orderHelper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CustomerSession $customerSession,
        HttpContext $httpContext,
        LSR $lsr,
        DataHelper $dataHelper,
        HospitalityLsr $hospitalityLsr,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $countryFactory,
            $priceHelper,
            $orderRepository,
            $orderHelper,
            $searchCriteriaBuilder,
            $customerSession,
            $httpContext,
            $lsr,
            $dataHelper,
            $data
        );

        $this->hospitalityLsr = $hospitalityLsr;
    }

    /**
     * @inheritDoc
     *
     * @throws NoSuchEntityException
     */
    protected function _prepareLayout()
    {
        if (!$this->hospitalityLsr->isHospitalityStore()) {
            parent::_prepareLayout();
        } else {
            if ($this->getOrder()) {
                $this->pageConfig->getTitle()->set(__('Order # %1', $this->getOrder()->getId()));
            }
        }
    }
}
