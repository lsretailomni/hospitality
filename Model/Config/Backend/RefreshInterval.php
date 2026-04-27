<?php

namespace Ls\Hospitality\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class RefreshInterval extends Value
{
    /**
     * Validate refresh interval value
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = (int)$this->getValue();

        if ($value < 10) {
            throw new LocalizedException(
                __('Kitchen status refresh interval must be at least 10 seconds.')
            );
        }

        return parent::beforeSave();
    }
}
