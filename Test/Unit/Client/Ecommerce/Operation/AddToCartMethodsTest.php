<?php

namespace Ls\Hospitality\Test\Unit\Client\Ecommerce\Operation;

use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneList;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListItem;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListItemSubLine;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListPublishedOffer;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOrderDiscountLine;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOrderHospLine;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOrderHospSubLine;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\ListType;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\SubLineType;
use \Ls\Omni\Client\Ecommerce\Entity\LoyItem;
use \Ls\Omni\Client\Ecommerce\Entity\OneList;
use \Ls\Omni\Client\Ecommerce\Entity\OneListHospCalculate;
use \Ls\Omni\Client\Ecommerce\Entity\OneListItem;
use \Ls\Omni\Client\Ecommerce\Entity\OneListItemSubLine;
use \Ls\Omni\Client\Ecommerce\Entity\OneListPublishedOffer;
use \Ls\Omni\Client\Ecommerce\Entity\OrderHosp;
use \Ls\Omni\Exception\InvalidEnumException;
use \Ls\Omni\Test\Unit\Client\Ecommerce\Operation\OmniClientSetupTest;

/**
 * It will cover all the methods for Add to cart - Basket Calculation
 *
 */
class AddToCartMethodsTest extends OmniClientSetupTest
{
    /**
     * Lookup Item
     */
    public function testItemGetbyId()
    {
        $param    = [
            'itemId'  => $this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'),
            'storeId' => $this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'),
        ];
        $response = $this->executeMethod("ItemGetbyId", $param);
        $result = $response ? $response->getResult() : null;
        $this->assertInstanceOf(LoyItem::class, $result);
    }

