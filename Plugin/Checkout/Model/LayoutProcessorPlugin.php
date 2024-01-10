<?php

namespace Ls\Hospitality\Plugin\Checkout\Model;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use Magento\Checkout\Block\Checkout\LayoutProcessor;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class LayoutProcessorPlugin
 * for enabling displaying checkout nodes
 */
class LayoutProcessorPlugin
{
    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @var LSR
     */
    public $hospLsr;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var CheckoutSession
     */
    public $checkoutSession;

    /**
     * @param LSR $hospLsr
     * @param HospitalityHelper $hospitalityHelper
     * @param StoreManagerInterface $storeManager
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        LSR $hospLsr,
        HospitalityHelper $hospitalityHelper,
        StoreManagerInterface $storeManager,
        CheckoutSession $checkoutSession
    ) {
        $this->hospLsr = $hospLsr;
        $this->hospitalityHelper = $hospitalityHelper;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * After plugin to unset and set required components for hospitality
     *
     * @param LayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterProcess(
        LayoutProcessor $subject,
        array $jsLayout
    ) {
        $shippingStep               = &$jsLayout['components']['checkout']['children']['steps']['children']['shipping-step'];
        $billingStep                = &$jsLayout['components']['checkout']['children']['steps']['children']['billing-step'];
        $shippingAdditionalChildren = &$shippingStep['children']['shippingAddress']['children']['shippingAdditional']['children'];

        if ($this->hospLsr->getCurrentIndustry() == \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            $storeId = $this->storeManager->getStore()->getId();

            $anonymousOrderEnabled = $this->hospLsr->getStoreConfig(
                Lsr::ANONYMOUS_ORDER_ENABLED,
                $storeId
            );
            $removeCheckoutStepEnabled = $this->hospitalityHelper->removeCheckoutStepEnabled();
            $this->processFormFields(
                $shippingStep,
                $billingStep,
                $anonymousOrderEnabled,
                $removeCheckoutStepEnabled,
                $storeId
            );

            if ($anonymousOrderEnabled || $removeCheckoutStepEnabled) {
                unset($jsLayout['components']['checkout']['children']['sidebar']['children']['shipping-information']);
            }
        }

        if (!$this->hospLsr->isEnabled()) {
            unset($shippingAdditionalChildren['ls-shipping-option-wrapper']);
            unset($billingStep['children']['payment']['children']['payments-list']['children']['before-place-order']['children']['comment']);
            unset($billingStep['children']['payment']['children']['additional-payment-validators']['children']['order-comment-validator']);
        }

        if ($this->hospLsr->getCurrentIndustry() != \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            unset($shippingAdditionalChildren['ls-shipping-option-wrapper']['children']['shipping-option']['children']['service-mode']);
            unset($billingStep['children']['payment']['children']['payments-list']['children']['before-place-order']['children']['comment']);
            unset($billingStep['children']['payment']['children']['additional-payment-validators']['children']['order-comment-validator']);
        }

        if (!$this->hospLsr->isServiceModeEnabled()) {
            unset($shippingAdditionalChildren['ls-shipping-option-wrapper']['children']['shipping-option']['children']['service-mode']);
        }

        if ($this->hospLsr->getCurrentIndustry() == \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY
            && $this->hospLsr->isPickupTimeslotsEnabled() &&
            $this->hospLsr->isLSR($this->hospLsr->getCurrentStoreId())) {
            $shippingAdditionalChildren['ls-pickup-additional-options-wrapper'] =
                [
                    'component' => 'Ls_Omni/js/view/checkout/shipping/pickup-date-time-block',
                    'provider' => 'checkoutProvider',
                    'sortOrder' => 1,
                    'children' => [
                        'shipping-option' => [
                            'component' => 'uiComponent',
                            'displayArea' => 'additionalShippingOptionField',
                            'children' => [
                                'pickup-date' => [
                                    'component' => 'Ls_Omni/js/view/checkout/shipping/pickup-date-options',
                                    'config' => [
                                        'customScope' => 'shippingOptionSelect',
                                        'id' => 'pickup-date',
                                        'template' => 'ui/form/field',
                                        'elementTmpl' => 'ui/form/element/select'
                                    ],
                                    'dataScope' => 'shippingOptionSelect.pickup-date',
                                    'label' => __('Pick up Date'),
                                    'provider' => 'checkoutProvider',
                                    'visible' => true,
                                    'validation' => [
                                        'required-entry' => true,
                                        'validate-no-empty' => true
                                    ]
                                ],
                                'pickup-timeslot' => [
                                    'component' => 'Ls_Omni/js/view/checkout/shipping/pickup-timeslot-options',
                                    'config' => [
                                        'customScope' => 'shippingOptionSelect',
                                        'id' => 'pickup-date',
                                        'template' => 'ui/form/field',
                                        'elementTmpl' => 'ui/form/element/select'
                                    ],
                                    'dataScope' => 'shippingOptionSelect.pickup-timeslot',
                                    'label' => __('Pick up Time'),
                                    'provider' => 'checkoutProvider',
                                    'visible' => true,
                                    'validation' => [
                                        'required-entry' => true,
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

    /**
     * Process form fields
     *
     * @param $shippingStep
     * @param $billingStep
     * @param $anonymousOrderEnabled
     * @param $removeCheckoutStepEnabled
     * @param $storeId
     * @return void
     * @throws NoSuchEntityException
     */
    public function processFormFields(
        &$shippingStep,
        &$billingStep,
        $anonymousOrderEnabled,
        $removeCheckoutStepEnabled,
        $storeId
    ) {
        if ($anonymousOrderEnabled || $removeCheckoutStepEnabled) {
            if ($removeCheckoutStepEnabled) {
                $anonymousOrderRequiredAttributes = [];
            } else {
                $anonymousOrderRequiredAttributes = $this->hospitalityHelper->getformattedAddressAttributesConfig(
                    $storeId
                );
            }

            $prefillAttributes                = $this->hospitalityHelper->getAnonymousOrderPrefillAttributes(
                $anonymousOrderRequiredAttributes
            );
            $anonymousAddress = $this->hospitalityHelper->getAnonymousAddress($prefillAttributes);

            if ($anonymousOrderEnabled) {
                $shippingAddressFieldSet =
                &$shippingStep['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];
                $this->hideNotRequiredAddressAttributes(
                    $shippingAddressFieldSet,
                    $anonymousOrderRequiredAttributes,
                    $anonymousAddress
                );
            }

            $paymentsList = &$billingStep['children']['payment']['children']['payments-list']['children'];

            foreach ($paymentsList as &$payment) {
                if (isset($payment['children']['form-fields'])) {
                    $this->hideNotRequiredAddressAttributes(
                        $payment['children']['form-fields']['children'],
                        $anonymousOrderRequiredAttributes,
                        $anonymousAddress
                    );
                }
            }
        }
    }

    /**
     * Hide not required address attributes
     *
     * @param $formFields
     * @param $anonymousOrderRequiredAttributes
     * @param $address
     * @return void
     */
    public function hideNotRequiredAddressAttributes(
        &$formFields,
        $anonymousOrderRequiredAttributes,
        $address
    ) {
        foreach ($formFields as $index => &$field) {
            $value = $address->getData($index);

            if (!isset($anonymousOrderRequiredAttributes[$index])) {
                if ($index == 'street') {
                    foreach ($field['children'] as $i => &$line) {
                        if (isset(explode(',', $value)[$i])) {
                            $line['value'] = explode(',', $value)[$i];
                        }
                    }
                } else {
                    $field['value'] = $value;
                }

                $field['visible'] = false;
            } else {
                if ($anonymousOrderRequiredAttributes[$index] == "0") {
                    $field['value'] = $value;
                    $field['notice'] =
                        __('This is an autofilled field. Please leave it as it is if you don\'t want to change it.');
                }
            }
        }
    }
}
