<?php

namespace Ls\Hospitality\Plugin\Block\OnePage;

use \Ls\Customer\Block\Onepage\Success;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\NoSuchEntityException;

class SuccessPlugin
{
    /**
     * @var LSR
     */
    private $lsr;

    /**
     * SuccessPlugin constructor.
     * @param LSR $lsr
     */
    public function __construct(
        LSR $lsr
    ) {
        $this->lsr = $lsr;
    }

    /**
     * For displaying order id
     *
     * @param Success $subject
     * @param callable $proceed
     * @return void
     * @throws NoSuchEntityException
     */
    public function aroundPrepareBlockData(Success $subject, callable $proceed)
    {
        $proceed();

        if (!$this->lsr->isHospitalityStore()) {
            return;
        }

        $orderId = $subject->getCheckoutSession()->getLastRealOrder()->getLsOrderId();

        if ($orderId) {
            $subject->addData(
                [
                    'order_id' => $orderId
                ]
            );
        } else {
            $subject->addData(
                [
                    'order_id' => null
                ]
            );
        }
    }
}
