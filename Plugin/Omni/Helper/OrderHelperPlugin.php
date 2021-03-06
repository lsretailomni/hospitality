<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use Exception;
use \Ls\Core\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\DocumentIdType;
use \Ls\Omni\Client\Ecommerce\Operation;
use \Ls\Omni\Client\ResponseInterface;
use \Ls\Omni\Exception\InvalidEnumException;
use \Ls\Omni\Helper\OrderHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * OrderHelper plugin responsible for intercepting required methods
 */
class OrderHelperPlugin
{
    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var DateTime
     */
    public $date;

    /**
     * OrderHelperPlugin constructor.
     * @param DateTime $date
     * @param LoggerInterface $logger
     */
    public function __construct(
        DateTime $date,
        LoggerInterface $logger
    ) {
        $this->date   = $date;
        $this->logger = $logger;
    }

    /**
     * Around plugin for preparing the order request
     *
     * @param OrderHelper $subject
     * @param callable $proceed
     * @param Model\Order $order
     * @param $oneListCalculateResponse
     * @return Entity\OrderHospCreate
     */
    public function aroundPrepareOrder(OrderHelper $subject, callable $proceed, Model\Order $order, $oneListCalculateResponse)
    {
        try {
            if ($subject->lsr->getCurrentIndustry($order->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
                return $proceed($order, $oneListCalculateResponse);
            }
            $storeId = $oneListCalculateResponse->getStoreId();
            $cardId  = $oneListCalculateResponse->getCardId();
            /** Entity\ArrayOfOrderPayment $orderPaymentArrayObject */
            $orderPaymentArrayObject = $subject->setOrderPayments($order, $cardId);

            if (!empty($subject->checkoutSession->getCouponCode())) {
                $order->setCouponCode($subject->checkoutSession->getCouponCode());
            }

            $oneListCalculateResponse
                ->setCardId($cardId)
                ->setStoreId($storeId)
                ->setRestaurantNo($storeId)
                ->setPickUpTime($this->date->date("Y-m-d\T00:00:00"));
            $oneListCalculateResponse->setOrderPayments($orderPaymentArrayObject);
            $orderLinesArray = $oneListCalculateResponse->getOrderLines()->getOrderHospLine();
            //For click and collect we need to remove shipment charge orderline
            //For flat shipment it will set the correct shipment value into the order
            $orderLinesArray = $subject->updateShippingAmount($orderLinesArray, $order);
            // @codingStandardsIgnoreLine
            $request = new Entity\OrderHospCreate();
            $oneListCalculateResponse->setOrderLines($orderLinesArray);
            $request->setRequest($oneListCalculateResponse);

            return $request;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Around plugin for updating shipping amount
     *
     * @param OrderHelper $subject
     * @param callable $proceed
     * @param $orderLines
     * @param $order
     * @return mixed
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     */
    public function aroundUpdateShippingAmount(OrderHelper $subject, callable $proceed, $orderLines, $order)
    {
        if ($subject->lsr->getCurrentIndustry($order->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($orderLines, $order);
        }

        $shipmentFeeId = $subject->lsr->getStoreConfig(LSR::LSR_SHIPMENT_ITEM_ID, $order->getStoreId());

        if ($order->getShippingAmount() > 0) {
            // @codingStandardsIgnoreLine
            $shipmentOrderLine = new Entity\OrderHospLine();
            $shipmentOrderLine->setPrice($order->getShippingAmount())
                ->setNetPrice($order->getBaseShippingAmount())
                ->setNetAmount($order->getBaseShippingAmount())
                ->setAmount($order->getBaseShippingAmount())
                ->setItemId($shipmentFeeId)
                ->setLineType(Entity\Enum\LineType::ITEM)
                ->setQuantity(1)
                ->setDiscountAmount($order->getShippingDiscountAmount());
            array_push($orderLines, $shipmentOrderLine);
        }

        return $orderLines;
    }

    /**
     * Around plugin for placing hospitality order
     *
     * @param OrderHelper $subject
     * @param callable $proceed
     * @param $request
     * @return Entity\OrderCreateResponse|ResponseInterface
     * @throws NoSuchEntityException
     */
    public function aroundPlaceOrder(OrderHelper $subject, callable $proceed, $request)
    {
        if ($subject->lsr->getCurrentIndustry(
                $subject->basketHelper->getCorrectStoreIdFromCheckoutSession() ?? null
            ) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed($request);
        }

        $response = null;
        // @codingStandardsIgnoreLine
        $operation = new Operation\OrderHospCreate();
        $response  = $operation->execute($request);
        // @codingStandardsIgnoreLine

        return $response;
    }

    /**
     * Before plugin for base getOrderDetailsAgainstId
     *
     * @param OrderHelper $subject
     * @param $docId
     * @param string $type
     * @return array
     * @throws NoSuchEntityException
     */
    public function beforeGetOrderDetailsAgainstId(OrderHelper $subject, $docId, $type = DocumentIdType::ORDER)
    {
        if ($type == DocumentIdType::ORDER && $subject->lsr->getCurrentIndustry() ==
            LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return [$docId, DocumentIdType::HOSP_ORDER];
        }
        return [$docId, $type];
    }

    /**
     * Around plugin for order cancellation
     *
     * @param OrderHelper $subject
     * @param callable $proceed
     * @param $documentId
     * @param $storeId
     * @return Entity\OrderCancelResponse|ResponseInterface|null
     * @throws NoSuchEntityException
     */
    public function aroundOrderCancel(OrderHelper $subject, callable $proceed, $documentId, $storeId)
    {
        if ($subject->lsr->getCurrentIndustry($subject->basketHelper->getCorrectStoreIdFromCheckoutSession() ?? null)
            != LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed($documentId, $storeId);
        }

        $response = null;
        $request  = new Entity\HospOrderCancel();
        $request->setOrderId($documentId);
        $request->setStoreId($storeId);
        $operation = new Operation\HospOrderCancel();
        try {
            $response = $operation->execute($request);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return $response;
    }

    /**
     * After plugin to intercept method returning document_id
     *
     * @param OrderHelper $subject
     * @param $result
     * @param $salesEntry
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function afterGetDocumentIdGivenSalesEntry(OrderHelper $subject, $result, $salesEntry)
    {
        if ($subject->lsr->getCurrentIndustry() != \Ls\Hospitality\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $result;
        }

        return $salesEntry->getId();
    }
}
