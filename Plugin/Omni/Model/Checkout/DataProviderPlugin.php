<?php

namespace Ls\Hospitality\Plugin\Omni\Model\Checkout;

use \Ls\Omni\Helper\StoreHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Model\Checkout\DataProvider;
use Magento\Checkout\Model\Session\Proxy as CheckoutSessionProxy;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * For intercepting data provider functions
 */
class DataProviderPlugin
{
    /**
     * @var StoreHelper
     */
    public $storeHelper;

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @var CheckoutSessionProxy
     */
    public $checkoutSession;

    /**
     * @param StoreHelper $storeHelper
     * @param LSR $lsr
     * @param CheckoutSessionProxy $checkoutSession
     */
    public function __construct(
        StoreHelper $storeHelper,
        LSR $lsr,
        CheckoutSessionProxy $checkoutSession
    ) {
        $this->storeHelper     = $storeHelper;
        $this->lsr             = $lsr;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Around plugin for intercepting get stores function
     *
     * @param DataProvider $subject
     * @param callable $proceed
     * @return void
     * @throws NoSuchEntityException
     */
    public function aroundGetStores(
        DataProvider $subject,
        callable $proceed
    ) {
        $salesTypeStoreIdArray = [];
        $storeHoursArray       = [];
        if ($subject->lsr->getCurrentIndustry($subject->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed();
        }
        $takeAwaySalesType = $this->lsr->getTakeAwaySalesType();
        $allStores         = $this->storeHelper->getAllStores($subject->getStoreId());
        foreach ($allStores as $store) {
            if ($this->checkSalesType($store->getHospSalesTypes()->getSalesType(), $takeAwaySalesType) &&
                $store->getIsClickAndCollect() == true) {
                $webStoreId                   = $store->getId();
                $salesTypeStoreIdArray[]      = $webStoreId;
                $storeHoursArray[$webStoreId] = $this->storeHelper->formatDateTimeSlotsValues($store->getStoreHours());
            }
        }
        if (!empty($salesTypeStoreIdArray)) {
            $this->checkoutSession->setStorePickupHours($storeHoursArray);
            return $subject->storeCollectionFactory
                ->create()
                ->addFieldToFilter('nav_id', array(
                    'in' => implode(',', $salesTypeStoreIdArray),
                ))
                ->addFieldToFilter('scope_id', $subject->getStoreId())
                ->addFieldToFilter('ClickAndCollect', 1);
        }

        return $proceed();
    }

    /**
     * After getting the configuration
     *
     * @param DataProvider $subject
     * @param $result
     * @return array[]
     * @throws NoSuchEntityException
     */
    public function afterGetConfig(DataProvider $subject, $result)
    {
        $enabled = $this->lsr->isPickupTimeslotsEnabled();
        if (empty($this->checkoutSession->getStorePickupHours())) {
            $enabled = 0;
        }
        $result['shipping'] ['pickup_date_timeslots'] = [
            'options'           => $this->checkoutSession->getStorePickupHours(),
            'enabled'           => $enabled,
            'current_web_store' => $this->lsr->getActiveWebStore(),
            'store_type'        => ($this->lsr->getCurrentIndustry() == LSR::LS_INDUSTRY_VALUE_HOSPITALITY) ? 1 : 0
            ];

        return $result;
    }

    /**
     * Check Sales Type
     *
     * @param $hospSalesType
     * @param $takeAwaySalesType
     * @return bool
     */
    public function checkSalesType(
        $hospSalesType,
        $takeAwaySalesType
    ) {
        if (!empty($hospSalesType)) {
            foreach ($hospSalesType as $salesType) {
                if ($salesType->getCode() == $takeAwaySalesType) {
                    return true;
                }
            }
        }
        return false;
    }
}
