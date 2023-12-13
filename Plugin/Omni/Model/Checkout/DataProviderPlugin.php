<?php

namespace Ls\Hospitality\Plugin\Omni\Model\Checkout;

use \Ls\Core\Model\LSR as LSRAlias;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Helper\StoreHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Model\Checkout\DataProvider;
use \Ls\Replication\Model\ResourceModel\ReplStore\Collection;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var CheckoutSession
     */
    public $checkoutSession;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @param StoreHelper $storeHelper
     * @param LSR $lsr
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        StoreHelper $storeHelper,
        LSR $lsr,
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->storeHelper     = $storeHelper;
        $this->lsr             = $lsr;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * Around plugin for intercepting get stores function
     *
     * @param DataProvider $subject
     * @param callable $proceed
     * @return Collection
     * @throws NoSuchEntityException|LocalizedException
     */
    public function aroundGetStores(
        DataProvider $subject,
        callable $proceed
    ) {
        $salesTypeStoreIdArray = [];
        $storeHoursArray       = [];

        if ($subject->lsr->getCurrentIndustry($subject->getStoreId()) != LSRAlias::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed();
        }
        $takeAwaySalesType = $this->lsr->getTakeAwaySalesType();
        $allStores         = $this->storeHelper->getAllStores($subject->getStoreId());

        foreach ($allStores as $store) {
            if ($this->checkSalesType($store->getHospSalesTypes()->getSalesType(), $takeAwaySalesType) &&
                $store->getIsClickAndCollect()) {
                $webStoreId              = $store->getId();
                $salesTypeStoreIdArray[] = $webStoreId;
                if ($this->lsr->isPickupTimeslotsEnabled()) {
                    $storeHoursArray[$webStoreId] = $this->storeHelper->formatDateTimeSlotsValues(
                        $store->getStoreHours()
                    );
                }
            }
        }

        if (!empty($salesTypeStoreIdArray)) {
            if (!empty($storeHoursArray)) {
                $this->checkoutSession->setStorePickupHours($storeHoursArray);
            }
            $this->checkoutSession->setNoManageStock(0);
            $items = $this->checkoutSession->getQuote()->getAllVisibleItems();
            list($response) = $subject->stockHelper->getGivenItemsStockInGivenStore($items);
            if (!$subject->availableStoresOnlyEnabled()) {
                return $subject->storeCollectionFactory
                    ->create()
                    ->addFieldToFilter('nav_id', [
                        'in' => implode(',', $salesTypeStoreIdArray),
                    ])
                    ->addFieldToFilter('scope_id', $subject->getStoreId())
                    ->addFieldToFilter('ClickAndCollect', 1);
            } else {
                if ($response) {
                    if (is_object($response)) {
                        if (!is_array($response->getInventoryResponse())) {
                            $response = [$response->getInventoryResponse()];
                        } else {
                            $response = $response->getInventoryResponse();
                        }
                    }
                }

                $subject->filterClickAndCollectStores($response, $salesTypeStoreIdArray);
            }

            return $subject->filterStoresOnTheBasisOfQty($response, $items);
        }

        return $proceed();
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
        if ($subject->lsr->getCurrentIndustry($subject->getStoreId()) == LSRAlias::LS_INDUSTRY_VALUE_HOSPITALITY
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
            $storeId = $this->storeManager->getStore()->getId();

            $anonymousOrderEnabled = $this->lsr->getStoreConfig(
                Lsr::ANONYMOUS_ORDER_ENABLED,
                $storeId
            );
            $removeCheckoutStepEnabled = $this->hospitalityHelper->removeCheckoutStepEnabled();

            $anonymousOrderRequiredAttributes = $this->hospitalityHelper->getformattedAddressAttributesConfig(
                $storeId
            );
            $result['anonymous_order']['is_enabled'] = (bool) $anonymousOrderEnabled;
            $result['anonymous_order']['required_fields'] = $anonymousOrderRequiredAttributes;
            $result['remove_checkout_step_enabled'] = (bool) $removeCheckoutStepEnabled;
        }

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
