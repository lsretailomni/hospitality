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
}
