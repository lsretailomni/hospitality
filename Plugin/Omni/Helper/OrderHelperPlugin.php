<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Omni\Helper;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\CentralEcommerce\Entity\CreateHospOrder;
use \Ls\Omni\Client\CentralEcommerce\Entity\CreateHospOrderResult;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\DocumentIdType;
use \Ls\Omni\Client\CentralEcommerce\Entity\FABOrder;
use \Ls\Omni\Client\Ecommerce\Entity\HospOrderCancelResponse;
use \Ls\Omni\Client\CentralEcommerce\Entity\HospTransaction;
use \Ls\Omni\Client\CentralEcommerce\Entity\HospTransactionLine;
use \Ls\Omni\Client\CentralEcommerce\Entity\HospTransDiscountLine;
use \Ls\Omni\Client\CentralEcommerce\Entity\MobileTransactionSubLine;
use \Ls\Omni\Client\CentralEcommerce\Entity\RootHospTransaction;
use \Ls\Omni\Client\CentralEcommerce\Entity\RootMobileTransaction;
use \Ls\Omni\Client\Ecommerce\Operation;
use \Ls\Omni\Client\CentralEcommerce\Operation\GetSelectedSalesDoc_GetSelectedSalesDoc;
use \Ls\Omni\Client\ResponseInterface;
use \Ls\Omni\Exception\InvalidEnumException;
use \Ls\Omni\Helper\OrderHelper;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * OrderHelper plugin responsible for intercepting required methods
 */
