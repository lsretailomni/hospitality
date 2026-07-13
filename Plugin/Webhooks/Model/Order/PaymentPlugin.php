<?php

declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Webhooks\Model\Order;

use \Ls\Core\Model\LSR;
use \Ls\Webhooks\Helper\Data;
use \Ls\Webhooks\Model\Order\Payment;

/**
 * Builds the complete invoice amount for hospitality webhook invoices.
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
     * Preset the full invoice total for hospitality webhook invoices.
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

        // Only hospitality (non-retail) orders route their total through $data['Amount']; retail
        // drives its own amount, so leave it untouched. Mirrors generateInvoice's own industry check.
        $industry = $this->webhookHelper->getLsrObject()->getStoreConfig(
            LSR::LS_INDUSTRY_VALUE,
            $order->getStoreId()
        );
        if ($industry === LSR::LS_INDUSTRY_VALUE_RETAIL) {
            return [$data, $linesMerged, $magentoOrder];
        }

        // Do not change an Amount already provided upstream.
        if (!array_key_exists('Amount', $data)) {
            $linesTotal = 0.0;
            foreach ($data['Lines'] ?? [] as $line) {
                $linesTotal += (float)($line['Amount'] ?? 0);
            }
            // The tip is one-time, so only add it on the first invoice.
            $tipAmount = $order->hasInvoices() ? 0.0 : (float)$order->getLsTipAmount();
            $data['Amount'] = $linesTotal + $tipAmount;
        }

        return [$data, $linesMerged, $magentoOrder];
    }
}
