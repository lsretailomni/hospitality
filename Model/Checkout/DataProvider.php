<?php

namespace Ls\Hospitality\Model\Checkout;

use \Ls\Hospitality\Model\LSR;
use Magento\Checkout\Model\Session;
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
    public $checkoutSession;

    /**
     * @param LSR $hospLsr
     * @param Session $checkoutSession
     */
    public function __construct(
        LSR $hospLsr,
        Session $checkoutSession
    ) {
        $this->hospLsr         = $hospLsr;
        $this->checkoutSession = $checkoutSession;
    }

    /**
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

        if(!empty($this->checkoutSession->getData(LSR::LS_ORDER_COMMENT))) {
           $comment =  $this->checkoutSession->getData(LSR::LS_ORDER_COMMENT);
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
