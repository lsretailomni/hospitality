<?php

namespace Ls\Hospitality\Model\Checkout;

use \Ls\Hospitality\Model\LSR;
use Magento\Customer\Model\Session;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class DataProvider for passing values to checkout */
class DataProvider implements ConfigProviderInterface
{
    /**
     * @var LSR
     */
    public $hospLsr;

    /**
     * @var Session
     */
    public $customerSession;

    /**
     * @var CheckoutSession
     */
    public $checkoutSession;

    /**
     * @param LSR $hospLsr
     * @param Session $customerSession
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        LSR $hospLsr,
        Session $customerSession,
        CheckoutSession $checkoutSession
    ) {
        $this->hospLsr         = $hospLsr;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Get checkout config values
     *
     * @return array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getConfig()
    {
        $comment = '';

        if ($this->checkoutSession->getQuoteId()) {
            $comment = $this->checkoutSession->getQuote()->getData(LSR::LS_ORDER_COMMENT) ?: '';
        }

        if (!empty($this->customerSession->getData(LSR::LS_QR_CODE_ORDERING)) && empty($comment)) {
            $params = $this->customerSession->getData(LSR::LS_QR_CODE_ORDERING);
            foreach ($params as $key => $value) {
                $key     = ucfirst(str_replace('_', ' ', $key));
                $comment .= $key . ': ' . $value . PHP_EOL;
            }
            $orderSource = __('QR Code Ordering');
            if (!empty($comment)) {
                $comment .= __('Order Source:') . ' ' . $orderSource . PHP_EOL;
            }
        }

        return [
            'shipping'                       => [
                'service_mode' => [
                    'options' => $this->getServiceModeValues(),
                    'enabled' => $this->hospLsr->isServiceModeEnabled()
                ]
            ],
            'show_in_checkout'               => $this->hospLsr->canShowCommentInCheckout(),
            'max_length'                     => (int)$this->hospLsr->getMaximumCharacterLength(),
            'comment_initial_collapse_state' => (int)$this->hospLsr->getInitialCollapseState(),
            'existing_comment'               => $comment
        ];
    }

    /**
     * Getting service mode values
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getServiceModeValues()
    {
        $serviceOptions = [];
        $options        = $this->hospLsr->getServiceModeOptions();
        if (!empty($options)) {
            $optionsArray = explode(",", $options);
            foreach ($optionsArray as $optionValue) {
                $serviceOptions[$optionValue] = $optionValue;
            }
        }

        return $serviceOptions;
    }
}
