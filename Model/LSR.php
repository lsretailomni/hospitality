<?php

namespace Ls\Hospitality\Model;

use \Ls\Omni\Client\Ecommerce\Entity\Enum\KOTStatus;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;

/**
 * Hospitality LSR
 */
class LSR extends \Ls\Core\Model\LSR
{
    const LS_ITEM_IS_DEAL_ATTRIBUTE = 'lsr_is_deal';
    const LSR_ITEM_MODIFIER_PREFIX = 'ls_mod_';
    const LSR_RECIPE_PREFIX = 'ls_rec';

    //Hospitality configuration
    const SERVICE_MODE_ENABLED = 'ls_mag/hospitality/service_mode_status';
    const SERVICE_MODE_OPTIONS = 'ls_mag/hospitality/service_mode_options';
    const ORDER_TRACKING_ON_SUCCESS_PAGE = 'ls_mag/hospitality/order_tracking';
    const DELIVERY_SALES_TYPE = 'ls_mag/hospitality/delivery_salas_type';
    const TAKEAWAY_DELIVERY_TYPE = 'ls_mag/hospitality/takeaway_delivery_type';

    //For Item Modifiers in Hospitality
    const SC_SUCCESS_CRON_ITEM_MODIFIER = 'ls_mag/replication/success_process_item_modifier';
    const SC_ITEM_MODIFIER_CONFIG_PATH_LAST_EXECUTE = 'ls_mag/replication/last_execute_process_item_modifier';

    //For Item Recipes in Hospitality
    const SC_SUCCESS_CRON_ITEM_RECIPE = 'ls_mag/replication/success_process_item_recipe';
    const SC_ITEM_RECIPE_CONFIG_PATH_LAST_EXECUTE = 'ls_mag/replication/last_execute_process_item_recipe';

    //For Item Deals in Hospitality
    const SC_SUCCESS_CRON_ITEM_DEAL = 'ls_mag/replication/success_process_item_deal';
    const SC_ITEM_DEAL_CONFIG_PATH_LAST_EXECUTE = 'ls_mag/replication/last_execute_process_item_deal';

    const SC_REPLICATION_ITEM_MODIFIER_BATCH_SIZE = 'ls_mag/replication/item_modifier_batch_size';
    const SC_REPLICATION_ITEM_RECIPE_BATCH_SIZE = 'ls_mag/replication/item_recipe_batch_size';

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function isServiceModeEnabled()
    {
        return $this->scopeConfig->getValue(
            self::SERVICE_MODE_ENABLED,
            ScopeInterface::SCOPE_WEBSITES,
            $this->storeManager->getStore()->getWebsiteId()
        );
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getServiceModeOptions()
    {
        return $this->scopeConfig->getValue(
            self::SERVICE_MODE_OPTIONS,
            ScopeInterface::SCOPE_WEBSITES,
            $this->storeManager->getStore()->getWebsiteId()
        );
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function showOrderTrackingInfoOnSuccessPage()
    {
        return $this->scopeConfig->getValue(
            self::ORDER_TRACKING_ON_SUCCESS_PAGE,
            ScopeInterface::SCOPE_WEBSITES,
            $this->storeManager->getStore()->getWebsiteId()
        );
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getDeliverySalesType()
    {
        return $this->scopeConfig->getValue(
            self::DELIVERY_SALES_TYPE,
            ScopeInterface::SCOPE_WEBSITES,
            $this->storeManager->getStore()->getWebsiteId()
        );
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getTakeAwayDeliveryType()
    {
        return $this->scopeConfig->getValue(
            self::TAKEAWAY_DELIVERY_TYPE,
            ScopeInterface::SCOPE_WEBSITES,
            $this->storeManager->getStore()->getWebsiteId()
        );
    }

    /**
     * @param null $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isHospitalityStore($storeId = null)
    {
        //If StoreID is not passed they retrieve it from the global area.
        if ($storeId === null) {
            $storeId = $this->getCurrentStoreId();
        }

        return ($this->getCurrentIndustry($storeId) == self::LS_INDUSTRY_VALUE_HOSPITALITY);
    }

    /** For showing user friendly message to user regarding kitchen status
     * @return array
     */
    public function kitchenStatusMapping()
    {
        return [
            KOTStatus::NOT_SENT    => __("Order not sent to Kitchen"),
            KOTStatus::N_A_S_ERROR => __("Error from kitchen display system"),
            KOTStatus::K_D_S_ERROR => __("Error from kitchen display system"),
            KOTStatus::SENT        => __("Order sent to Kitchen"),
            KOTStatus::STARTED     => __("Preparing your order"),
            KOTStatus::FINISHED    => __("Finished preparing your order"),
            KOTStatus::SERVED      => __("Your order has been served"),
            KOTStatus::VOIDED      => __("Order is cancelled"),
            KOTStatus::POSTED      => __("Order completed"),
            KOTStatus::NONE        => ''
        ];
    }
}
