<?php

namespace Ls\Hospitality\Plugin\Omni\Model\Checkout;

use \Ls\Core\Model\LSR as LSRAlias;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Model\Checkout\DataProvider;
use \Ls\Replication\Model\ResourceModel\ReplStore\Collection;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * For intercepting data provider functions
 */
class DataProviderPlugin
{
    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @param LSR $lsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        LSR $lsr,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->lsr = $lsr;
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * After plugin for intercepting get required stores method to get takeaway stores
     *
     * @param DataProvider $subject
     * @param Collection $result
     * @return Collection
     * @throws NoSuchEntityException
     */
    public function afterGetRequiredStores(
        DataProvider $subject,
        $result
    ) {
        if ($this->lsr->getCurrentIndustry($subject->getStoreId()) != LSRAlias::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $result;
        }
        $takeAwaySalesType = $this->lsr->getTakeAwaySalesType();

        return $result->addFieldToFilter('HospSalesTypes', ['like' => '%'.$takeAwaySalesType.'%']);
    }

    /**
     * Before intercept to set the store
     *
     * @param DataProvider $subject
     * @param $responseItems
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function beforeGetSelectedClickAndCollectStoresData(DataProvider $subject, $responseItems)
    {
        if ($this->lsr->getCurrentIndustry($subject->getStoreId()) == LSRAlias::LS_INDUSTRY_VALUE_HOSPITALITY
            && empty($responseItems)) {
            $responseItems [] = $this->lsr->getActiveWebStore();
        }

        return [$responseItems];
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
        if ($this->lsr->isHospitalityStore()) {
            $storeId = $subject->storeManager->getStore()->getId();

            $anonymousOrderEnabled     = $this->lsr->getStoreConfig(
                Lsr::ANONYMOUS_ORDER_ENABLED,
                $storeId
            );
            $removeCheckoutStepEnabled = $this->hospitalityHelper->removeCheckoutStepEnabled();

            $anonymousOrderRequiredAttributes = $this->hospitalityHelper->getformattedAddressAttributesConfig(
                $storeId
            );
            $result['anonymous_order']['is_enabled']      = (bool)$anonymousOrderEnabled;
            $result['anonymous_order']['required_fields'] = $anonymousOrderRequiredAttributes;
            $result['remove_checkout_step_enabled']       = (bool)$removeCheckoutStepEnabled;
        }
        $clickAndCollectEnabled = $this->lsr->getClickCollectEnabled();
        $enabled                = $this->lsr->isPickupTimeslotsEnabled();
        $deliveryHoursEnabled   = $this->lsr->isDeliveryTimeslotsEnabled();
        if (empty($subject->basketHelper->getStorePickUpHoursFromCheckoutSession()) || !$clickAndCollectEnabled) {
            $enabled = 0;
        }
        if (empty($subject->basketHelper->getDeliveryHoursFromCheckoutSession())) {
            $deliveryHoursEnabled = 0;
        }
        $result['shipping'] ['pickup_date_timeslots'] = [
            'options'                => $subject->basketHelper->getStorePickUpHoursFromCheckoutSession(),
            'delivery_hours'         => $subject->basketHelper->getDeliveryHoursFromCheckoutSession(),
            'enabled'                => $enabled,
            'current_web_store'      => $this->lsr->getActiveWebStore(),
            'store_type'             => ($this->lsr->getCurrentIndustry() == LSR::LS_INDUSTRY_VALUE_HOSPITALITY) ?
                1 : 0,
            'delivery_hours_enabled' => $deliveryHoursEnabled
        ];

        return $result;
    }

    /**
     * After getting the configuration
     *
     * @param DataProvider $subject
     * @param $result
     * @return array[]
     * @throws NoSuchEntityException
     */
    public function afterAvailableStoresOnlyEnabled(DataProvider $subject, $result)
    {
        if ($subject->checkoutSession->getNoManageStock()) {
            $result = [true];
        }

        return $result;
    }
}
