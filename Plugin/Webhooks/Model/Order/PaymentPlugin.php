<?php

declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Webhooks\Model\Order;

use Ls\Webhooks\Helper\Data;
use Ls\Webhooks\Model\Order\Payment;

/**
 * Includes the hospitality tip in the invoice amount built from LS Central order lines.
 */
class PaymentPlugin
{
    /**
     * @param Data $webhookHelper
     */
    public function __construct(
        private Data $webhookHelper
    ) {
    }

    /**
     * Add the hospitality tip to the invoice amount for offline (e.g. pay at store) orders.
     *
     *
     * @param Payment $subject
     * @param array $data
     * @param bool $linesMerged
     * @param mixed $magentoOrder
     * @return array
     */
    public function beforeGenerateInvoice(
        Payment $subject,
        $data,
        $linesMerged = true,
        $magentoOrder = null
    ) {
        $order = !empty($magentoOrder) ? $magentoOrder :
            (empty($data['OrderId']) ? null : $this->webhookHelper->getOrderByDocumentId($data['OrderId']));

        if (empty($order) || is_array($order)) {
            return [$data, $linesMerged, $magentoOrder];
        }

        $tipAmount = (float)$order->getLsTipAmount();

        if ($tipAmount > 0 && $order->getPayment()->getMethodInstance()->isOffline()) {
            $data['Amount'] = $tipAmount;
        }

        return [$data, $linesMerged, $magentoOrder];
    }
}
