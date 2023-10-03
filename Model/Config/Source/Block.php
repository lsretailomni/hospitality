<?php

namespace Ls\Hospitality\Model\Config\Source;

use Magento\Cms\Model\Config\Source\Block as CmsBlock;

/**
 * Class Block for getting list of cms blocks
 */
class Block extends CmsBlock
{

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $optionsArray   = [];
        $optionsArray[] = ['value' => '', 'label' => __('Please select a static block')];
        $options        = parent::toOptionArray();
        if (!empty($options)) {
            foreach ($options as $option) {
                $optionsArray[] = ['value' => $option['value'], 'label' => $option['label']];
            }
        }
        return $optionsArray;
    }
}
