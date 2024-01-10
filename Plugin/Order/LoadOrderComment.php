<?php
namespace Ls\Hospitality\Plugin\Order;

use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Api\Data\OrderExtensionFactory;

/**
 * class order comment
 */
class LoadOrderComment
{
    /**
     * @var OrderFactory
     */
    public $orderFactory;

    /**
     * @var OrderExtensionFactory
     */
    public $orderExtensionFactory;

    /**
     * @param OrderFactory $orderFactory
     * @param OrderExtensionFactory $extensionFactory
     */
    public function __construct(
        OrderFactory $orderFactory,
        OrderExtensionFactory $extensionFactory
    ) {
        $this->orderFactory = $orderFactory;
        $this->orderExtensionFactory = $extensionFactory;
    }

    /**
     * Getting order comment
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $resultOrder
     * @return OrderInterface
     */
    public function afterGet(
        OrderRepositoryInterface $subject,
        OrderInterface $resultOrder
    ) {
        $this->setOrderComment($resultOrder);
        return $resultOrder;
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $orderSearchResult
     * @return OrderSearchResultInterface
     */
    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $orderSearchResult
    ) {
        foreach ($orderSearchResult->getItems() as $order) {
            $this->setOrderComment($order);
        }
        return $orderSearchResult;
    }

    /**
     * Setting order comment
     *
     * @param OrderInterface $order
     */
    public function setOrderComment(OrderInterface $order)
    {
        if ($order instanceof \Magento\Sales\Model\Order) {
            $value = $order->getLsOrderComment();
        } else {
            $temp = $this->getOrderFactory()->create();
            $temp->load($order->getId());
            $value = $temp->getLsOrderComment();
        }

        $extensionAttributes = $order->getExtensionAttributes();
        $orderExtension = $extensionAttributes ? $extensionAttributes : $this->getOrderExtensionFactory()->create();
        $orderExtension->setLsOrderComment($value);
        $order->setExtensionAttributes($orderExtension);
    }

    /**
     * Get order factory
     *
     * @return OrderFactory
     */
    public function getOrderFactory()
    {
        return $this->orderFactory;
    }

    /**
     * Get order extension factory
     *
     * @return OrderExtensionFactory
     */
    public function getOrderExtensionFactory()
    {
        return $this->orderExtensionFactory;
    }
}
