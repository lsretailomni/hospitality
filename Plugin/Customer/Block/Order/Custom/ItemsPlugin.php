<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Customer\Block\Order\Custom;

use \Ls\Customer\Block\Order\Custom\Items;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;

class ItemsPlugin
{
    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @param LSR $lsr
     */
    public function __construct(LSR $lsr)
    {
        $this->lsr = $lsr;
    }

    /**
     * Around plugin to get order item collection from magento
     *
     * @param Items $subject
     * @param callable $proceed
     * @return DataObject[]
     * @throws NoSuchEntityException
     */
    public function aroundGetItems(
        Items $subject,
        callable $proceed
    ) {
        if (!$this->lsr->isHospitalityStore()) {
            return $proceed();
        }

        if ($subject->getMagOrder()) {
            $magentoOrder = $subject->getMagOrder();
            $order = $subject->getOrder(true);

            if (!empty($magentoOrder) && !empty($order->getStoreCurrency())) {
                if ($order->getStoreCurrency() != $magentoOrder->getOrderCurrencyCode()) {
                    $magentoOrder = null;
                }
            }

            return $subject->itemCollection->getItems();
        }

        return $proceed();
    }
}