    /**
     * Get One List
     *
     * @return mixed
     * @throws InvalidEnumException
     */
    public function getOneList($cardId = '')
    {
        $listItems = new OneListItem();
        $listItems
            ->setItemId($this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'))
            ->setUnitOfMeasureId($this->getEnvironmentVariableValueGivenName('HOSP_UNIT_OF_MEASURE_ID'))
            ->setQuantity(1);
        $itemsArray = new ArrayOfOneListItem();
        $itemsArray->setOneListItem($listItems);
        $oneListRequest = new OneList();
        $oneListRequest
            ->setItems($itemsArray)
            ->setCardId($cardId)
            ->setStoreId($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'))
            ->setListType(ListType::BASKET);
        $param = [
            'oneList' => $oneListRequest,
            'calculate' => true
        ];

        return $this->client->OneListSave($param);
    }

    /**
     * Save Wish type one list
     * @throws InvalidEnumException
     */
    public function testOneListSaveWish()
    {
        $listItems = new OneListItem();
        $listItems
            ->setItemId($this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'))
            ->setUnitOfMeasureId($this->getEnvironmentVariableValueGivenName('HOSP_UNIT_OF_MEASURE_ID'))
            ->setQuantity(1);
        $itemsArray = new ArrayOfOneListItem();
        $itemsArray->setOneListItem($listItems);
        $oneListRequest = new OneList();
        $oneListRequest
            ->setItems($itemsArray)
            ->setCardId($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'))
            ->setStoreId($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'))
            ->setListType(ListType::WISH)
            ->setName(ListType::WISH)
            ->setIsHospitality(1);
        $param    = [
            'oneList' => $oneListRequest,
            'calculate' => false
        ];
        $response = $this->executeMethod("OneListSave", $param);
        $oneList = $response ? $response->getResult() : null;
        $this->assertInstanceOf(OneList::class, $oneList);
        $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'), $oneList->getCardId());
        $this->assertTrue(property_exists($oneList, 'Id'));
        $this->assertTrue(property_exists($oneList, 'ListType'));
        $this->assertTrue(property_exists($oneList, 'CreateDate'));
        $this->assertTrue(property_exists($oneList, 'StoreId'));
        $this->assertTrue(property_exists($oneList, 'TotalAmount'));
        $this->assertTrue(property_exists($oneList, 'TotalDiscAmount'));
        $this->assertTrue(property_exists($oneList, 'TotalNetAmount'));
        $this->assertTrue(property_exists($oneList, 'TotalTaxAmount'));
    }

    /**
     * Get Wish type one lists by Member Card Id
     * @depends testOneListSaveWish
     */
    public function testOneListGetByCardIdWish()
    {
        $param    = [
            'cardId' => $this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'),
            'listType' => ListType::WISH,
            'includeLines' => true
        ];
        $response = $this->executeMethod("OneListGetByCardId", $param);
        $result = $response ? $response->getResult() : null;
        $this->assertInstanceOf(ArrayOfOneList::class, $result);
        foreach ($result as $oneList) {
            $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'), $oneList->getCardId());
            $this->assertTrue(property_exists($oneList, 'Id'));
            $this->assertTrue(property_exists($oneList, 'CreateDate'));
            $this->assertTrue(property_exists($oneList, 'StoreId'));
            $this->assertTrue(property_exists($oneList, 'TotalAmount'));
            $this->assertTrue(property_exists($oneList, 'TotalDiscAmount'));
            $this->assertTrue(property_exists($oneList, 'TotalNetAmount'));
            $this->assertTrue(property_exists($oneList, 'TotalTaxAmount'));
        }
    }

    /**
     * Delete Basket List By OneList Id
     * @depends testOneListGetByCardIdBasket
     */
    public function testOneListDeleteByIdBasket()
    {
        $param    = [
            'cardId' => $this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'),
            'listType' => ListType::BASKET,
            'includeLines' => false
        ];
        $response = $this->client->OneListGetByCardId($param);
        $result   = $response->getResult();
        foreach ($result as $oneList) {
            $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'), $oneList->getCardId());
            $this->assertTrue(property_exists($oneList, 'Id'));
            $paramDelete = [
                'oneListId' => $oneList->getId()
            ];
            $response = $this->executeMethod("OneListDeleteById", $paramDelete);
            $result = $response ? $response->getResult() : null;
            $this->assertTrue(is_bool($result));
        }
    }

    /**
     * Delete wish List By OneList Id
     * @depends testOneListGetByCardIdWish
     */
    public function testOneListDeleteByIdWish()
    {
        $param    = [
            'cardId' => $this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'),
            'listType' => ListType::WISH,
            'includeLines' => false
        ];
        $response = $this->client->OneListGetByCardId($param);
        $result   = $response->getResult();
        foreach ($result as $oneList) {
            $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'), $oneList->getCardId());
            $this->assertTrue(property_exists($oneList, 'Id'));
            $paramDelete = [
                'oneListId' => $oneList->getId()
            ];
            $response = $this->executeMethod("OneListDeleteById", $paramDelete);
            $result = $response ? $response->getResult() : null;
            $this->assertTrue(is_bool($result));
        }
    }

    /**
     * Save Basket type one list
     * @throws InvalidEnumException
     */
    public function testOneListSaveBasket()
    {
        $listItems      = new OneListItem();
        $itemsArray     = new ArrayOfOneListItem();
        $oneListRequest = new OneList();
        $listItems
            ->setItemId($this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'))
            ->setUnitOfMeasureId($this->getEnvironmentVariableValueGivenName('HOSP_UNIT_OF_MEASURE_ID'))
            ->setQuantity(1)
            ->setIsADeal(0)
            ->setOnelistSubLines((new ArrayOfOneListItemSubLine())->setOneListItemSubLine([]));

        $itemsArray->setOneListItem($listItems);

        $oneListRequest
            ->setItems($itemsArray)
            ->setCardId($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'))
            ->setStoreId($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'))
            ->setListType(ListType::BASKET)
            ->setName(ListType::BASKET)
            ->setIsHospitality(1);
        $param    = ['oneList' => $oneListRequest, 'calculate' => true];
        $response = $this->executeMethod("OneListSave", $param);
        $oneList = $response ? $response->getResult() : null;
        $this->assertInstanceOf(OneList::class, $oneList);
        $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'), $oneList->getCardId());
        $this->assertTrue(property_exists($oneList, 'Id'));
        $this->assertTrue(property_exists($oneList, 'ListType'));
        $this->assertTrue(property_exists($oneList, 'PublishedOffers'));
        $this->assertTrue(property_exists($oneList, 'CreateDate'));
        $this->assertTrue(property_exists($oneList, 'StoreId'));
        $this->assertTrue(property_exists($oneList, 'TotalAmount'));
        $this->assertTrue(property_exists($oneList, 'TotalDiscAmount'));
        $this->assertTrue(property_exists($oneList, 'TotalNetAmount'));
        $this->assertTrue(property_exists($oneList, 'TotalTaxAmount'));
    }

    /**
     * Get Basket type one lists by Member Card Id
     * @depends testOneListSaveBasket
     */
    public function testOneListGetByCardIdBasket()
    {
        $param    = [
            'cardId' => $this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'),
            'listType' => ListType::BASKET,
            'includeLines' => true
        ];
        $response = $this->executeMethod("OneListGetByCardId", $param);
        $result = $response ? $response->getResult() : null;
        $this->assertInstanceOf(ArrayOfOneList::class, $result);
        foreach ($result as $oneList) {
            $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'), $oneList->getCardId());
            $this->assertTrue(property_exists($oneList, 'Id'));
            $this->assertTrue(property_exists($oneList, 'ListType'));
            $this->assertTrue(property_exists($oneList, 'PublishedOffers'));
            $this->assertTrue(property_exists($oneList, 'CreateDate'));
            $this->assertTrue(property_exists($oneList, 'StoreId'));
            $this->assertTrue(property_exists($oneList, 'TotalAmount'));
            $this->assertTrue(property_exists($oneList, 'TotalDiscAmount'));
            $this->assertTrue(property_exists($oneList, 'TotalNetAmount'));
            $this->assertTrue(property_exists($oneList, 'TotalTaxAmount'));
        }
    }

    /**
     * Save Basket type one list for Guest
     * @throws InvalidEnumException
     */
    public function testOneListSaveBasketGuest()
    {
        $listItems = new OneListItem();
        $listItems
            ->setItemId($this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'))
            ->setUnitOfMeasureId($this->getEnvironmentVariableValueGivenName('HOSP_UNIT_OF_MEASURE_ID'))
            ->setQuantity(1)
            ->setIsADeal(0)
            ->setOnelistSubLines((new ArrayOfOneListItemSubLine())->setOneListItemSubLine([]));
        $itemsArray = new ArrayOfOneListItem();
        $itemsArray->setOneListItem($listItems);
        $oneListRequest = new OneList();
        $oneListRequest
            ->setItems($itemsArray)
            ->setStoreId($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'))
            ->setListType(ListType::BASKET)
            ->setName(ListType::BASKET)
            ->setIsHospitality(1);
        $param    = [
            'oneList' => $oneListRequest,
            'calculate' => true
        ];
        $response = $this->executeMethod("OneListSave", $param);
        $oneList = $response ? $response->getResult() : null;
        $this->assertInstanceOf(OneList::class, $oneList);
        $this->assertTrue(property_exists($oneList, 'Id'));
        $this->assertTrue(property_exists($oneList, 'ListType'));
        $this->assertTrue(property_exists($oneList, 'CreateDate'));
        $this->assertTrue(property_exists($oneList, 'StoreId'));
        $this->assertTrue(property_exists($oneList, 'TotalAmount'));
        $this->assertTrue(property_exists($oneList, 'TotalDiscAmount'));
        $this->assertTrue(property_exists($oneList, 'TotalNetAmount'));
        $this->assertTrue(property_exists($oneList, 'TotalTaxAmount'));
    }

    /**
     * Apply Coupon as Published Offer with Card Id
     * @throws InvalidEnumException
     */
    public function testApplyCoupon()
    {
        $listItems      = new OneListItem();
        $itemsArray     = new ArrayOfOneListItem();
        $oneListRequest = new OneList();
        $offer          = new OneListPublishedOffer();
        $offers         = new ArrayOfOneListPublishedOffer();
        $listItems
            ->setItemId($this->getEnvironmentVariableValueGivenName('HOSP_SIMPLE_ITEM_ID'))
            ->setQuantity(1)
            ->setIsADeal(0)
            ->setOnelistSubLines((new ArrayOfOneListItemSubLine())->setOneListItemSubLine([]));

        $itemsArray->setOneListItem($listItems);

        $oneListRequest
            ->setItems($itemsArray)
            ->setCardId($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'))
            ->setStoreId($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'))
            ->setListType(ListType::BASKET)
            ->setIsHospitality(1);

        $offers->setOneListPublishedOffer($offer);
        $offer
            ->setId($this->getEnvironmentVariableValueGivenName('HOSP_COUPON_CODE'))
            ->setType('Coupon');
        $oneListRequest->setPublishedOffers($offers);
        $entity = new OneListHospCalculate();
        $entity->setOneList($oneListRequest);
        $response = $this->executeMethod("OneListHospCalculate", $entity);
        $oneList = $response ? $response->getResult() : null;
        $this->assertInstanceOf(OrderHosp::class, $oneList);
        $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'), $oneList->getCardId());
        $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'), $oneList->getStoreId());
        $this->assertInstanceOf(ArrayOfOrderHospLine::class, $oneList->getOrderLines());
        $this->assertInstanceOf(ArrayOfOrderDiscountLine::class, $oneList->getOrderDiscountLines());
        $this->assertGreaterThan(0, $oneList->getTotalAmount());
        $this->assertGreaterThan(0, $oneList->getTotalNetAmount());
        $this->assertGreaterThan(0, $oneList->getTotalDiscount());
        $discountExists = false;

        foreach ($oneList->getOrderDiscountLines() as $oneListItemDiscount) {
            if ($oneListItemDiscount->getOfferNumber() ==
                $this->getEnvironmentVariableValueGivenName('HOSP_COUPON_CODE')) {
                $discountExists = true;
                break;
            }
        }

        $this->assertTrue($discountExists);
    }

    /**
     * Calculates OneList Basket Object and returns Order Object
     * @throws InvalidEnumException
     */
    public function testOneListHospCalculate()
    {
        $oneListRequest = new OneList();
        $listItems      = new OneListItem();
        $itemsArray     = new ArrayOfOneListItem();
        $entity         = new OneListHospCalculate();
        $listItems
            ->setItemId($this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'))
            ->setUnitOfMeasureId($this->getEnvironmentVariableValueGivenName('HOSP_UNIT_OF_MEASURE_ID'))
            ->setQuantity(1)
            ->setIsADeal(0)
            ->setOnelistSubLines((new ArrayOfOneListItemSubLine())->setOneListItemSubLine([]));

        $itemsArray->setOneListItem($listItems);
        $oneListRequest
            ->setItems($itemsArray)
            ->setCardId($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'))
            ->setStoreId($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'))
            ->setListType(ListType::BASKET)
            ->setIsHospitality(1)
            ->setSalesType('TAKEAWAY');

        $entity->setOneList($oneListRequest);
        $response = $this->executeMethod("OneListHospCalculate", $entity);
        $result = $response ? $response->getResult() : null;
        $this->assertInstanceOf(OrderHosp::class, $result);
        $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'), $result->getStoreId());
        $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'), $result->getCardId());
        $this->assertNotNull($result->getTotalAmount());
        $this->assertNotNull($result->getTotalNetAmount());
        $this->assertInstanceOf(ArrayOfOrderHospLine::class, $result->getOrderLines());
    }

    /**
     * Calculates OneList Basket Calculation for guest and returns Order Object
     * @throws InvalidEnumException
     */
    public function testOneListHospCalculateGuest()
    {
        $oneListRequest = new OneList();
        $listItems      = new OneListItem();
        $itemsArray     = new ArrayOfOneListItem();
        $entity         = new OneListHospCalculate();
        $listItems
            ->setItemId($this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'))
            ->setUnitOfMeasureId($this->getEnvironmentVariableValueGivenName('HOSP_UNIT_OF_MEASURE_ID'))
            ->setQuantity(1)
            ->setIsADeal(0)
            ->setOnelistSubLines((new ArrayOfOneListItemSubLine())->setOneListItemSubLine([]));

        $itemsArray->setOneListItem($listItems);
        $oneListRequest
            ->setItems($itemsArray)
            ->setStoreId($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'))
            ->setListType(ListType::BASKET)
            ->setIsHospitality(1)
            ->setSalesType('TAKEAWAY');

        $entity->setOneList($oneListRequest);
        $response = $this->executeMethod("OneListHospCalculate", $entity);
        $result = $response ? $response->getResult() : null;
        $this->assertInstanceOf(OrderHosp::class, $result);
        $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'), $result->getStoreId());
        $this->assertNotNull($result->getTotalAmount());
        $this->assertNotNull($result->getTotalNetAmount());
        $this->assertInstanceOf(ArrayOfOrderHospLine::class, $result->getOrderLines());
    }

    /**
     * Calculates Deal OneList Basket Object and returns Order Object
     * @throws InvalidEnumException
     */
    public function testDealOneListHospCalculate()
    {
        $oneListRequest = new OneList();
        $listItems      = new OneListItem();
        $itemsArray     = new ArrayOfOneListItem();
        $entity         = new OneListHospCalculate();
        $subLinesArray = new ArrayOfOneListItemSubLine();
        $subLinesArray
            ->setOneListItemSubLine([
                (new OneListItemSubLine())
                    ->setDealLineId(10000)
                    ->setLineNumber(10000)
                    ->setQuantity(1)
                    ->setType(SubLineType::DEAL),
                (new OneListItemSubLine())
                    ->setDealLineId(20000)
                    ->setDealModLineId($this->getEnvironmentVariableValueGivenName('HOSP_DEAL_ITEM_MODIFIER_LINE_ID_1'))
                    ->setUom($this->getEnvironmentVariableValueGivenName('HOSP_DEAL_ITEM_MODIFIER_LINE_UOM_1'))
                    ->setQuantity(1)
                    ->setType(SubLineType::DEAL),
                (new OneListItemSubLine())
                    ->setDealLineId(30000)
                    ->setDealModLineId($this->getEnvironmentVariableValueGivenName('HOSP_DEAL_ITEM_MODIFIER_LINE_ID_2'))
                    ->setUom($this->getEnvironmentVariableValueGivenName('HOSP_DEAL_ITEM_MODIFIER_LINE_UOM_2'))
                    ->setQuantity(1)
                    ->setType(SubLineType::DEAL),
                (new OneListItemSubLine())
                    ->setDealLineId(10000)
                    ->setModifierGroupCode($this->getEnvironmentVariableValueGivenName(
                        'HOSP_DEAL_ITEM_MODIFIER_GROUP_CODE_1'
                    ))
                    ->setModifierSubCode($this->getEnvironmentVariableValueGivenName(
                        'HOSP_DEAL_ITEM_MODIFIER_SUB_CODE_1'
                    ))
                    ->setParentSubLineId(10000)
                    ->setQuantity(1)
                    ->setType(SubLineType::MODIFIER),
                (new OneListItemSubLine())
                    ->setDealLineId(10000)
                    ->setModifierGroupCode($this->getEnvironmentVariableValueGivenName(
                        'HOSP_DEAL_ITEM_MODIFIER_GROUP_CODE_2'
                    ))
                    ->setModifierSubCode($this->getEnvironmentVariableValueGivenName(
                        'HOSP_DEAL_ITEM_MODIFIER_SUB_CODE_2'
                    ))
                    ->setParentSubLineId(10000)
                    ->setQuantity(1)
                    ->setType(SubLineType::MODIFIER),
                (new OneListItemSubLine())
                    ->setDealLineId(10000)
                    ->setItemId($this->getEnvironmentVariableValueGivenName(
                        'HOSP_DEAL_ITEM_EXCLUDING_ITEM_ID'
                    ))
                    ->setParentSubLineId(10000)
                    ->setQuantity(0)
                    ->setType(SubLineType::MODIFIER)
            ]);
        $listItems
            ->setItemId($this->getEnvironmentVariableValueGivenName('HOSP_DEAL_ITEM_ID'))
            ->setQuantity(1)
            ->setIsADeal(1)
            ->setOnelistSubLines($subLinesArray);

        $itemsArray->setOneListItem($listItems);
        $oneListRequest
            ->setItems($itemsArray)
            ->setCardId($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'))
            ->setStoreId($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'))
            ->setListType(ListType::BASKET)
            ->setIsHospitality(1)
            ->setSalesType('TAKEAWAY');

        $entity->setOneList($oneListRequest);
        $response = $this->executeMethod("OneListHospCalculate", $entity);
        $result = $response ? $response->getResult() : null;
        $this->assertInstanceOf(OrderHosp::class, $result);
        $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'), $result->getStoreId());
        $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'), $result->getCardId());
        $this->assertNotNull($result->getTotalAmount());
        $this->assertNotNull($result->getTotalNetAmount());
        $this->assertInstanceOf(ArrayOfOrderHospLine::class, $result->getOrderLines());
        $this->assertEquals(
            $this->getEnvironmentVariableValueGivenName('HOSP_DEAL_ITEM_ID'),
            current($result->getOrderLines()->getOrderHospLine())->getItemId()
        );
        $this->assertInstanceOf(
            ArrayOfOrderHospSubLine::class,
            current($result->getOrderLines()->getOrderHospLine())->getSubLines()
        );
    }
}
