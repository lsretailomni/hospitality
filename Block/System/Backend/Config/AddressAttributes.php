<?php

declare(strict_types=1);

namespace Ls\Hospitality\Block\System\Backend\Config;

use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Data\Form\Element\Factory as ElementFactory;

class AddressAttributes extends AbstractFieldArray
{
    /**
     * @var ElementFactory
     */
    private $elementFactory;

    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @param Context $context
     * @param ElementFactory $elementFactory
     * @param HospitalityHelper $hospitalityHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        ElementFactory $elementFactory,
        HospitalityHelper $hospitalityHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->hospitalityHelper = $hospitalityHelper;
        $this->elementFactory = $elementFactory;
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->addColumn('address_attribute_code', [
            'label' => __('Address Attribute'),
        ]);

        $this->addColumn('is_required', [
            'label' => __('Is Required'),
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add new address attribute');

        parent::_construct();
    }

    /**
     * @inheritDoc
     */
    public function renderCellTemplate($columnName): string
    {
        if ($columnName === 'address_attribute_code' && isset($this->_columns[$columnName])) {
            return $this->renderSelectBox($columnName);
        }

        if ($columnName === 'is_required' && isset($this->_columns[$columnName])) {
            return $this->renderCheckbox($columnName);
        }

        return parent::renderCellTemplate($columnName);
    }

    /**
     * Render Select box element
     *
     * @param string $columnName
     *
     * @return string
     */
    private function renderSelectBox(string $columnName): string
    {
        return $this->elementFactory
            ->create('select')
            ->setForm($this->getData('form'))
            ->setData('name', $this->_getCellInputElementName($columnName))
            ->setData('html_id', $this->_getCellInputElementId('<%- _id %>', $columnName))
            ->setData('values', $this->toOptionArray())
            ->getElementHtml();
    }

    /**
     * Render checkbox element
     *
     * @param string $columnName
     *
     * @return string
     */
    private function renderCheckbox(string $columnName): string
    {
        return $this->elementFactory
            ->create('checkbox')
            ->setForm($this->getData('form'))
            ->setData('name', $this->_getCellInputElementName($columnName))
            ->setData('html_id', $this->_getCellInputElementId('<%- _id %>', $columnName))
            ->setData('value', 1)
            ->getElementHtml();
    }

    /**
     * Get all address attributes
     *
     * @return array
     */
    public function toOptionArray()
    {
        $addressAttributes[] = [
            'value' => '',
            'label' => __('-- Please Select --')
        ];

        $addressAttributes[] = ['value' => 'email', 'label' => 'Email Address'];

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
