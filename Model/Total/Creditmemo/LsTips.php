<?php

declare(strict_types=1);

namespace Ls\Hospitality\Model\Total\Creditmemo;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

/**
 * Adds the hospitality tip amount to the credit memo grand total.
 *
 */
class LsTips extends AbstractTotal
{
    /**
     * Add the tip to the credit memo grand total, on the refund that completes the order.
     *
     * @param Creditmemo $creditmemo
     * @return $this
     */
    public function collect(Creditmemo $creditmemo)
    {
        parent::collect($creditmemo);

        $tipAmount = (float)$creditmemo->getOrder()->getLsTipAmount();

        if ($tipAmount <= 0 || !$creditmemo->isLast()) {
            return $this;
        }

        $creditmemo->setGrandTotal((float)$creditmemo->getGrandTotal() + $tipAmount);
        $creditmemo->setBaseGrandTotal((float)$creditmemo->getBaseGrandTotal() + $tipAmount);

        return $this;
    }
}