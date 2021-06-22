<?php

namespace Ls\Hospitality\Block\Order;

use \Ls\Core\Model\LSR;
use \Ls\Hospitality\Model\LSR as HospitalityLsr;
use \Ls\Omni\Helper\OrderHelper;
use Magento\Customer\Model\Session\Proxy;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Http\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Sales\Model\OrderRepository;

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
     * Info constructor.
     * @param TemplateContext $context
     * @param Registry $registry
     * @param CountryFactory $countryFactory
     * @param Data $priceHelper
     * @param OrderRepository $orderRepository
     * @param OrderHelper $orderHelper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Proxy $customerSession
     * @param Context $httpContext
     * @param Lsr $lsr
     * @param \Ls\Hospitality\Model\LSR $hospitalityLsr
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
        Proxy $customerSession,
        Context $httpContext,
        Lsr $lsr,
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
            $data
        );

        $this->hospitalityLsr = $hospitalityLsr;
    }

    /**
     * @inheritDoc
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
