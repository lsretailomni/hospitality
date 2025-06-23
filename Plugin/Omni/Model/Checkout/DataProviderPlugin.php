<?php

namespace Ls\Hospitality\Plugin\Omni\Model\Checkout;

use Laminas\Json\Json;
use \Ls\Core\Model\LSR as LSRAlias;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Block\Stores\Stores;
use \Ls\Omni\Model\Checkout\DataProvider;
use Ls\Replication\Model\ResourceModel\ReplStoreview\Collection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * For intercepting data provider functions
 */
class DataProviderPlugin
{
    /**
     * @param LSR $lsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        public LSR $lsr,
        public HospitalityHelper $hospitalityHelper
    ) {
    }

    /**
     * After plugin for intercepting get required stores method to get takeaway stores
     *
     * @param DataProvider $subject
     * @param callable $proceed
     * @return Collection
     * @throws NoSuchEntityException
     */
    public function aroundGetRequiredStores(
        DataProvider $subject,
        $proceed,
    ) {
        if ($this->lsr->getCurrentIndustry($subject->getStoreId()) != LSRAlias::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed();
        }
        $takeAwaySalesType = $this->lsr->getTakeAwaySalesType();

        $allStores = $subject->storeHelper->getAllStoresFromCentral();
        $requiredStores = [];

        foreach (!empty($allStores->getLscStore()) ?  $allStores->getLscStore() : [] as $store) {
            if (($store->getClickAndCollect() ||
                $store->getWebStore()) &&
                in_array($takeAwaySalesType, explode('|', $store->getStoreSalesTypeFilter()))
            ) {
                $requiredStores[] = $store->getNo();
            }
        }

        return $subject->storeCollectionFactory->create()
            ->addFieldToFilter(
                'scope_id',
                !$subject->lsr->isSSM() ?
                    $subject->lsr->getCurrentWebsiteId() :
                    $subject->lsr->getAdminStore()->getWebsiteId()
            )->addFieldToFilter('no', ['in' => $requiredStores]);
    }

    /**
     * Before intercept to set the store
     *
     * @param DataProvider $subject
     * @param array $responseItems
     * @return array
     * @throws NoSuchEntityException
     */
    public function beforeGetSelectedClickAndCollectStoresData(DataProvider $subject, array $responseItems)
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
     * @param array $result
     * @return array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function afterGetConfig(DataProvider $subject, array $result)
    {
        if ($this->lsr->isHospitalityStore()) {
            $storeId = $subject->storeManager->getStore()->getId();

            $anonymousOrderEnabled = $this->lsr->getStoreConfig(
                Lsr::ANONYMOUS_ORDER_ENABLED,
                $storeId
            );
            $removeCheckoutStepEnabled = $this->hospitalityHelper->removeCheckoutStepEnabled();

            $anonymousOrderRequiredAttributes = $this->hospitalityHelper->getformattedAddressAttributesConfig(
                $storeId
            );
            $result['anonymous_order']['is_enabled'] = (bool)$anonymousOrderEnabled;
            $result['anonymous_order']['required_fields'] = $anonymousOrderRequiredAttributes;
            $result['remove_checkout_step_enabled'] = (bool)$removeCheckoutStepEnabled;
        }
        $clickAndCollectEnabled = $this->lsr->getClickCollectEnabled();
        $enabled = $this->lsr->isPickupTimeslotsEnabled();
        $deliveryHoursEnabled = $this->lsr->isDeliveryTimeslotsEnabled();
        if (empty($subject->basketHelper->getStorePickUpHoursFromCheckoutSession()) || !$clickAndCollectEnabled) {
            $enabled = 0;
        }
        if (empty($subject->basketHelper->getDeliveryHoursFromCheckoutSession())) {
            $deliveryHoursEnabled = 0;
        }
        if ($this->lsr->isDisableInventory() && $this->lsr->isHospitalityStore()) {
            $storesResponse = $subject->getRequiredStores();
            $stores = $storesResponse ? $storesResponse->toArray() : [];
            $layout = $subject->layoutFactory->create();
            $storesData = $layout->createBlock(Stores::class)
                ->setTemplate('Ls_Omni::stores/stores.phtml')
                ->setData('data', $storesResponse)
                ->setData('storeHours', 0)
                ->toHtml();
            $stores['storesInfo'] = $storesData;
            $encodedStores = Json::encode($stores);
            $result['shipping']['select_store'] ['stores'] = $encodedStores;
        }
        $result['shipping'] ['pickup_date_timeslots'] = [
            'options' => $subject->basketHelper->getStorePickUpHoursFromCheckoutSession(),
            'delivery_hours' => $subject->basketHelper->getDeliveryHoursFromCheckoutSession(),
            'enabled' => $enabled,
            'current_web_store' => $this->lsr->getActiveWebStore(),
            'store_type' => ($this->lsr->getCurrentIndustry() == LSR::LS_INDUSTRY_VALUE_HOSPITALITY) ?
                1 : 0,
            'delivery_hours_enabled' => $deliveryHoursEnabled
        ];

        return $result;
    }

    /**
     * After getting the configuration
     *
     * @param DataProvider $subject
     * @param string $result
     * @return string
     * @throws NoSuchEntityException
     */
    public function afterAvailableStoresOnlyEnabled(DataProvider $subject, string $result)
    {
        if ($subject->checkoutSession->getNoManageStock()) {
            $result = "1";
        }

        return $result;
    }
}
