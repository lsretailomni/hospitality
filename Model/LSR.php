<?php

namespace Ls\Hospitality\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;

/**
 * Hospitality LSR
 */
class LSR extends \Ls\Core\Model\LSR
{
    const LSR_ITEM_MODIFIER_PREFIX = 'ls_mod_';
    const LSR_RECIPE_PREFIX = 'ls_rec';
    const SERVICE_MODE_ENABLED = 'ls_mag/service_mode/status';
    const SERVICE_MODE_OPTIONS = 'ls_mag/service_mode/options';

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
}
