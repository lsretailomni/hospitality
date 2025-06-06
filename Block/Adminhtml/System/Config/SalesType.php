<?php

namespace Ls\Hospitality\Block\Adminhtml\System\Config;

use Ls\Omni\Block\Adminhtml\System\Config\Stores;

class SalesType extends Stores
{
    /**
     * Get ajax url
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('ls_repl/system_config/getSalestype');
    }
}
