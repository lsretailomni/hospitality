<?php

namespace Ls\Hospitality\Plugin\Quote\Model;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Plugin to store basket_response in the quote table
 */
class QuoteRepositoryPlugin
{
    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @var LSR
     */
    private $hospitalityLsr;

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param LSR $hospitalityLsr
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        LSR $hospitalityLsr
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
        $this->hospitalityLsr = $hospitalityLsr;
    }

    /**
     * Before saving quote setting prefilled address attributes
     *
     * @param $subject
     * @param CartInterface $quote
     * @return CartInterface[]
     * @throws NoSuchEntityException
     */
    public function beforeSave($subject, CartInterface $quote)
    {
        $isHospitalityStore = $this->hospitalityLsr->isHospitalityStore();
        $anonymousOrderEnabled = $this->hospitalityLsr->getStoreConfig(
            Lsr::ANONYMOUS_ORDER_ENABLED,
            $quote->getStoreId()
        );
        $removeCheckoutStepEnabled = $this->hospitalityLsr->getStoreConfig(
            Lsr::ANONYMOUS_REMOVE_CHECKOUT_STEPS,
            $quote->getStoreId()
        );
        if ($isHospitalityStore && ($anonymousOrderEnabled || $removeCheckoutStepEnabled)) {
            if ($removeCheckoutStepEnabled) {
                $anonymousOrderRequiredAttributes = [];
            } else {
                $anonymousOrderRequiredAttributes = $this->hospitalityHelper->getformattedAddressAttributesConfig(
                    $quote->getStoreId()
                );
            }

            $prefillAttributes                = $this->hospitalityHelper->getAnonymousOrderPrefillAttributes(
                $anonymousOrderRequiredAttributes
            );

            if (!empty($prefillAttributes)) {
                $anonymousAddress = $this->hospitalityHelper->getAnonymousAddress($prefillAttributes);
                if ($anonymousOrderEnabled) {
                    $quote->setShippingAddress($anonymousAddress);
                }
                $quote->setBillingAddress($anonymousAddress);
            }

            if ($anonymousOrderEnabled) {
                if (((isset($anonymousOrderRequiredAttributes['email']) &&
                        $anonymousOrderRequiredAttributes['email'] == '0')) ||
                    !isset($anonymousOrderRequiredAttributes['email'])
                ) {
                    $quote->setCustomerEmail($this->hospitalityHelper->getAnonymousOrderCustomerEmail());
                }
            }

            if ($removeCheckoutStepEnabled) {
                $quote->setCustomerEmail($this->hospitalityHelper->getAnonymousOrderCustomerEmail());
            }
            $quote->getShippingAddress()->setCollectShippingRates(true);
        }

        return [$quote];
    }
}
