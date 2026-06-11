<?php

declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Customer\Block\Order;

use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\NoSuchEntityException;

class HistoryPlugin
{
    /**
     * @param LSR $lsr
     */
    public function __construct(public LSR $lsr)
    {
    }


    /**
     * Around plugin to get document id for hospitality order
     *
     * @param $subject
     * @param $proceed
     * @param $_order
     * @return mixed|string
     * @throws NoSuchEntityException
     */
    public function aroundGetRequiredDocumentId($subject, $proceed, $_order)
    {
        if (!$this->lsr->isHospitalityStore()) {
            return $proceed($_order);
        }

        return !empty($_order->getDocumentId())
            ? $_order->getDocumentId() : '';
    }
}
