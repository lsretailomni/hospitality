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
     * After plugin to remove unnecessary components based on the industry
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
        $shippingStep = &$jsLayout['components']['checkout']['children']['steps']['children']['shipping-step'];
        $billingStep  = &$jsLayout['components']['checkout']['children']['steps']['children']['billing-step'];

        if ($this->hospLsr->getCurrentIndustry() != \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            unset($shippingStep['children']['shippingAddress']['children']['shippingAdditional']['children']['ls-shipping-option-wrapper']['children']['shipping-option']['children']['service-mode']);
            unset($shippingStep['children']['shippingAddress']['children']['shippingAdditional']['children']['ls-pickup-additional-options-wrapper']);
            unset($billingStep['children']['payment']['children']['payments-list']['children']['before-place-order']['children']['comment']);
            unset($billingStep['children']['payment']['children']['additional-payment-validators']['children']['order-comment-validator']);
        }

        if (!$this->hospLsr->isServiceModeEnabled()) {
            unset($shippingStep['children']['shippingAddress']['children']['shippingAdditional']['children']['ls-shipping-option-wrapper']['children']['shipping-option']['children']['service-mode']);
        }

        if (!($this->hospLsr->isPickupTimeslotsEnabled() && $this->hospLsr->isLSR($this->hospLsr->getCurrentStoreId()))) {
            unset($shippingStep['children']['shippingAddress']['children']['shippingAdditional']['children']['ls-pickup-additional-options-wrapper']);
        }

        return $jsLayout;
    }
}
