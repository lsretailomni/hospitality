<?php

namespace Ls\Hospitality\Model\Order\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Get collapse value for comment field
 */
class Collapse implements OptionSourceInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = $this->toArray();
        $result  = [];

        foreach ($options as $value => $label) {
            $result[] = [
                'value' => $value, 'label' => $label
            ];
        }

        return $result;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            0 => __('Field closed'),
            1 => __('Field opened'),
            2 => __('Field without collapse')
        ];
    }
}
