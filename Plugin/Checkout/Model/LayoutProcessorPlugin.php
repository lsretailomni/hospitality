<?php

namespace Ls\Hospitality\Plugin\Checkout\Model;

use \Ls\Hospitality\Model\LSR;
use Magento\Checkout\Block\Checkout\LayoutProcessor;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class LayoutProcessorPlugin
 * for enabling displaying checkout nodes
 */
class LayoutProcessorPlugin
{

    /**
     * @var LSR
     */
    public $hospLsr;

    /**
     * LayoutProcessorPlugin constructor.
     * @param LSR $hospLsr
     */
    public function __construct(
        LSR $hospLsr
    ) {
        $this->hospLsr = $hospLsr;
    }

    /**
     * @param LayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterProcess(
        LayoutProcessor $subject,
        array $jsLayout
    ) {
        $pickupDateTimeOptions = &$jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shippingAdditional']['children'];
        if ($this->hospLsr->getCurrentIndustry() != \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            unset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shippingAdditional']['children']['ls-shipping-option-wrapper']['children']['shipping-option']['children']['service-mode']);
            unset($jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['payments-list']['children']['before-place-order']['children']['comment']);
        }
        if (!$this->hospLsr->isServiceModeEnabled()) {
            unset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shippingAdditional']['children']['ls-shipping-option-wrapper']['children']['shipping-option']['children']['service-mode']);
        }

        if ($this->hospLsr->getCurrentIndustry() == \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY
            && $this->hospLsr->isPickupTimeslotsEnabled() &&
            $this->hospLsr->isLSR($this->hospLsr->getCurrentStoreId())) {
            $pickupDateTimeOptions['ls-pickup-additional-options-wrapper'] =
                [
                    'component' => 'Ls_Omni/js/view/checkout/shipping/pickup-date-time-block',
                    'provider'  => 'checkoutProvider',
                    'sortOrder' => 1,
                    'children'  => [
                        'shipping-option' => [
                            'component'   => 'uiComponent',
                            'displayArea' => 'additionalShippingOptionField',
                            'children'    => [
                                'pickup-date'     => [
                                    'component'  => 'Ls_Omni/js/view/checkout/shipping/pickup-date-options',
                                    'config'     => [
                                        'customScope' => 'shippingOptionSelect',
                                        'id'          => 'pickup-date',
                                        'template'    => 'ui/form/field',
                                        'elementTmpl' => 'ui/form/element/select'
                                    ],
                                    'dataScope'  => 'shippingOptionSelect.pickup-date',
                                    'label'      => __('Pick up Date'),
                                    'provider'   => 'checkoutProvider',
                                    'visible'    => true,
                                    'validation' => [
                                        'required-entry'    => true,
                                        'validate-no-empty' => true
                                    ]
                                ],
                                'pickup-timeslot' => [
                                    'component'  => 'Ls_Omni/js/view/checkout/shipping/pickup-timeslot-options',
                                    'config'     => [
                                        'customScope' => 'shippingOptionSelect',
                                        'id'          => 'pickup-date',
                                        'template'    => 'ui/form/field',
                                        'elementTmpl' => 'ui/form/element/select'
                                    ],
                                    'dataScope'  => 'shippingOptionSelect.pickup-timeslot',
                                    'label'      => __('Pick up Time'),
                                    'provider'   => 'checkoutProvider',
                                    'visible'    => true,
                                    'validation' => [
                                        'required-entry'    => true,
                                        'validate-no-empty' => true
                                    ]
                                ]

                            ]
                        ]
                    ]
                ];
        }

        return $jsLayout;
    }
}
