<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Omni\Helper;

use GuzzleHttp\Exception\GuzzleException;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\Ecommerce\Entity\MobileTransaction;
use \Ls\Omni\Client\Ecommerce\Entity\MobileTransactionLine;
use \Ls\Omni\Client\Ecommerce\Entity\MobileTransactionSubLine;
use \Ls\Omni\Client\Ecommerce\Entity\MobileTransDiscountLine;
use \Ls\Omni\Client\Ecommerce\Entity\RootMobileTransaction;
use \Ls\Omni\Client\Ecommerce\Operation\MobilePosCalculate;
use \Ls\Omni\Exception\InvalidEnumException;
use \Ls\Omni\Helper\BasketHelper;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

/**
 * BasketHelper plugin responsible for intercepting required methods
 */
class BasketHelperPlugin
{
    /**
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        public HospitalityHelper $hospitalityHelper
    ) {
    }

    /**
     * Around plugin for creating oneList in hospitality on the basis of items in the quote
     *
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param Quote $quote
     * @param RootMobileTransaction $oneList
     * @return mixed
     * @throws InvalidEnumException
     * @throws NoSuchEntityException|LocalizedException
     */
    public function aroundSetOneListQuote(
        BasketHelper $subject,
        callable $proceed,
        Quote $quote,
        RootMobileTransaction $oneList
    ) {
        if ($subject->lsr->getCurrentIndustry($quote->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($quote, $oneList);
        }
        $quoteItems = $quote->getAllVisibleItems();

        $itemsArray = $oneListSubLinesArray = $transactionLines = $dealLines = $modifierLines = $recipeLines = [];
        $oneList->getMobiletransaction()->setSalestype($this->hospitalityHelper->getLSR()->getDeliverySalesType());
        $transactionId = $oneList->getMobiletransaction()
            ->getId();
        $storeCode = $subject->getDefaultWebStore();

        foreach ($quoteItems as $lineNumber => $quoteItem) {
            $lineNumber = (++$lineNumber) * 10000;
            $product = $quoteItem->getProduct();
            $children = [];
            $isBundle = 0;

            if ($quoteItem->getProductType() == Type::TYPE_BUNDLE) {
                $children = $quoteItem->getChildren();
                $isBundle = 1;
            } else {
                $children[] = $quoteItem;
            }

            foreach ($children as $child) {
                if ($child->getProduct()->isInStock()) {
                    list($itemId, $variantId, $uom, $barCode) =
                        $subject->itemHelper->getItemAttributesGivenQuoteItem($child);
                    $match = false;
                    $giftCardIdentifier = $subject->lsr->getGiftCardIdentifiers();

                    if (in_array($itemId, explode(',', $giftCardIdentifier))) {
                        foreach ($itemsArray as $itemArray) {
                            if ($itemArray->getId() == $child->getItemId()) {
                                $itemArray->setQuantity($itemArray->getQuantity() + $quoteItem->getData('qty'));
                                $match = true;
                                break;
                            }
                        }
                    } else {
                        foreach ($itemsArray as $itemArray) {
                            if (is_numeric($itemArray->getId()) ?
                                $itemArray->getId() == $child->getItemId() :
                                ($itemArray->getItemId() == $itemId &&
                                    $itemArray->getVariantId() == $variantId &&
                                    $itemArray->getUnitOfMeasureId() == $uom &&
                                    $itemArray->getBarcodeId() == $barCode)
                            ) {
                                $itemArray->setQuantity($itemArray->getQuantity() + $quoteItem->getData('qty'));
                                $match = true;
                                break;
                            }
                        }
                    }

                    if (!$match) {
                        $price = $quoteItem->getProduct()->getPrice();
                        $price = $subject->itemHelper->convertToCurrentStoreCurrency($price);
                        $qty = $isBundle ? $child->getData('qty') * $quoteItem->getData('qty') :
                            $quoteItem->getData('qty');
                        $amount = $subject->itemHelper->convertToCurrentStoreCurrency($quoteItem->getPrice() * $qty);
                        $transactionLine = $subject->createInstance(
                            MobileTransactionLine::class,
                            [
                                'data' => [
                                    MobileTransactionLine::ID => $transactionId,
                                    MobileTransactionLine::LINE_NO => $lineNumber,
                                    MobileTransactionLine::STORE_ID => $storeCode,
                                    MobileTransactionLine::QUANTITY => $qty,
                                    MobileTransactionLine::NUMBER => $itemId,
                                    MobileTransactionLine::VARIANT_CODE => $variantId,
                                    MobileTransactionLine::UOM_ID => $uom,
                                    MobileTransactionLine::PRICE => $price,
                                    MobileTransactionLine::NET_AMOUNT => $amount,
                                    MobileTransactionLine::TRANS_DATE => $subject->getCompatibleDateTime(),
                                    MobileTransactionLine::CURRENCY_FACTOR => 1,
                                    MobileTransactionLine::DEAL_ITEM =>
                                        $product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE)
                                ]
                            ]
                        );
                        $transactionLines[] = $transactionLine;
                    }
                }
            }
            $selectedSubLines = $this->hospitalityHelper->getSelectedOrderHospSubLineGivenQuoteItem(
                $quoteItem,
                $lineNumber
            );

            if (!empty($selectedSubLines['deal'])) {
                $dealLines = array_merge($selectedSubLines['deal'], $dealLines);
            }

            if (!empty($selectedSubLines['modifier'])) {
                $modifierLines = array_merge($selectedSubLines['modifier'], $modifierLines);
            }

            if (!empty($selectedSubLines['recipe'])) {
                $recipeLines = array_merge($selectedSubLines['recipe'], $recipeLines);
            }
        }
        $lineNumber = 0;
        if (!empty($dealLines)) {
            // Custom sort: entries without DealModLineId and uom come first
            usort($dealLines, function ($a, $b) {
                $aHasModAndUom = isset($a['DealModLineId']) && isset($a['uom']);
                $bHasModAndUom = isset($b['DealModLineId']) && isset($b['uom']);

                // If one has and the other doesn't, the one without comes first
                if ($aHasModAndUom !== $bHasModAndUom) {
                    return $aHasModAndUom ? 1 : -1;
                }

                // Optional: sort by LineNumber if both are equal on above condition
                return ($a['ParentSubLineId'] ?? 0) <=> ($b['ParentSubLineId'] ?? 0);
            });
            foreach ($dealLines as $dealLine) {
                $lineNumber = $lineNumber + 10000;
                $oneListSubLine = $subject->createInstance(MobileTransactionSubLine::class)
                    ->setId($transactionId)
                    ->setLineno($lineNumber)
                    ->setParentlineno($dealLine['ParentSubLineId'] ?? null)
                    ->setLinetype(1)
                    ->setUomid($dealLine['uom'] ?? null)
                    ->setQuantity(1)
                    ->setDealline($dealLine['DealLineId'] ?? null)
                    ->setDealmodline($dealLine['DealModLineId'] ?? null)
                    ->setDealid($dealLine['DealId'] ?? null);
                $oneListSubLinesArray[] = $oneListSubLine;
            }
        }

        if (!empty($modifierLines)) {
            foreach ($modifierLines as $modifierLine) {
                $lineNumber = $lineNumber + 10000;
                $oneListSubLine = $subject->createInstance(MobileTransactionSubLine::class)
                    ->setId($transactionId)
                    ->setLineno($lineNumber)
                    ->setParentlineno($modifierLine['ParentSubLineId'] ?? null)
                    ->setParentlineissubline($modifierLine['ParentLineIsSubline'] ?? 0)
                    ->setQuantity(1)
                    ->setModifiergroupcode($modifierLine['ModifierGroupCode'] ?? null)
                    ->setModifiersubcode($modifierLine['ModifierSubCode'] ?? null)
                    ->setDealid('0');
                $oneListSubLinesArray[] = $oneListSubLine;
            }
        }

        if (!empty($recipeLines)) {
            foreach ($recipeLines as $recipeLine) {
                $lineNumber = $lineNumber + 10000;
                $oneListSubLine = $subject->createInstance(MobileTransactionSubLine::class)
                    ->setId($transactionId)
                    ->setLineno($lineNumber)
                    ->setParentlineno($recipeLine['ParentSubLineId'] ?? null)
                    ->setParentlineissubline($recipeLine['ParentLineIsSubline'] ?? 0)
                    ->setNumber($recipeLine['ItemId'])
                    ->setDealid('0');
                $oneListSubLinesArray[] = $oneListSubLine;
            }
        }
        $oneList->setMobiletransactionsubline($oneListSubLinesArray);
        $oneList->setMobiletransactionline($transactionLines);

        return $oneList;
    }

