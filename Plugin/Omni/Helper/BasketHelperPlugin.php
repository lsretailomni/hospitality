<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListItemSubLine;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\SubLineType;
use \Ls\Omni\Client\Ecommerce\Entity\OrderHosp;
use \Ls\Omni\Client\Ecommerce\Operation;
use \Ls\Omni\Client\ResponseInterface;
use \Ls\Omni\Exception\InvalidEnumException;
use \Ls\Omni\Helper\BasketHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;

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
     * Before plugin for setting isHospitality if current industry is hospitality
     *
     * @param BasketHelper $subject
     * @param Entity\OneList $list
     * @return Entity\OneList[]
     * @throws NoSuchEntityException
     */
    public function beforeSaveToOmni(BasketHelper $subject, Entity\OneList $list)
    {
        $industry = $subject->lsr->getCurrentIndustry($subject->getCorrectStoreIdFromCheckoutSession() ?? null);

        if (version_compare($subject->lsr->getOmniVersion(), '4.19', '>')) {
            $list->setIsHospitality(
                $industry == LSR::LS_INDUSTRY_VALUE_HOSPITALITY
            );
        } else {
            $list->setHospitalityMode(
                $industry == LSR::LS_INDUSTRY_VALUE_HOSPITALITY ?
                    \Ls\Omni\Client\Ecommerce\Entity\Enum\HospMode::DELIVERY :
                    \Ls\Omni\Client\Ecommerce\Entity\Enum\HospMode::NONE
            );
        }

        return [$list];
    }

    /**
     * Around plugin for creating oneList in hospitality on the basis of items in the quote
     *
     * @param BasketHelper $subject
     * @param callable $proceed
     * @param Quote $quote
     * @param Entity\OneList $oneList
     * @return mixed
     * @throws NoSuchEntityException|InvalidEnumException
     */
    public function aroundSetOneListQuote(
        BasketHelper $subject,
        callable $proceed,
        Quote $quote,
        Entity\OneList $oneList
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
            list($itemId, $variantId, $uom, $barCode) = $subject->itemHelper->getComparisonValues(
                $quoteItem->getSku()
            );
            $product = $subject->productRepository->getById($quoteItem->getProductId());

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
                ->setId('')
                ->setBarcodeId($barCode)
                ->setVariantId($variantId)
                ->setUnitOfMeasureId($uom)
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
     * @param Entity\OneList $oneList
     * @return Entity\OneListCalculateResponse|Entity\OneListHospCalculateResponse|Entity\Order|OrderHosp|ResponseInterface|null
     * @throws NoSuchEntityException
     * @throws InvalidEnumException
     * @throws \Exception
     */
    public function aroundCalculate(BasketHelper $subject, callable $proceed, Entity\OneList $oneList)
    {
        if (empty($subject->getCouponCode()) && $subject->calculateBasket) {
            return null;
        }

        if ($subject->lsr->getCurrentIndustry(
                $subject->getCorrectStoreIdFromCheckoutSession() ?? null
            ) != \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed($oneList);
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
     * @param $item
     * @return string
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     */
    public function aroundGetItemRowTotal(BasketHelper $subject, callable $proceed, $item)
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($item);
        }
        $rowTotal          = "";
        $baseUnitOfMeasure = $item->getProduct()->getData('uom');
        list($itemId, $variantId, $uom) = $subject->itemHelper->getComparisonValues(
            $item->getSku()
        );
        $basketData = $subject->getOneListCalculation();
        $orderLines = $basketData->getOrderLines()->getOrderHospLine();

        foreach ($orderLines as $index => $line) {
            ++$index;

            if (
                $subject->itemHelper->isValid($line, $itemId, $variantId, $uom, $baseUnitOfMeasure) &&
                $this->hospitalityHelper->isSameAsSelectedLine($line, $item, $index)
            ) {
                $rowTotal = $this->hospitalityHelper->getAmountGivenLine($line);
                break;
            }
        }

        return $rowTotal;
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

        return $subject->calculateOneListFromOrder($order);
    }
}
