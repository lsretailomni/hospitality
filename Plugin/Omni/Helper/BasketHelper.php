<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use Exception;
use \Ls\Core\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\Ecommerce\Operation;
use \Ls\Omni\Client\ResponseInterface;
use \Ls\Omni\Exception\InvalidEnumException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;

/**
 * BasketHelper Plugin
 */
class BasketHelper
{
    /**
     * @param \Ls\Omni\Helper\BasketHelper $subject
     * @param Entity\OneList $list
     * @return Entity\OneList[]
     * @throws NoSuchEntityException
     */
    public function beforeSaveToOmni(\Ls\Omni\Helper\BasketHelper $subject, Entity\OneList $list)
    {
        $industry = $subject->lsr->getCurrentIndustry();
        $list->setIsHospitality($industry == LSR::LS_INDUSTRY_VALUE_HOSPITALITY);
        return [$list];
    }

    /**
     * @param \Ls\Omni\Helper\BasketHelper $subject
     * @param callable $proceed
     * @param Entity\OneList $oneList
     * @return Entity\OneListCalculateResponse|Entity\OneListHospCalculateResponse|Entity\Order|Entity\OrderHosp|ResponseInterface|null
     * @throws NoSuchEntityException
     * @throws InvalidEnumException
     */
    public function aroundCalculate(\Ls\Omni\Helper\BasketHelper $subject, callable $proceed, Entity\OneList $oneList)
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
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
                ->setStoreId($storeId)
                ->setIsHospitality($oneList->getIsHospitality());

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
     * @param \Ls\Omni\Helper\BasketHelper $subject
     * @param callable $proceed
     * @param $item
     * @return string
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     */
    public function aroundGetItemRowTotal(\Ls\Omni\Helper\BasketHelper $subject, callable $proceed, $item)
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($item);
        }
        $itemSku = explode("-", $item->getSku());
        if (count($itemSku) < 2) {
            $itemSku[1] = null;
        }
        $rowTotal   = "";
        $basketData = $subject->getOneListCalculation();
        $orderLines = $basketData->getOrderLines()->getOrderHospLine();
        foreach ($orderLines as $line) {
            if ($itemSku[0] == $line->getItemId() && $itemSku[1] == $line->getVariantId()) {
                $rowTotal = $line->getAmount();
                break;
            }
        }
        return $rowTotal;
    }

    /**
     * @param \Ls\Omni\Helper\BasketHelper $subject
     * @param callable $proceed
     * @param $couponCode
     * @return Entity\OneListCalculateResponse|Entity\Order|Phrase|string
     * @throws InvalidEnumException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws Exception
     */
    public function aroundSetCouponCode(\Ls\Omni\Helper\BasketHelper $subject, callable $proceed, $couponCode)
    {
        if ($subject->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
            return $proceed($couponCode);
        }
        $couponCode = trim($couponCode);
        if ($couponCode == "") {
            $subject->couponCode = '';
            $subject->setCouponQuote("");
            $subject->update(
                $subject->get()
            );
            $subject->itemHelper->setDiscountedPricesForItems(
                $subject->checkoutSession->getQuote(),
                $subject->getBasketSessionValue()
            );

            return $status = '';
        }
        $subject->couponCode = $couponCode;
        $status              = $subject->update(
            $subject->get()
        );

        $checkCouponAmount = $subject->data->orderBalanceCheck(
            $subject->checkoutSession->getQuote()->getLsGiftCardNo(),
            $subject->checkoutSession->getQuote()->getLsGiftCardAmountUsed(),
            $subject->checkoutSession->getQuote()->getLsPointsSpent(),
            $status
        );

        if (!is_object($status) && $checkCouponAmount) {
            $subject->couponCode = '';
            $subject->update(
                $subject->get()
            );
            $subject->setCouponQuote($subject->couponCode);

            return $status;
        }
        foreach ($status->getOrderLines()->getOrderHospLine() as $basket) {
            $discountsLines = $basket->getDiscountLines();
            foreach ($discountsLines as $orderDiscountLine) {
                if ($orderDiscountLine->getDiscountType() == 'Coupon') {
                    $status = "success";
                    $subject->itemHelper->setDiscountedPricesForItems(
                        $subject->checkoutSession->getQuote(),
                        $subject->getBasketSessionValue()
                    );
                    $subject->setCouponQuote($subject->couponCode);
                    return $status;
                }
            }
        }
        $this->setCouponQuote("");
        return __("Coupon Code is not valid for these item(s)");
    }
}