    /**
     * Around plugin for calculating oneList for hospitality
     *
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param RootMobileTransaction $oneList
     * @return RootMobileTransaction|null
     * @throws GuzzleException
     * @throws NoSuchEntityException
     */
    public function aroundCalculate(BasketHelper $subject, callable $proceed, RootMobileTransaction $oneList)
    {
        if ($subject->lsr->getCurrentIndustry($subject->getCorrectStoreIdFromCheckoutSession() ?? null) !=
            \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed($oneList);
        }

        if (!$subject->lsr->isLSR(
            $subject->lsr->getCurrentStoreId(),
            false,
            $subject->lsr->getBasketIntegrationOnFrontend()
        )) {
            return null;
        }

        if (empty($subject->getCouponCode()) && $subject->calculateBasket == 1
            && empty($subject->getOneListCalculationFromCheckoutSession())) {
            return null;
        }

        if ($subject->getCouponCode() != "" && $subject->getCouponCode() != null) {
            $mobileTransactionLines = $oneList->getMobiletransactionline();
            $lineNumber = (count($mobileTransactionLines) + 1) * 10000;
            $transactionId = $oneList->getMobiletransaction()
                ->getId();
            $storeCode = $subject->getDefaultWebStore();
            $listItem = $subject->createInstance(
                MobileTransactionLine::class,
                [
                    'data' => [
                        MobileTransactionLine::ID => $transactionId,
                        MobileTransactionLine::LINE_NO => $lineNumber,
                        MobileTransactionLine::STORE_ID => $storeCode,
                        MobileTransactionLine::QUANTITY => 1,
                        MobileTransactionLine::NUMBER => $subject->getCouponCode(),
                        MobileTransactionLine::BARCODE => $subject->getCouponCode(),
                        MobileTransactionLine::TRANS_DATE => $subject->getCompatibleDateTime(),
                        MobileTransactionLine::CURRENCY_FACTOR => 1,
                        MobileTransactionLine::LINE_TYPE => 6,
                        MobileTransactionLine::TRANSACTION_NO => $lineNumber,
                        MobileTransactionLine::ORIG_TRANS_NO => $lineNumber,
                        MobileTransactionLine::ORIG_TRANS_LINE_NO => $lineNumber,
                    ]
                ]
            );
            $mobileTransactionLines[] = $listItem;
            $oneList->setMobiletransactionline($mobileTransactionLines);
        }
        $oneList->getMobiletransaction()
            ->setCurrencycode($subject->lsr->getStoreCurrencyCode())
            ->setCurrencyfactor((float)$subject->loyaltyHelper->getPointRate());
        $operation = $subject->createInstance(MobilePosCalculate::class);
        $operation->setOperationInput(
            [Entity\MobilePosCalculate::MOBILE_TRANSACTION_XML => $oneList]
        );
        $response = $operation->execute();

        return $response && $response->getResponsecode() == "0000" ? $response->getMobiletransactionxml() : null;
    }

