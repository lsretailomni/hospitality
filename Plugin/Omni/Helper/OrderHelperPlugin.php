<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use Exception;
use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\DocumentIdType;
use \Ls\Omni\Client\Ecommerce\Entity\HospOrderCancelResponse;
use \Ls\Omni\Client\Ecommerce\Operation;
use \Ls\Omni\Client\ResponseInterface;
use \Ls\Omni\Exception\InvalidEnumException;
use \Ls\Omni\Helper\OrderHelper;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
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
     * @var LSR
     */
    public $lsr;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * OrderHelperPlugin constructor.
     * @param DateTime $date
     * @param LoggerInterface $logger
     * @param LSR $lsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        DateTime $date,
        LoggerInterface $logger,
        LSR $lsr,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->date              = $date;
        $this->logger            = $logger;
        $this->lsr               = $lsr;
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * Around plugin for preparing the order request
     *
     * @param OrderHelper $subject
     * @param callable $proceed
     * @param Model\Order $order
     * @param mixed $oneListCalculateResponse
     * @return Entity\OrderHospCreate
     * @throws NoSuchEntityException
     */
    public function aroundPrepareOrder(
        OrderHelper $subject,
        callable $proceed,
        Model\Order $order,
        $oneListCalculateResponse
    ) {
        if ($subject->lsr->getCurrentIndustry($order->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($order, $oneListCalculateResponse);
        }
        // @codingStandardsIgnoreLine
        $request         = new Entity\OrderHospCreate();
        $orderLinesArray = $oneListCalculateResponse->getOrderLines()->getOrderHospLine();
        try {
            $storeId       = $oneListCalculateResponse->getStoreId();
            $cardId        = $oneListCalculateResponse->getCardId();
            $customerEmail = $order->getCustomerEmail();
            $billToName    = substr($order->getBillingAddress()->getFirstname() . ' ' .
                                    $order->getBillingAddress()->getLastname(), 0, 20);

            /** Entity\ArrayOfOrderPayment $orderPaymentArrayObject */
            $orderPaymentArrayObject = $subject->setOrderPayments($order, $cardId);
            $shippingMethod          = $order->getShippingMethod(true);
            $isClickCollect          = false;
            $dateTimeFormat          = "Y-m-d\T" . "H:i:00";
            $pickupDateTimeslot      = null;
            $pickupDateTime          = $this->date->date($dateTimeFormat);
            if ($shippingMethod !== null) {
                $isClickCollect = $shippingMethod->getData('carrier_code') == 'clickandcollect';
            }

            if ($isClickCollect) {
                $oneListCalculateResponse->setSalesType($this->lsr->getTakeAwaySalesType());
                $pickupDateTimeslot = $order->getPickupDateTimeslot();
                if (!empty($pickupDateTimeslot)) {
                    $pickupDateTime = $this->date->date($dateTimeFormat, $pickupDateTimeslot);
                }
            }
            $subject->checkoutSession->setPickupDateTimeslot($pickupDateTimeslot);

            $qrCodeQueryString = '';
            $qrCodeParams      = $order->getData(LSR::LS_QR_CODE_ORDERING);

            if (!empty($qrCodeParams)) {
                $qrCodeParams = $subject->json->unserialize($qrCodeParams);
            } else {
                $qrCodeParams = $subject->customerSession->getData(LSR::LS_QR_CODE_ORDERING);
            }

            if (!empty($qrCodeParams)) {
                $qrCodeQueryString = http_build_query($qrCodeParams);
            }

            $comment = $order->getData(LSR::LS_ORDER_COMMENT);

            $oneListCalculateResponse
                ->setCardId($cardId)
                ->setStoreId($storeId)
                ->setRestaurantNo($storeId)
                ->setPickUpTime($pickupDateTime)
                ->setComment($comment)
                ->setQRData($qrCodeQueryString)
                ->setEmail($customerEmail)
                ->setName($billToName)
                ->setBillToName($billToName)
                ->setExternalId($order->getIncrementId());
            $oneListCalculateResponse->setOrderPayments($orderPaymentArrayObject);
            //For click and collect we need to remove shipment charge orderline
            //For flat shipment it will set the correct shipment value into the order
            $orderLinesArray = $subject->updateShippingAmount($orderLinesArray, $order);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
        $oneListCalculateResponse->setOrderLines($orderLinesArray);
        $request->setRequest($oneListCalculateResponse);

        if (version_compare($this->lsr->getOmniVersion(), '2023.05.1', '>=')) {
            $request->setReturnOrderIdOnly(true);
        }

        return $request;
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

        $shipmentFeeId      = $this->lsr->getStoreConfig(LSR::LSR_SHIPMENT_ITEM_ID, $order->getStoreId());
        $shipmentTaxPercent = $this->lsr->getStoreConfig(LSR::LSR_SHIPMENT_TAX, $order->getStoreId());
        $shippingAmount     = $order->getShippingAmount();
        if ($shippingAmount > 0) {
            $netPriceFormula = 1 + $shipmentTaxPercent / 100;
            $netPrice        = $subject->loyaltyHelper->formatValue($shippingAmount / $netPriceFormula);
            $taxAmount       = $subject->loyaltyHelper->formatValue($shippingAmount - $netPrice);
            // @codingStandardsIgnoreLine
            $shipmentOrderLine = new Entity\OrderHospLine();
            $shipmentOrderLine->setPrice($shippingAmount)
                ->setAmount($shippingAmount)
                ->setNetPrice($netPrice)
                ->setNetAmount($netPrice)
                ->setTaxAmount($taxAmount)
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
        if ($subject->lsr->getCurrentIndustry($subject->basketHelper->getCorrectStoreIdFromCheckoutSession() ?? null)
            != LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed($request);
        }

        // @codingStandardsIgnoreLine
        $operation = new Operation\OrderHospCreate();
        $response  = $operation->execute($request);
        // @codingStandardsIgnoreLine
        $subject->customerSession->setData(LSR::LS_QR_CODE_ORDERING, null);
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
        if ($type == DocumentIdType::ORDER
            && $subject->lsr->getCurrentIndustry() ==
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
     * @return bool|HospOrderCancelResponse|ResponseInterface|null
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

        return $response ? $response->getHospOrderCancelResult() : $response;
    }

    /**
     * Around plugin to formulate exception for order cancellation in case of hospitality
     *
     * @param OrderHelper $subject
     * @param callable $proceed
     * @param $response
     * @param $order
     * @return void
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws LocalizedException
     */
    public function aroundFormulateOrderCancelResponse(OrderHelper $subject, callable $proceed, $response, $order)
    {
        if ($subject->lsr->getCurrentIndustry($subject->basketHelper->getCorrectStoreIdFromCheckoutSession() ?? null)
            != LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed($response, $order);
        }

        if (!$response) {
            $subject->formulateException($order);
        }
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
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $result;
        }

        return $salesEntry->getId();
    }

    /**
     * Around plugin for fetch order from Central using supported method for hospitality
     *
     * @param OrderHelper $subject
     * @param $proceed
     * @param $docId
     * @param $type
     * @return Entity\SalesEntry|Entity\SalesEntryGetResponse|ResponseInterface|mixed|null
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     */
    public function aroundFetchOrder(OrderHelper $subject, $proceed, $docId, $type)
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($docId, $type);
        }

        return $subject->getOrderDetailsAgainstId($docId, $type);
    }
}
