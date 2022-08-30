<?php

namespace Ls\Hospitality\Plugin\QuoteGraphQl\Resolver;

use \Ls\Hospitality\Model\LSR;
use \Ls\OmniGraphQl\Helper\DataHelper;
use \Ls\Replication\Model\ResourceModel\ReplStore\Collection;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;

/**
 * AvailablePaymentMethods plugin responsible for filtering payment methods based on click and collect configuration
 */
class AvailablePaymentMethodsPlugin
{
    /**
     * @var PaymentInformationManagementInterface
     */
    private $informationManagement;

    /**
     * @param PaymentInformationManagementInterface $informationManagement
     */
    public function __construct(
        PaymentInformationManagementInterface $informationManagement,
        LSR $lsr
    ) {
        $this->informationManagement = $informationManagement;
        $this->lsr                   = $lsr;
    }

    /**
     * Around plugin to filter click and collect stores based on configuration for takeaway
     *
     * @param AvailablePaymentMethods $subject
     * @param Collection $result
     * @param string $scopeId
     * @return array
     * @throws NoSuchEntityException
     */
    public function aroundGetPaymentMethodsData(
        AvailablePaymentMethods $subject,
        $result,
        CartInterface $cart
    ) {
        $paymentInformation                 = $this->informationManagement->getPaymentInformation($cart->getId());
        $paymentMethods                     = $paymentInformation->getPaymentMethods();
        $clickAndCollectPaymentMethodsArr   = [];
        $clickAndCollectActive              = $this->lsr->getStoreConfig(
            LSR::SC_CLICKCOLLECT_ACTIVE,
            $this->lsr->getStoreId()
        );
        $clickAndCollectPaymentMethods      = $this->lsr->getStoreConfig(
            LSR::SC_CLICKCOLLECT_PAYMENT_OPTION,
            $this->lsr->getStoreId()
        );
        $shippingMethod                     = $cart->getShippingAddress()->getShippingMethod();

        if ($shippingMethod == "clickandcollect_clickandcollect" &&
            $clickAndCollectActive &&
            $clickAndCollectPaymentMethods
        ) {
            $clickAndCollectPaymentMethodsArr = explode(",", $clickAndCollectPaymentMethods);
        }

        $paymentMethodsData = [];
        foreach ($paymentMethods as $paymentMethod) {
            if ($clickAndCollectActive && $shippingMethod == "clickandcollect_clickandcollect") {
                if (in_array($paymentMethod->getCode(), $clickAndCollectPaymentMethodsArr)) {
                    $paymentMethodsData[] = [
                        'title' => $paymentMethod->getTitle(),
                        'code' => $paymentMethod->getCode(),
                    ];
                }
            } else {
                $paymentMethodsData[] = [
                    'title' => $paymentMethod->getTitle(),
                    'code' => $paymentMethod->getCode(),
                ];
            }
        }

        return $paymentMethodsData;
    }
}
