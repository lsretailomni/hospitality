<?php

namespace Ls\Hospitality\Plugin\Config\Backend;

use \Ls\Core\Model\Config\Backend\SingleStoreMode;
use \Ls\Hospitality\Model\LSR;

/**
 * Before plugin for adding extra system.xml fields for hospitality
 */
class SingleStoreModePlugin
{

    /**
     * Set parameters for the configuration that need update when single store setup change
     *
     * @param SingleStoreMode $subject
     * @return SingleStoreMode[]
     */
    public function beforeAfterSave(SingleStoreMode $subject)
    {
        array_push($subject->tableCoreConfig,
            LSR::DELIVERY_SALES_TYPE,
            LSR::TAKEAWAY_SALES_TYPE,
            LSR::SERVICE_MODE_ENABLED,
            LSR::SERVICE_MODE_OPTIONS,
            LSR::ORDER_TRACKING_ON_SUCCESS_PAGE
        );

        return [$subject];
    }
}
