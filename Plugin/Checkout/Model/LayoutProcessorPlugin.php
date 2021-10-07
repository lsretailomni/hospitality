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
        if ($this->hospLsr->getCurrentIndustry() != \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            unset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shippingAdditional']['children']['ls-shipping-option-wrapper']['children']['shipping-option']['children']['service-mode']);
            unset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shippingAdditional']['children']['ls-shipping-option-wrapper']['children']['shipping-option']['children']['ls-enhance-pickup-additional-options-wrapper']);
        }
        if (!$this->hospLsr->isServiceModeEnabled()) {
            unset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shippingAdditional']['children']['ls-shipping-option-wrapper']['children']['shipping-option']['children']['service-mode']);
        }
        if (!($this->hospLsr->isPickupTimeslotsEnabled() && $this->hospLsr->isLSR($this->hospLsr->getCurrentStoreId()))) {
            unset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shippingAdditional']['children']['ls-shipping-option-wrapper']['children']['shipping-option']['children']['ls-enhance-pickup-additional-options-wrapper']);
        }
        return $jsLayout;
    }
}
