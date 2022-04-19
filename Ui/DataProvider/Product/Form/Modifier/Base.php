<?php
namespace Ls\Hospitality\Ui\DataProvider\Product\Form\Modifier;

use Ls\Hospitality\Model\Catalog\Image;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\CustomOptions;
use Magento\Ui\Component\Form\Element\Input;
use Magento\Ui\Component\Form\Element\DataType\Text;
use Magento\Ui\Component\Form\Field;

class Base extends AbstractModifier
{
    public const SWATCH_FIELD_DATA_SCOPE = 'swatch';
    public const SWATCH_FIELD_PREVIEW_DATA_SCOPE = 'swatch_hidden';
    public const SWATCH_FIELD_TITLE = 'Swatch';
    /**
     * @var array
     */
    protected $meta = [];

    /**
     * {@inheritdoc}
     */
    public function modifyData(array $data)
    {
        foreach ($data as &$custom) {
            if (isset($custom['product']) && isset($custom['product']['options'])) {
                foreach ($custom['product']['options'] as &$option) {
                    if (isset($option[self::SWATCH_FIELD_DATA_SCOPE])) {
                        $option[self::SWATCH_FIELD_PREVIEW_DATA_SCOPE] = $option[self::SWATCH_FIELD_DATA_SCOPE];
                    }

                    foreach ($option['values'] as &$value) {
                        if (isset($value[self::SWATCH_FIELD_DATA_SCOPE])) {
                            $value[self::SWATCH_FIELD_PREVIEW_DATA_SCOPE] = $value[self::SWATCH_FIELD_DATA_SCOPE];
                        }
                    }
                }
            }
        }
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function modifyMeta(array $meta)
    {
        $this->meta = $meta;

        $this->addFields();

        return $this->meta;
    }

    /**
     * Adds fields to the meta-data
     */
    protected function addFields()
    {
        $groupCustomOptionsName    = CustomOptions::GROUP_CUSTOM_OPTIONS_NAME;
        $optionContainerName       = CustomOptions::CONTAINER_OPTION;
        $commonOptionContainerName = CustomOptions::CONTAINER_COMMON_NAME;

        // Add fields to the option
        $this->meta[$groupCustomOptionsName]['children']['options']['children']['record']['children']
        [$optionContainerName]['children'][$commonOptionContainerName]['children'] = array_replace_recursive(
            $this->meta[$groupCustomOptionsName]['children']['options']['children']['record']['children']
            [$optionContainerName]['children'][$commonOptionContainerName]['children'],
            $this->getOptionFieldsConfig()
        );

        // Add fields to the values
        $this->meta[$groupCustomOptionsName]['children']['options']['children']['record']['children']
        [$optionContainerName]['children']['values']['children']['record']['children'] = array_replace_recursive(
            $this->meta[$groupCustomOptionsName]['children']['options']['children']['record']['children']
            [$optionContainerName]['children']['values']['children']['record']['children'],
            $this->getValueFieldsConfig()
        );
    }

    /**
     * The custom option fields config
     *
     * @return array
     */
    protected function getOptionFieldsConfig()
    {
        $fields[self::SWATCH_FIELD_PREVIEW_DATA_SCOPE] = $this->getHiddenSwatchFieldConfig();
        $fields[self::SWATCH_FIELD_DATA_SCOPE] = $this->getOptionSwatchFieldConfig();

        return $fields;
    }

    /**
     * The custom option fields config
     *
     * @return array
     */
    protected function getValueFieldsConfig()
    {
        $fields[self::SWATCH_FIELD_PREVIEW_DATA_SCOPE] = $this->getHiddenSwatchFieldConfig();
        $fields[self::SWATCH_FIELD_DATA_SCOPE] = $this->getOptionSwatchFieldConfig();

        return $fields;
    }

    /**
     * Get Option Swatch field config
     *
     * @return array
     */
    public function getOptionSwatchFieldConfig()
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label'         => __('Swatch'),
                        'componentType' => Field::NAME,
                        'formElement'   => Image::NAME,
                        'dataScope'     => self::SWATCH_FIELD_DATA_SCOPE,
                        'dataType'      => Image::NAME,
                        'sortOrder'     => 50,
                        'additionalClasses'  => 'awesome-upload',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get Hidden Swatch Field Config
     *
     * @return \array[][][]
     */
    public function getHiddenSwatchFieldConfig()
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'additionalClasses'  => 'preview-icon',
                        'componentType' => Field::NAME,
                        'formElement'   => Input::NAME,
                        'dataScope'     => self::SWATCH_FIELD_PREVIEW_DATA_SCOPE,
                        'dataType'      => Text::NAME,
                        'sortOrder'     => 49,
                    ],
                ],
            ],
        ];
    }
}