class OrderHelperPlugin
{
    /**
     * @param DateTime $date
     * @param LoggerInterface $logger
     * @param LSR $lsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        public DateTime $date,
        public LoggerInterface $logger,
        public LSR $lsr,
        public HospitalityHelper $hospitalityHelper
    ) {
    }

    /**
     * Around plugin for preparing the order request for hospitality order
     *
     * @param OrderHelper $subject
     * @param callable $proceed
     * @param Model\Order $order
     * @param RootMobileTransaction $oneListCalculateResponse
     * @return Entity\OrderHospCreate
     * @throws NoSuchEntityException|GuzzleException
     */
    public function aroundPrepareOrder(
        OrderHelper $subject,
        callable $proceed,
        Model\Order $order,
        RootMobileTransaction $oneListCalculateResponse
    ) {
        if ($subject->lsr->getCurrentIndustry($order->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($order, $oneListCalculateResponse);
        }

        $rootCustomerOrderCreate = $subject->createInstance(
            RootHospTransaction::class
        );
        try {
            $customerOrderCreateCoHeader = $subject->createInstance(
                HospTransaction::class
            );
            $fabOrder = $subject->createInstance(
                FABOrder::class
            );
            $storeId = current((array)$oneListCalculateResponse->getMobiletransaction())->getStoreid();
            $cardId = current((array)$oneListCalculateResponse->getMobiletransaction())->getMembercardno();
            $sourceType = current((array)$oneListCalculateResponse->getMobiletransaction())->getSourcetype();
            $transactionType = current((array)$oneListCalculateResponse->getMobiletransaction())->getTransactiontype();
            $currencyFactor = current((array)$oneListCalculateResponse->getMobiletransaction())->getCurrencyfactor();
            $transactionId = current((array)$oneListCalculateResponse->getMobiletransaction())->getId();
            $transactionDate = current((array)$oneListCalculateResponse->getMobiletransaction())->getTransdate() ??
                $this->date->date("Y-m-d\T" . "H:i:00");
            $customerEmail = $order->getCustomerEmail();
            $customerName = substr($order->getBillingAddress()->getFirstname() . ' ' .
                $order->getBillingAddress()->getLastname(), 0, 20);

            if ($this->hospitalityHelper->removeCheckoutStepEnabled()) {
                $order->setShippingMethod('clickandcollect_clickandcollect');
            }
            $shippingMethod = $order->getShippingMethod(true);
            $isClickCollect = false;

            if ($shippingMethod !== null) {
                $carrierCode = $shippingMethod->getData('carrier_code');
                $isClickCollect = $carrierCode == 'clickandcollect';
            }

            $dateTimeFormat = "Y-m-d\T" . "H:i:00";
            $pickupDateTimeslot = null;
            $pickupDateTime = $this->date->date($dateTimeFormat);
            if (!empty($order->getPickupDateTimeslot())) {
                $pickupDateTimeslot = $order->getPickupDateTimeslot();
                if (!empty($pickupDateTimeslot)) {
                    $pickupDateTime = $this->date->date($dateTimeFormat, $pickupDateTimeslot);
                }
            }

            $subject->checkoutSession->setPickupDateTimeslot($pickupDateTimeslot);

            // Create DateTime object from the formatted string
            $pickupDateTimeObj = new \DateTime($pickupDateTime);

            // Separate date and time
            $pickupDate = $pickupDateTimeObj->format('Y-m-d');
            $pickupTime = $pickupDateTimeObj->format('H:i:s.vP');
            $pickupTime = preg_replace('/(\.\d{3})\+/', '.0000000+', $pickupTime);
            $comment = $order->getData(LSR::LS_ORDER_COMMENT);

            $qrCodeQueryString = '';
            $qrCodeParams = $order->getData(LSR::LS_QR_CODE_ORDERING);

            if (!empty($qrCodeParams)) {
                $qrCodeParams = $subject->json->unserialize($qrCodeParams);
            } else {
                $qrCodeParams = $this->hospitalityHelper->qrcodeHelperObject()->getQrCodeOrderingInSession();
            }

            if (!empty($qrCodeParams)) {
                $qrCodeQueryString = http_build_query($qrCodeParams);
            }

            if ($isClickCollect) {
                $salesType = $this->lsr->getTakeAwaySalesType();
                if (!empty($qrCodeParams) && array_key_exists('sales_type', $qrCodeParams)) {
                    $salesType = $qrCodeParams['sales_type'];
                }
            } else {
                $salesType = $this->hospitalityHelper->getLSR()->getDeliverySalesType();
            }
            $orderPayments = $subject->setOrderPayments(
                $order,
                $cardId,
                $isClickCollect ? $order->getPickupStore() : $storeId
            );

            //if the shipping address is empty, we use the contact address as shipping address.
            $customerOrderCreateCoHeader->addData(
                [
                    HospTransaction::ID => $transactionId,
                    HospTransaction::MEMBER_CARD_NO => $cardId,
                    HospTransaction::SOURCE_TYPE => $sourceType,
                    HospTransaction::STORE_ID => $storeId,
                    HospTransaction::TRANSACTION_TYPE => $transactionType,
                    HospTransaction::CURRENCY_FACTOR => $currencyFactor,
                    HospTransaction::TRANS_DATE => $transactionDate,
                    HospTransaction::SALES_TYPE => $salesType
                ]
            );

            $fabOrder->addData([
                FABOrder::EXTERNAL_ID => $order->getIncrementId(),
                FABOrder::CLIENT_EMAIL => $customerEmail,
                FABOrder::CLIENT_NAME => $customerName,
                FABOrder::SALES_TYPE => $salesType,
                FABOrder::STORE_NO => $storeId,
                FABOrder::PICKUP_DATE => $pickupDate,
                FABOrder::PICKUP_TIME => $pickupTime,
                FABOrder::PICKUP_DATE_TIME => $pickupDateTime,
                FABOrder::CUSTOMER_COMMENT => $comment,
                FABOrder::QRMESSAGE => $qrCodeQueryString,
                FABOrder::GROSS_AMOUNT => $order->getGrandTotal(),
                FABOrder::CLIENT_ADDRESS => $order->getShippingAddress() ?
                    $order->getShippingAddress()->getStreetLine(1) :
                    $order->getBillingAddress()->getStreetLine(1),
                FABOrder::CLIENT_ADDRESS2 => $order->getShippingAddress() ?
                    $order->getShippingAddress()->getStreetLine(2) :
                    $order->getBillingAddress()->getStreetLine(2),
                FABOrder::CLIENT_CITY => $order->getShippingAddress() ?
                    $order->getShippingAddress()->getCity() :
                    $order->getBillingAddress()->getCity(),
                FABOrder::CLIENT_PHONE_NO => $order->getShippingAddress() ?
                    $order->getShippingAddress()->getTelephone() :
                    $order->getBillingAddress()->getTelephone(),
                FABOrder::CLIENT_POST_CODE => $order->getShippingAddress() ?
                    $order->getShippingAddress()->getPostcode() :
                    $order->getBillingAddress()->getPostcode(),
                FABOrder::CLIENT_COUNTRY_REGION => $order->getShippingAddress() ?
                    $order->getShippingAddress()->getCountryId() :
                    $order->getBillingAddress()->getCountryId(),
            ]);
            $customerOrderCoLines = [];
            foreach ($oneListCalculateResponse->getMobiletransactionline() ?? [] as $id => $orderLine) {
                if ($orderLine->getLinetype() == 0) {
                    $customerOrderCoLine = $subject->createInstance(
                        HospTransactionLine::class
                    );

                    $customerOrderCoLine->addData([
                        HospTransactionLine::ID => $transactionId,
                        HospTransactionLine::LINE_NO => $orderLine->getLineno(),
                        HospTransactionLine::LINE_TYPE => $orderLine->getLinetype(),
                        HospTransactionLine::NUMBER => $orderLine->getNumber(),
                        HospTransactionLine::VARIANT_CODE => $orderLine->getVariantcode(),
                        HospTransactionLine::UOM_ID => $orderLine->getUomid(),
                        HospTransactionLine::NET_PRICE => $orderLine->getNetprice(),
                        HospTransactionLine::PRICE => $orderLine->getPrice(),
                        HospTransactionLine::QUANTITY => $orderLine->getQuantity(),
                        HospTransactionLine::DISCOUNT_AMOUNT => $orderLine->getDiscountamount(),
                        HospTransactionLine::DISCOUNT_PERCENT => $orderLine->getDiscountpercent(),
                        HospTransactionLine::NET_AMOUNT => $orderLine->getNetamount(),
                        HospTransactionLine::TAXAMOUNT => $orderLine->getTaxamount(),
                        HospTransactionLine::STORE_ID => $isClickCollect ? $order->getPickupStore() : $storeId,
                        HospTransactionLine::EXTERNAL_ID => $id,
                        HospTransactionLine::DEAL_ITEM => $orderLine->getDealitem()
                    ]);

                    $customerOrderCoLines[] = $customerOrderCoLine;
                }
            }
            $dealLines = $modifierRecipeLines = [];

            foreach ($oneListCalculateResponse->getMobiletransactionsubline() ?? [] as $id => $orderLine) {
                $customerOrderCoLine = $subject->createInstance(
                    MobileTransactionSubLine::class
                );

                $customerOrderCoLine->addData([
                    MobileTransactionSubLine::ID => $transactionId,
                    MobileTransactionSubLine::LINE_NO => $orderLine->getLineno(),
                    MobileTransactionSubLine::PARENT_LINE_NO => $orderLine->getParentLineNo(),
                    MobileTransactionSubLine::LINE_TYPE => $orderLine->getLinetype(),
                    MobileTransactionSubLine::PARENT_LINE_IS_SUBLINE => $orderLine->getParentlineissubline(),
                    MobileTransactionSubLine::NUMBER => $orderLine->getNumber(),
                    MobileTransactionSubLine::VARIANT_CODE => $orderLine->getVariantcode(),
                    MobileTransactionSubLine::UOM_ID => $orderLine->getUomid(),
                    MobileTransactionSubLine::NET_PRICE => $orderLine->getNetprice(),
                    MobileTransactionSubLine::PRICE => $orderLine->getPrice(),
                    MobileTransactionSubLine::QUANTITY => $orderLine->getQuantity(),
                    MobileTransactionSubLine::DISCOUNT_AMOUNT => $orderLine->getDiscountamount(),
                    MobileTransactionSubLine::DISCOUNT_PERCENT => $orderLine->getDiscountpercent(),
                    MobileTransactionSubLine::NET_AMOUNT => $orderLine->getNetamount(),
                    MobileTransactionSubLine::TAXAMOUNT => $orderLine->getTaxamount(),
                    MobileTransactionSubLine::MODIFIER_GROUP_CODE => $orderLine->getModifierGroupCode(),
                    MobileTransactionSubLine::MODIFIER_SUB_CODE => $orderLine->getModifierSubCode(),
                    MobileTransactionSubLine::DEAL_ID => $orderLine->getDealId(),
                    MobileTransactionSubLine::DEAL_LINE => $orderLine->getDealline(),
                    MobileTransactionSubLine::DEAL_MOD_LINE => $orderLine->getDealmodline(),
                    MobileTransactionSubLine::DESCRIPTION => $orderLine->getDescription(),
                    MobileTransactionSubLine::UOM_DESCRIPTION => $orderLine->getUomdescription(),
                ]);

                if (empty($orderLine->getDealmodline())) {
                    $modifierRecipeLines[] = $customerOrderCoLine;
                } else {
                    $dealLines[] = $customerOrderCoLine;
                }
            }
            $customerOrderCoSubLines = array_merge($modifierRecipeLines, $dealLines);

            foreach ($customerOrderCoSubLines as $index => $subLine) {
                $lineNo = (++$index) * 10000;
                $subLine->addData([
                    MobileTransactionSubLine::LINE_NO => $lineNo
                ]);
            }

            //For click and collect we need to remove shipment charge orderline
            //For flat shipment it will set the correct shipment value into the order
            $customerOrderCoLines = $subject->updateShippingAmount($customerOrderCoLines, $order, $storeId);

            foreach ($orderPayments ?? [] as $orderPayment) {
                $currentLineNo = end($customerOrderCoLines)->getLineno();
                $currentLineNo += 10000;
                $customerOrderCoLines[] = $orderPayment->addData([
                    HospTransactionLine::ID => $transactionId,
                    HospTransactionLine::LINE_NO => $currentLineNo
                ]);
            }

            $customerOrderDiscountCoLines = [];

            foreach ($oneListCalculateResponse->getMobiletransdiscountline() ?? [] as $id => $orderDiscountLine) {
                $customerOrderDiscountCoLine = $subject->createInstance(
                    HospTransDiscountLine::class
                );

                $customerOrderDiscountCoLine->addData([
                    HospTransDiscountLine::ID => $transactionId,
                    HospTransDiscountLine::LINE_NO => $orderDiscountLine->getLineno(),
                    HospTransDiscountLine::NO => $orderDiscountLine->getNo(),
                    HospTransDiscountLine::OFFER_NO => $orderDiscountLine->getOfferno(),
                    HospTransDiscountLine::DISCOUNT_TYPE => $orderDiscountLine->getDiscounttype(),
                    HospTransDiscountLine::PERIODIC_DISC_TYPE =>
                        $orderDiscountLine->getPeriodicdisctype(),
                    HospTransDiscountLine::PERIODIC_DISC_GROUP =>
                        $orderDiscountLine->getPeriodicdiscgroup(),
                    HospTransDiscountLine::DESCRIPTION => $orderDiscountLine->getDescription(),
                    HospTransDiscountLine::DISCOUNT_PERCENT => $orderDiscountLine->getDiscountpercent(),
                    HospTransDiscountLine::DISCOUNT_AMOUNT => $orderDiscountLine->getDiscountamount(),
                ]);

                $customerOrderDiscountCoLines[] = $customerOrderDiscountCoLine;
            }

            $rootCustomerOrderCreate
                ->setHosptransaction($customerOrderCreateCoHeader)
                ->setFaborder($fabOrder)
                ->setHosptransactionline($customerOrderCoLines)
                ->setHosptransdiscountline($customerOrderDiscountCoLines)
                ->setHosptransactionsubline($customerOrderCoSubLines);

        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return $rootCustomerOrderCreate;
    }

    /**
     * Around plugin for getting payments for the hospitality order
     *
     * @param OrderHelper $subject
     * @param $proceed
     * @param Order $order
     * @param string $cardId
     * @param string $storeId
     * @return array|mixed
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function aroundSetOrderPayments(
        OrderHelper $subject,
        $proceed,
        Model\Order $order,
        string $cardId,
        string $storeId
    ) {
        if ($subject->lsr->getCurrentIndustry($subject->basketHelper->getCorrectStoreIdFromCheckoutSession() ?? null)
            != LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed($order, $cardId, $storeId);
        }
        $transId = $order->getPayment()->getLastTransId();
        $cardNumber = $order->getPayment()->getCcLast4();

        $orderPaymentArray = [];
        //TODO change it to $paymentMethod->isOffline() == false when order edit option available for offline payments.
        $paymentCode = $order->getPayment()->getMethodInstance()->getCode();
        $tenderTypeId = $subject->getPaymentTenderTypeId($paymentCode);

        $noOrderPayment = $subject->paymentLineNotRequiredPaymentMethods($order);

        $shippingMethod = $order->getShippingMethod(true);
        $isClickCollect = false;

        if ($shippingMethod !== null) {
            $carrierCode = $shippingMethod->getData('carrier_code');
            $isClickCollect = $carrierCode == 'clickandcollect';
        }
        $lineNumber = 10000;
        if (!in_array($paymentCode, $noOrderPayment)) {
            // @codingStandardsIgnoreStart
            $orderPayment = $subject->createInstance(HospTransactionLine::class);
            $orderPayment->addData(
                [
                    HospTransactionLine::STORE_ID => $isClickCollect ? $order->getPickupStore() : $storeId,
                    HospTransactionLine::LINE_NO => $lineNumber,
                    HospTransactionLine::LINE_TYPE => 1,
                    HospTransactionLine::NUMBER => $tenderTypeId,
                    HospTransactionLine::CURRENCY_CODE => $order->getOrderCurrency()->getCurrencyCode(),
                    HospTransactionLine::CURRENCY_FACTOR => 1,
                    HospTransactionLine::NET_AMOUNT => $order->getGrandTotal(),
                    HospTransactionLine::EFTTRANSACTION_NO => $order->getIncrementId(),
                ]
            );
            // For CreditCard/Debit Card payment  use Tender Type 1 for Cards
            if (!empty($transId)) {
                $orderPayment->addData(
                    [
                        HospTransactionLine::EFTCARD_NUMBER => $cardNumber,
                    ]
                );
            }
            $orderPaymentArray[] = $orderPayment;
            $lineNumber += 10000;
        }

        if ($order->getLsPointsSpent()) {
            $tenderTypeId = $subject->getPaymentTenderTypeId(\Ls\Core\Model\LSR::LS_LOYALTYPOINTS_TENDER_TYPE);
            $pointRate = $subject->loyaltyHelper->getPointRate();

            $orderPayment = $subject->createInstance(HospTransactionLine::class);
            $orderPayment->addData(
                [
                    HospTransactionLine::STORE_ID => $isClickCollect ? $order->getPickupStore() : $storeId,
                    HospTransactionLine::LINE_NO => $lineNumber,
                    HospTransactionLine::LINE_TYPE => 1,
                    HospTransactionLine::NUMBER => $tenderTypeId,
                    HospTransactionLine::CURRENCY_CODE => 'LOY',
                    HospTransactionLine::CURRENCY_FACTOR => $pointRate,
                    HospTransactionLine::NET_AMOUNT => $order->getLsPointsSpent(),
                    HospTransactionLine::EFTTRANSACTION_NO => $order->getIncrementId(),
                ]
            );
            $orderPaymentArray[] = $orderPayment;
            $lineNumber += 10000;
        }

        if ($order->getLsGiftCardAmountUsed()) {
            $tenderTypeId = $subject->getPaymentTenderTypeId(LSR::LS_GIFTCARD_TENDER_TYPE);
            $giftCardCurrencyCode = $order->getOrderCurrency()->getCurrencyCode();

            $orderPayment = $subject->createInstance(HospTransactionLine::class);
            $orderPayment->addData(
                [
                    HospTransactionLine::STORE_ID => $isClickCollect ? $order->getPickupStore() : $storeId,
                    HospTransactionLine::LINE_NO => $lineNumber,
                    HospTransactionLine::LINE_TYPE => 1,
                    HospTransactionLine::NUMBER => $tenderTypeId,
                    HospTransactionLine::CURRENCY_CODE => $giftCardCurrencyCode,
                    HospTransactionLine::CURRENCY_FACTOR => 0,
                    HospTransactionLine::NET_AMOUNT => $order->getLsGiftCardAmountUsed(),
                    HospTransactionLine::EFTTRANSACTION_NO => $order->getIncrementId(),
                    HospTransactionLine::EFTCARD_NUMBER => $order->getLsGiftCardNo()
                ]
            );
            $orderPaymentArray[] = $orderPayment;
        }

        return $orderPaymentArray;
    }

    /**
     * After interceptor to inject into payment methods array
     *
     * @param OrderHelper $subject
     * @param array $result
     * @param Model\Order $order
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterPaymentLineNotRequiredPaymentMethods(OrderHelper $subject, $result, Model\Order $order)
    {
        if ($subject->lsr->getCurrentIndustry($order->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $result;
        }

        $result[] = 'cashondelivery';

        return $result;
    }

    /**
     * Around plugin for updating shipping amount
     *
     * @param OrderHelper $subject
     * @param callable $proceed
     * @param $orderLines
     * @param Order $order
     * @param string $storeCode
     * @return mixed
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     */
    public function aroundUpdateShippingAmount(
        OrderHelper $subject,
        callable $proceed,
        $orderLines,
        Model\Order $order,
        string $storeCode
    ) {
        if ($subject->lsr->getCurrentIndustry($order->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($orderLines, $order, $storeCode);
        }

        $shipmentFeeId = $this->lsr->getStoreConfig(\Ls\Core\Model\LSR::LSR_SHIPMENT_ITEM_ID, $order->getStoreId());
        $shipmentTaxPercent = $subject->getShipmentTaxPercent($order->getStore());
        $shippingAmount = $order->getShippingInclTax();

        if (isset($shipmentTaxPercent) && $shippingAmount > 0) {
            $netPriceFormula = 1 + $shipmentTaxPercent / 100;
            $netPrice = (float)$shippingAmount / $netPriceFormula;
            $taxAmount = (float)number_format(($shippingAmount - $netPrice), 2);
            $currentLineNo = end($orderLines)->getLineno();
            $currentLineNo += 10000;
            $customerOrderCoLine = $subject->createInstance(
                HospTransactionLine::class
            );

            $customerOrderCoLine->addData([
                HospTransactionLine::LINE_NO => $currentLineNo,
                HospTransactionLine::LINE_TYPE => 0,
                HospTransactionLine::NUMBER => $shipmentFeeId,
                HospTransactionLine::NET_PRICE => $netPrice,
                HospTransactionLine::MANUAL_PRICE => $shippingAmount,
                HospTransactionLine::QUANTITY => 1,
                HospTransactionLine::NET_AMOUNT => $netPrice,
                HospTransactionLine::TAXAMOUNT => $taxAmount,
                HospTransactionLine::STORE_ID => $storeCode
            ]);
            $orderLines[] = $customerOrderCoLine;
        }

        return $orderLines;
    }

    /**
     * Around plugin for placing hospitality order
     *
     * @param OrderHelper $subject
     * @param callable $proceed
     * @param $request
     * @return CreateHospOrderResult
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
        $operation = $subject->createInstance(\Ls\Omni\Client\CentralEcommerce\Operation\CreateHospOrder::class);

        $operation->setOperationInput(
            [CreateHospOrder::CREATE_HOSP_ORDER_XML => $request]
        );

        $response  = $operation->execute();
        // @codingStandardsIgnoreLine
        $subject->customerSession->setData(LSR::LS_QR_CODE_ORDERING, null);

        return $response;
    }

    /**
     * Extract document_id from order response
     *
     * @param OrderHelper $subject
     * @param callable $proceed
     * @param $response
     * @return string
     * @throws NoSuchEntityException
     */
    public function aroundGetDocumentIdFromResponseBasedOnIndustry(OrderHelper $subject, callable $proceed, $response)
    {
        if ($subject->lsr->getCurrentIndustry($subject->basketHelper->getCorrectStoreIdFromCheckoutSession() ?? null)
            != LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed($response);
        }

        return $response->getHosporderreceiptno();
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
     * @return GetSelectedSalesDoc_GetSelectedSalesDoc|null
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     */
    public function aroundFetchOrder(OrderHelper $subject, $proceed, $docId, $type)
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($docId, $type);
        }

        $type = $type == DocumentIdType::ORDER ? DocumentIdType::HOSP_ORDER : $type;

        $response = $subject->getOrderDetailsAgainstId($docId, $type);

        if (!$response && $type == DocumentIdType::HOSP_ORDER) {
            $response = $subject->getOrderDetailsAgainstId($docId, DocumentIdType::RECEIPT);
        }

        return $response;
    }
}
