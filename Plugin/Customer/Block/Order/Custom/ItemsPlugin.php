<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Customer\Block\Order\Custom;

use \Ls\Hospitality\Model\LSR;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;

class ItemsPlugin
{
    /**
     * @param LSR $lsr
     */
    public function __construct(public LSR $lsr)
    {
    }

    /**
     * Around plugin to get order item collection from magento
     *
     * @param $subject
     * @param callable $proceed
     * @return DataObject[]
     * @throws NoSuchEntityException
     */
    public function aroundGetItems(
        $subject,
        callable $proceed
    ) {
        if (!$this->lsr->isHospitalityStore()) {
            return $proceed();
        }

        if ($subject->getMagOrder()) {
            $magentoOrder = $subject->getMagOrder();
            $order = $subject->getLscMemberSalesBuffer($subject->getOrder(true));

            if (!empty($magentoOrder) && !empty($order->getStoreCurrencyCode())) {
                if ($order->getStoreCurrencyCode() != $magentoOrder->getOrderCurrencyCode()) {
                    $magentoOrder = null;
                }
            }

            return $subject->itemCollection->getItems();
        }

        return $proceed();
    }
}