    /**
     * Around plugin for getting Correct Item Row Total for minicart after comparison
     *
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param Item $item
     * @return string
     * @throws GuzzleException
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     */
    public function aroundGetItemRowTotal(BasketHelper $subject, callable $proceed, Item $item)
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($item);
        }
        $rowTotal = $item->getRowTotalInclTax();
        $baseUnitOfMeasure = $item->getProduct()->getData('uom');
        list($itemId, $variantId, $uom) = $subject->itemHelper->getComparisonValues(
            $item->getSku()
        );
        $basketData = $subject->getOneListCalculation();
        if (!empty($basketData)) {
            $orderLines = $basketData->getMobiletransactionline();
            $subLines = $basketData->getMobiletransactionsubline() ?? [];

            foreach ($orderLines as $index => $line) {
                ++$index;

                if ($subject->itemHelper->isValid($item, $line, $itemId, $variantId, $uom, $baseUnitOfMeasure) &&
                    $this->hospitalityHelper->isSameAsSelectedLine($line, $item, $index, $subLines)
                ) {
                    $rowTotal = $this->hospitalityHelper->getAmountGivenLine($line, $subLines);
                    break;
                }
            }
        }

        return $rowTotal;
    }

    /**
     * Around plugin for getting Correct Item Row Discount for minicart after comparison
     *
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param Item $item
     * @param array $lines
     * @return float|int
     * @throws GuzzleException
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     */
    public function aroundGetItemRowDiscount(BasketHelper $subject, callable $proceed, Item $item, array $lines = [])
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($item, $lines);
        }
        $rowDiscount = 0;
        $baseUnitOfMeasure = $item->getProduct()->getData('uom');
        list($itemId, $variantId, $uom) = $subject->itemHelper->getComparisonValues(
            $item->getSku()
        );

        $basketData = $subject->getOneListCalculation();
        if (!empty($basketData)) {
            $orderLines = $basketData->getMobiletransactionline();
            foreach ($orderLines as $line) {
                if ($subject->itemHelper->isValid($item, $line, $itemId, $variantId, $uom, $baseUnitOfMeasure)) {
                    $rowDiscount = $line->getQuantity() == $item->getQty() ? $line->getDiscountamount()
                        : ($line->getDiscountamount() / $line->getQuantity()) * $item->getQty();
                    break;
                }
            }
        }

        return $rowDiscount;
    }

    /**
     * Around plugin to formulate Central Order requests given Magento order
     *
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param \Magento\Sales\Model\Order $order
     * @return RootMobileTransaction
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function aroundFormulateCentralOrderRequestFromMagentoOrder(
        BasketHelper $subject,
        callable $proceed,
        \Magento\Sales\Model\Order $order
    ) {
        if ($subject->lsr->getCurrentIndustry($order->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($order);
        }

        $orderEntity = $subject->createInstance(RootMobileTransaction::class);

        list($mobileTransaction,
            $mobileTransactionLines,
            $mobileTransactionSubLines,
            $mobileTransactionDiscountLines
            ) = $subject->getOrderLinesQuote($order);

        $orderEntity->addData([
            RootMobileTransaction::MOBILE_TRANSACTION => $mobileTransaction,
            RootMobileTransaction::MOBILE_TRANSACTION_LINE => $mobileTransactionLines,
            RootMobileTransaction::MOBILE_TRANSACTION_SUB_LINE => $mobileTransactionSubLines,
            RootMobileTransaction::MOBILE_TRANS_DISCOUNT_LINE => $mobileTransactionDiscountLines,
        ]);

        return $orderEntity;
    }

    /**
     * Get Order Lines and Discount Lines
     *
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param \Magento\Sales\Model\Order $order
     * @return array
     * @throws NoSuchEntityException|LocalizedException
     */
    public function aroundGetOrderLinesQuote(
        BasketHelper $subject,
        callable $proceed,
        \Magento\Sales\Model\Order $order
    ) {
        if ($subject->lsr->getCurrentIndustry($order->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($order);
        }

        $quote = $subject->cartRepository->get($order->getQuoteId());
        $websiteId = $quote->getStore()->getWebsiteId();
        $storeCode = $subject->lsr->getWebsiteConfig(
            \Ls\Core\Model\LSR::SC_SERVICE_STORE,
            $websiteId
        );
        $customerEmail = $order->getCustomerEmail();
        $basketResponse = $quote->getBasketResponse();
        $mobileTransaction = $mobileTransactionLines = $mobileTransactionDiscountLines = $mobileTransactionSubLines =
        $modifierLines = $recipeLines = [];
        $transactionId = '{'.$subject->generateGuid().'}';
        if (!empty($basketResponse)) {
            // phpcs:ignore Magento2.Security.InsecureFunction.FoundWithAlternative
            $basketData     = $subject->restoreModel(unserialize($basketResponse));
            $mobileTransaction = $basketData->getMobiletransaction();
            $mobileTransactionDiscountLines = $basketData->getMobiletransdiscountline();
            $mobileTransactionLines = $basketData->getMobiletransactionline();
            $mobileTransactionSubLines =  $basketData->getMobiletransactionsubline();
        }

        if (empty($mobileTransaction)) {
            $mobileTransaction[] = $subject->createInstance(MobileTransaction::class);
            current($mobileTransaction)->addData([
                MobileTransaction::ID => $transactionId,
                MobileTransaction::STORE_ID => $storeCode,
                MobileTransaction::TRANSACTION_TYPE => 2,
                MobileTransaction::CURRENCY_FACTOR => 1,
                MobileTransaction::SOURCE_TYPE => 1,
            ]);
        }

        if (!$order->getCustomerIsGuest()) {
            $customer = $subject->customerFactory->create()->setWebsiteId($websiteId)->loadByEmail($customerEmail);

            if (empty($customer->getData('lsr_cardid'))) {
                $subject->contactHelper->syncCustomerAndAddress($customer);
                $customer = $subject->contactHelper->loadCustomerByEmailAndWebsiteId($customerEmail, $websiteId);
            }

            current($mobileTransaction)->addData([
                MobileTransaction::MEMBER_CARD_NO => $customer->getData('lsr_cardid'),
            ]);
        }

        $storeCode = $subject->getDefaultWebStore();

        $quoteItems = $quote->getAllVisibleItems();
        if (empty($mobileTransactionLines)) {
            $lineNumber = 0;
            foreach ($quoteItems as $index => $quoteItem) {
                $lineNumber = $lineNumber + 10000;
                list($itemId, $variantId, $uom) =
                    $subject->itemHelper->getItemAttributesGivenQuoteItem($quoteItem);
                $discountPercentage = $discount = null;
                $regularPrice = $quoteItem->getOriginalPrice();
                $finalPrice = $quoteItem->getPriceInclTax();
                $priceIncTax = $regularPrice;
                $deficit = $subject->getPriceAddingCustomOptions($quoteItem, $priceIncTax);
                $deficit = $deficit - $priceIncTax;
                $finalPrice = $finalPrice - ($deficit / $quoteItem->getQty());

                if ($finalPrice < $regularPrice) {
                    $discount = ($regularPrice - $finalPrice) * $quoteItem->getData('qty');
                    $discountPercentage = (($regularPrice - $finalPrice) / $regularPrice) * 100;
                }
                $cartRuleDiscount = 0;
                $rowTotalInclTax = $quoteItem->getRowTotalInclTax();
                if ($quoteItem->getDiscountPercent() > 0) {
                    $cartRuleDiscount = ($finalPrice * $quoteItem->getQty()) * ($quoteItem->getDiscountPercent() / 100);
                    $rowTotalInclTax = $rowTotalInclTax - $cartRuleDiscount;
                }

                if ($deficit > 0) {
                    $rowTotalInclTax -= $deficit;
                }

                if ($cartRuleDiscount > 0) {
                    $regularPrice *= $quoteItem->getQty();
                    $discount = $regularPrice - $rowTotalInclTax;
                    $discountPercentage = ($discount / $regularPrice) * 100;
                }

                $product = $quoteItem->getProduct();

                $mobileTransactionSubLines = [];
                $selectedSubLines = $this->hospitalityHelper->getSelectedOrderHospSubLineGivenQuoteItem(
                    $quoteItem,
                    $lineNumber
                );

                if (!empty($selectedSubLines['deal'])) {
                    $dealLines = array_merge($selectedSubLines['deal'], $dealLines);
                }

                if (!empty($selectedSubLines['modifier'])) {
                    $modifierLines = array_merge($selectedSubLines['modifier'], $modifierLines);
                }

                if (!empty($selectedSubLines['recipe'])) {
                    $recipeLines = array_merge($selectedSubLines['recipe'], $recipeLines);
                }
                $subLinesLineNumber = 0;
                if (!empty($dealLines)) {
                    // Custom sort: entries without DealModLineId and uom come first
                    usort($dealLines, function ($a, $b) {
                        $aHasModAndUom = isset($a['DealModLineId']) && isset($a['uom']);
                        $bHasModAndUom = isset($b['DealModLineId']) && isset($b['uom']);

                        // If one has and the other doesn't, the one without comes first
                        if ($aHasModAndUom !== $bHasModAndUom) {
                            return $aHasModAndUom ? 1 : -1;
                        }

                        // Optional: sort by LineNumber if both are equal on above condition
                        return ($a['ParentSubLineId'] ?? 0) <=> ($b['ParentSubLineId'] ?? 0);
                    });
                    foreach ($dealLines as $dealLine) {
                        $subLinesLineNumber = $subLinesLineNumber + 10000;
                        $oneListSubLine = $subject->createInstance(MobileTransactionSubLine::class)
                            ->setId($transactionId)
                            ->setLineno($subLinesLineNumber)
                            ->setParentlineno($dealLine['ParentSubLineId'] ?? null)
                            ->setLinetype(1)
                            ->setUomid($dealLine['uom'] ?? null)
                            ->setQuantity(1)
                            ->setDealline($dealLine['DealLineId'] ?? null)
                            ->setDealmodline($dealLine['DealModLineId'] ?? null)
                            ->setDealid($dealLine['DealId'] ?? null);
                        $mobileTransactionSubLines[] = $oneListSubLine;
                    }
                }

                if (!empty($modifierLines)) {
                    foreach ($modifierLines as $modifierLine) {
                        $subLinesLineNumber = $subLinesLineNumber + 10000;
                        $oneListSubLine = $subject->createInstance(MobileTransactionSubLine::class)
                            ->setId($transactionId)
                            ->setLineno($subLinesLineNumber)
                            ->setParentlineno($modifierLine['ParentSubLineId'] ?? null)
                            ->setParentlineissubline($modifierLine['ParentLineIsSubline'] ?? 0)
                            ->setQuantity(1)
                            ->setModifiergroupcode($modifierLine['ModifierGroupCode'] ?? null)
                            ->setModifiersubcode($modifierLine['ModifierSubCode'] ?? null)
                            ->setDealid('0');
                        $mobileTransactionSubLines[] = $oneListSubLine;
                    }
                }

                if (!empty($recipeLines)) {
                    foreach ($recipeLines as $recipeLine) {
                        $subLinesLineNumber = $subLinesLineNumber + 10000;
                        $oneListSubLine = $subject->createInstance(MobileTransactionSubLine::class)
                            ->setId($transactionId)
                            ->setLineno($subLinesLineNumber)
                            ->setParentlineno($recipeLine['ParentSubLineId'] ?? null)
                            ->setParentlineissubline($recipeLine['ParentLineIsSubline'] ?? 0)
                            ->setNumber($recipeLine['ItemId'])
                            ->setDealid('0');
                        $mobileTransactionSubLines[] = $oneListSubLine;
                    }
                }

                $transactionLine = $subject->createInstance(
                    MobileTransactionLine::class,
                    [
                        'data' => [
                            MobileTransactionLine::ID => $transactionId,
                            MobileTransactionLine::LINE_NO => $lineNumber,
                            MobileTransactionLine::STORE_ID => $storeCode,
                            MobileTransactionLine::QUANTITY => $quoteItem->getData('qty'),
                            MobileTransactionLine::NUMBER => $itemId,
                            MobileTransactionLine::VARIANT_CODE => $variantId,
                            MobileTransactionLine::UOM_ID => $uom,
                            MobileTransactionLine::PRICE => $priceIncTax ?? $quoteItem->getPriceInclTax(),
                            MobileTransactionLine::NET_AMOUNT => $quoteItem->getRowTotal(),
                            MobileTransactionLine::TRANS_DATE => $subject->getCompatibleDateTime(),
                            MobileTransactionLine::CURRENCY_FACTOR => 1,
                            MobileTransactionLine::DEAL_ITEM =>
                                $product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE)
                        ]
                    ]
                );
                $mobileTransactionLines[] = $transactionLine;
                if ($discountPercentage && $discount) {
                    $orderDiscountLine = $subject->createInstance(MobileTransDiscountLine::class);
                    $orderDiscountLine->addData([
                        MobileTransDiscountLine::LINE_NO => $lineNumber,
                        MobileTransDiscountLine::NO => $lineNumber,
                        MobileTransDiscountLine::DISCOUNT_TYPE => 4,
                        MobileTransDiscountLine::DISCOUNT_AMOUNT => $discount,
                        MobileTransDiscountLine::DISCOUNT_PERCENT => $discountPercentage,
                    ]);
                    $mobileTransactionDiscountLines[] = $orderDiscountLine;
                }
            }
        }

        return [
            $mobileTransaction,
            $mobileTransactionLines,
            $mobileTransactionSubLines,
            $mobileTransactionDiscountLines
        ];
    }
}
