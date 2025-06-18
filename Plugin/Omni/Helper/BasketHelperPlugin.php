<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use GuzzleHttp\Exception\GuzzleException;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListItemSubLine;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOrderHospSubLine;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\SubLineType;
use Ls\Omni\Client\Ecommerce\Entity\OneListCalculateResponse;
use Ls\Omni\Client\Ecommerce\Entity\OneListHospCalculateResponse;
use Ls\Omni\Client\Ecommerce\Entity\Order;
use \Ls\Omni\Client\Ecommerce\Entity\OrderHosp;
use Ls\Omni\Client\Ecommerce\Entity\RootMobileTransaction;
use \Ls\Omni\Client\Ecommerce\Operation;
use \Ls\Omni\Client\ResponseInterface;
use \Ls\Omni\Exception\InvalidEnumException;
use \Ls\Omni\Helper\BasketHelper;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\Exception\AlreadyExistsException;
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
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
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
     * @throws NoSuchEntityException
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

        // @codingStandardsIgnoreLine
        $items = new Entity\ArrayOfOneListItem();

        $itemsArray = [];

        foreach ($quoteItems as $index => $quoteItem) {
            ++$index;
            list($itemId, $variantId, $uom, $barCode) =
                $subject->itemHelper->getItemAttributesGivenQuoteItem($quoteItem);
            $product = $quoteItem->getProduct();

            $oneListSubLinesArray = [];
            $selectedSubLines     = $this->hospitalityHelper->getSelectedOrderHospSubLineGivenQuoteItem(
                $quoteItem,
                $index
            );

            if (!empty($selectedSubLines['deal'])) {
                foreach ($selectedSubLines['deal'] as $subLine) {
                    $oneListSubLine         = (new Entity\OneListItemSubLine())
                        ->setDealLineId($subLine['DealLineId'] ?? null)
                        ->setDealModLineId($subLine['DealModLineId'] ?? null)
                        ->setLineNumber($subLine['LineNumber'] ?? null)
                        ->setUom($subLine['uom'] ?? null)
                        ->setQuantity(1)
                        ->setType(SubLineType::DEAL);
                    $oneListSubLinesArray[] = $oneListSubLine;
                }
            }

            if (!empty($selectedSubLines['modifier'])) {
                foreach ($selectedSubLines['modifier'] as $subLine) {
                    $oneListSubLine         = (new Entity\OneListItemSubLine())
                        ->setDealLineId($subLine['DealLineId'] ?? null)
                        ->setParentSubLineId($subLine['ParentSubLineId'] ?? null)
                        ->setModifierGroupCode($subLine['ModifierGroupCode'])
                        ->setModifierSubCode($subLine['ModifierSubCode'])
                        ->setQuantity(1)
                        ->setType(SubLineType::MODIFIER);
                    $oneListSubLinesArray[] = $oneListSubLine;
                }
            }

            if (!empty($selectedSubLines['recipe'])) {
                foreach ($selectedSubLines['recipe'] as $subLine) {
                    $oneListSubLine         = (new Entity\OneListItemSubLine())
                        ->setDealLineId($subLine['DealLineId'] ?? null)
                        ->setParentSubLineId($subLine['ParentSubLineId'] ?? null)
                        ->setItemId($subLine['ItemId'])
                        ->setQuantity(0)
                        ->setType(SubLineType::MODIFIER);
                    $oneListSubLinesArray[] = $oneListSubLine;
                }
            }
            // @codingStandardsIgnoreLine
            $list_item    = (new Entity\OneListItem())
                ->setIsADeal($product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE))
                ->setQuantity($quoteItem->getData('qty'))
                ->setItemId($itemId)
                ->setId($quoteItem->getItemId())
                ->setBarcodeId($barCode)
                ->setVariantId($variantId)
                ->setUnitOfMeasureId($uom)
                ->setAmount($quoteItem->getPrice())
                ->setPrice($quoteItem->getPrice())
                ->setImmutable(true)
                ->setOnelistSubLines(
                    (new ArrayOfOneListItemSubLine())->setOneListItemSubLine($oneListSubLinesArray)
                );
            $itemsArray[] = $list_item;
        }
        $items->setOneListItem($itemsArray);

        $oneList->setItems($items)
            ->setPublishedOffers($subject->_offers());

        $subject->setOneListInCustomerSession($oneList);

        return $oneList;
    }

    /**
     * Around plugin for calculating oneList for hospitality
     *
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param RootMobileTransaction $oneList
     * @return OneListCalculateResponse|OneListHospCalculateResponse|Order|OrderHosp|ResponseInterface|null
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     * @throws GuzzleException
     * @throws AlreadyExistsException
     */
    public function aroundCalculate(BasketHelper $subject, callable $proceed, RootMobileTransaction $oneList)
    {
        if ($subject->lsr->getCurrentIndustry(
            $subject->getCorrectStoreIdFromCheckoutSession() ?? null
        ) != \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed($oneList);
        }

        if ((empty($subject->getCouponCode()) && $subject->calculateBasket == 1
                && empty($subject->getOneListCalculationFromCheckoutSession())) ||
            !$subject->lsr->isLSR(
                $subject->lsr->getCurrentStoreId(),
                false,
                $subject->lsr->getBasketIntegrationOnFrontend()
            )) {
            return null;
        }

        // @codingStandardsIgnoreLine
        $storeId = $subject->getDefaultWebStore();
        $cardId  = $oneList->getCardId();

        /** @var Entity\ArrayOfOneListItem $oneListItems */
        $oneListItems = $oneList->getItems();

        /** @var Entity\OneListCalculateResponse $response */
        $response = false;

        if (!($oneListItems->getOneListItem() == null)) {
            /** @var Entity\OneListItem || Entity\OneListItem[] $listItems */
            $listItems = $oneListItems->getOneListItem();

            if (!is_array($listItems)) {
                /** Entity\ArrayOfOneListItem $items */
                // @codingStandardsIgnoreLine
                $items = new Entity\ArrayOfOneListItem();
                $items->setOneListItem($listItems);
                $listItems = $items;
            }

            // @codingStandardsIgnoreStart
            $oneListRequest = (new Entity\OneList())
                ->setCardId($cardId)
                ->setListType(Entity\Enum\ListType::BASKET)
                ->setItems($listItems)
                ->setStoreId($storeId);

            if (version_compare($subject->lsr->getOmniVersion(), '4.19', '>')) {
                $oneListRequest
                    ->setIsHospitality(true)
                    ->setSalesType($this->hospitalityHelper->getLSR()->getDeliverySalesType());
            } else {
                $oneListRequest
                    ->setHospitalityMode(\Ls\Omni\Client\Ecommerce\Entity\Enum\HospMode::TAKEAWAY);
            }

            if (version_compare($subject->lsr->getOmniVersion(), '4.24', '>')) {
                $oneListRequest->setShipToCountryCode($oneList->getShipToCountryCode());
            }

            /** @var Entity\OneListCalculate $entity */
            if ($subject->getCouponCode() != "" and $subject->getCouponCode() != null) {
                $offer  = new Entity\OneListPublishedOffer();
                $offers = new Entity\ArrayOfOneListPublishedOffer();
                $offers->setOneListPublishedOffer($offer);
                $offer->setId($subject->getCouponCode());
                $offer->setType("Coupon");
                $oneListRequest->setPublishedOffers($offers);
            } else {
                $oneListRequest->setPublishedOffers($subject->_offers());
            }

            $entity  = new Entity\OneListHospCalculate();
            $request = new Operation\OneListHospCalculate();

            $entity->setOneList($oneListRequest);
            $response = $request->execute($entity);
        }

        if (($response == null)) {
            // @codingStandardsIgnoreLine
            $oneListCalResponse = new Entity\OneListCalculateResponse();

            return $oneListCalResponse->getResult();
        }

        if (property_exists($response, "OneListCalculateResult")) {
            // @codingStandardsIgnoreLine
            $subject->setOneListCalculationInCheckoutSession($response->getResult());
            return $response->getResult();
        }

        if (is_object($response)) {
            $subject->setOneListCalculationInCheckoutSession($response->getResult());
            return $response->getResult();
        } else {
            return $response;
        }
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
        $rowTotal   = $item->getRowTotalInclTax();
        $baseUnitOfMeasure = $item->getProduct()->getData('uom');
        list($itemId, $variantId, $uom) = $subject->itemHelper->getComparisonValues(
            $item->getSku()
        );
        $basketData = $subject->getOneListCalculation();
        if (!empty($basketData)) {
            $orderLines = $basketData->getOrderLines()->getOrderHospLine();

            foreach ($orderLines as $index => $line) {
                ++$index;

                if (
                    $subject->itemHelper->isValid($item, $line, $itemId, $variantId, $uom, $baseUnitOfMeasure) &&
                    $this->hospitalityHelper->isSameAsSelectedLine($line, $item, $index)
                ) {
                    $rowTotal = $this->hospitalityHelper->getAmountGivenLine($line);
                    break;
                }
            }
        }

        return $rowTotal;
    }

    /**
     *
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
        $rowDiscount       = 0;
        $baseUnitOfMeasure = $item->getProduct()->getData('uom');
        list($itemId, $variantId, $uom) = $subject->itemHelper->getComparisonValues(
            $item->getSku()
        );

        $basketData = $subject->getOneListCalculation();
        if (!empty($basketData)) {
            $orderLines = $basketData->getOrderLines()->getOrderHospLine();
            foreach ($orderLines as $line) {
                if ($subject->itemHelper->isValid($item, $line, $itemId, $variantId, $uom, $baseUnitOfMeasure)) {
                    $rowDiscount = $line->getQuantity() == $item->getQty() ? $line->getDiscountAmount()
                        : ($line->getDiscountAmount() / $line->getQuantity()) * $item->getQty();
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
     * @param $order
     * @return Entity\OneListCalculateResponse|Entity\Order
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function aroundFormulateCentralOrderRequestFromMagentoOrder(
        BasketHelper $subject,
        callable $proceed,
        $order
    ) {
        if ($subject->lsr->getCurrentIndustry($order->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($order);
        }

        $orderEntity   = new Entity\OrderHosp();
        $quote         = $subject->cartRepository->get($order->getQuoteId());
        $websiteId     = $order->getStore()->getWebsiteId();
        $customerEmail = $order->getCustomerEmail();
        $webStore      = $subject->lsr->getWebsiteConfig(
            \Ls\Core\Model\LSR::SC_SERVICE_STORE,
            $websiteId
        );
        $orderEntity->setStoreId($webStore);

        if (!$order->getCustomerIsGuest()) {
            $customer = $subject->customerFactory->create()->setWebsiteId($websiteId)->loadByEmail($customerEmail);

            if (empty($customer->getData('lsr_cardid'))) {
                $subject->contactHelper->syncCustomerAndAddress($customer);
                $customer = $subject->contactHelper->loadCustomerByEmailAndWebsiteId($customerEmail, $websiteId);
            }

            $orderEntity->setCardId($customer->getData('lsr_cardid'));
        }
        $orderDetails            = $subject->getOrderLinesQuote($quote);
        $orderLinesArray         = $orderDetails['orderLinesArray'];
        $orderDiscountLinesArray = $orderDetails['orderDiscountLinesArray'];
        $orderEntity->setOrderLines($orderLinesArray);
        $orderEntity->setOrderDiscountLines($orderDiscountLinesArray);
        return $orderEntity;
    }

    /**
     * Get Order Lines and Discount Lines
     *
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param Quote $quote
     * @return array
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     */
    public function aroundGetOrderLinesQuote(
        BasketHelper $subject,
        callable $proceed,
        Quote $quote
    ) {
        if ($subject->lsr->getCurrentIndustry($quote->getStoreId()) != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($quote);
        }

        $basketResponse  = $quote->getBasketResponse();
        $discountsArray  = [];
        $itemsArray      = [];
        if (!empty($basketResponse)) {
            // phpcs:ignore Magento2.Security.InsecureFunction.FoundWithAlternative
            $basketData     = unserialize($basketResponse);
            $discountsArray = $basketData->getOrderDiscountLines();
            $itemsArray     = $basketData->getOrderLines();
        }

        $quoteItems = $quote->getAllVisibleItems();
        $orderLinesArray = new Entity\ArrayOfOrderHospLine();
        if (empty($itemsArray)) {
            $itemsArray = [];
            $lineNumber = 10000;
            foreach ($quoteItems as $index => $quoteItem) {
                ++$index;
                list($itemId, $variantId, $uom) =
                    $subject->itemHelper->getItemAttributesGivenQuoteItem($quoteItem);
                $discountPercentage = $discount = null;
                $regularPrice = $quoteItem->getOriginalPrice();
                $finalPrice   = $quoteItem->getPriceInclTax();
                $priceIncTax = $regularPrice;
                $deficit = $subject->getPriceAddingCustomOptions($quoteItem, $priceIncTax);
                $deficit = $deficit - $priceIncTax;
                $finalPrice   = $finalPrice - ($deficit / $quoteItem->getQty());

                if ($finalPrice < $regularPrice) {
                    $discount           = ($regularPrice - $finalPrice) * $quoteItem->getData('qty');
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

                $oneListSubLinesArray = [];
                $selectedSubLines     = $this->hospitalityHelper->getSelectedOrderHospSubLineGivenQuoteItem(
                    $quoteItem,
                    $index
                );

                if (!empty($selectedSubLines['deal'])) {
                    foreach ($selectedSubLines['deal'] as $subLine) {
                        $oneListSubLine         = (new Entity\OrderHospSubLine())
                            ->setDealLineId($subLine['DealLineId'] ?? null)
                            ->setDealModifierLineId($subLine['DealModLineId'] ?? null)
                            ->setLineNumber($subLine['LineNumber'] ?? null)
                            ->setUom($subLine['uom'] ?? null)
                            ->setQuantity(1)
                            ->setType(SubLineType::DEAL);
                        $oneListSubLinesArray[] = $oneListSubLine;
                    }
                }

                if (!empty($selectedSubLines['modifier'])) {
                    foreach ($selectedSubLines['modifier'] as $subLine) {
                        $oneListSubLine         = (new Entity\OrderHospSubLine())
                            ->setDealLineId($subLine['DealLineId'] ?? null)
                            ->setParentSubLineId($subLine['ParentSubLineId'] ?? null)
                            ->setModifierGroupCode($subLine['ModifierGroupCode'])
                            ->setModifierSubCode($subLine['ModifierSubCode'])
                            ->setQuantity(1)
                            ->setType(SubLineType::MODIFIER);
                        $oneListSubLinesArray[] = $oneListSubLine;
                    }
                }

                if (!empty($selectedSubLines['recipe'])) {
                    foreach ($selectedSubLines['recipe'] as $subLine) {
                        $oneListSubLine         = (new Entity\OrderHospSubLine())
                            ->setDealLineId($subLine['DealLineId'] ?? null)
                            ->setParentSubLineId($subLine['ParentSubLineId'] ?? null)
                            ->setItemId($subLine['ItemId'])
                            ->setQuantity(0)
                            ->setType(SubLineType::MODIFIER);
                        $oneListSubLinesArray[] = $oneListSubLine;
                    }
                }
                // @codingStandardsIgnoreLine
                $orderLine    = (new Entity\OrderHospLine())
                    ->setIsADeal($product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE))
                    ->setQuantity($quoteItem->getData('qty'))
                    ->setItemId($itemId)
                    ->setId($quoteItem->getItemId())
                    ->setVariantId($variantId)
                    ->setUomId($uom)
                    ->setLineNumber($lineNumber)
                    ->setAmount($rowTotalInclTax)
                    ->setNetAmount($quoteItem->getRowTotal())
                    ->setPrice($priceIncTax ?? $quoteItem->getPriceInclTax())
                    ->setNetPrice($quoteItem->getPrice())
                    ->setTaxAmount($quoteItem->getTaxAmount())
                    ->setDiscountAmount($discount)
                    ->setDiscountPercent($discountPercentage)
                    ->setLineType(Entity\Enum\LineType::ITEM)
                    ->setSubLines(
                        (new ArrayOfOrderHospSubLine())->setOrderHospSubLine($oneListSubLinesArray)
                    );
                $itemsArray[] = $orderLine;
                if ($discountPercentage && $discount) {
                    $orderDiscountLine = (new Entity\OrderDiscountLine())
                        ->setDiscountAmount($discount)
                        ->setDiscountPercent($discountPercentage)
                        ->setDiscountType(Entity\Enum\DiscountType::LINE)
                        ->setLineNumber($lineNumber);
                    $discountsArray[] = $orderDiscountLine;
                }
                $lineNumber += 10000;
            }
        }
        $orderLinesArray->setOrderHospLine($itemsArray);

        return [
            'orderLinesArray'         => ($basketResponse) ? $itemsArray : $orderLinesArray,
            'orderDiscountLinesArray' => $discountsArray
        ];
    }
}
