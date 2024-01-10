<?php

namespace Ls\Hospitality\Model\Order\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ShipmentKotStatus implements OptionSourceInterface
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
            'Not Sent' => __('Not Sent'),
            'NAS Error' => __('NAS Error'),
            'KDS Error' => __('KDS Error'),
            'Sent' => __('Sent'),
            'Started' => __('Started'),
            'Finished' => __('Finished'),
            'Served' => __('Served'),
        ];
    }
}
