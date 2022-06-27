<?php

namespace Ls\Hospitality\Block\Order;

use \Ls\Core\Model\LSR;
use \Ls\Hospitality\Model\LSR as HospitalityLsr;
use \Ls\Omni\Helper\LoyaltyHelper;
use \Ls\Omni\Helper\OrderHelper;
use \Ls\Omni\Helper\Data as DataHelper;
use Magento\Customer\Model\Session;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
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
     * @param PriceCurrencyInterface $priceCurrency
     * @param LoyaltyHelper $loyaltyHelper
     * @param LSR $lsr
     * @param OrderHelper $orderHelper
     * @param DataHelper $dataHelper
     * @param PriceHelper $priceHelper
     * @param OrderRepository $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Session $customerSession
     * @param CountryFactory $countryFactory
     * @param HospitalityLsr $hospitalityLsr
     * @param array $data
     */
    public function __construct(
        Context $context,
        PriceCurrencyInterface $priceCurrency,
        LoyaltyHelper $loyaltyHelper,
        LSR $lsr,
        OrderHelper $orderHelper,
        DataHelper $dataHelper,
        PriceHelper $priceHelper,
        OrderRepository $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Session $customerSession,
        CountryFactory $countryFactory,
        HospitalityLsr $hospitalityLsr,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $priceCurrency,
            $loyaltyHelper,
            $lsr,
            $orderHelper,
            $dataHelper,
            $priceHelper,
            $orderRepository,
            $searchCriteriaBuilder,
            $customerSession,
            $countryFactory,
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
