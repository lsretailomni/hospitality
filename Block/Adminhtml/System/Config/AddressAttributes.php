<?php

namespace Ls\Hospitality\Block\Adminhtml\System\Config;

use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\Data\OptionSourceInterface;

class AddressAttributes implements OptionSourceInterface
{
    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * @inheritDoc
     *
     * @return array
     */
    public function toOptionArray()
    {
        $addressAttributes[] = [
            'value' => '',
            'label' => __('-- Please Select --')
        ];
        $attributes = $this->hospitalityHelper->getAllAddressAttributes();

        foreach ($attributes as $attribute) {
            $addressAttributes[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getDefaultFrontendLabel()
            ];
        }

        return $addressAttributes;
    }
}
