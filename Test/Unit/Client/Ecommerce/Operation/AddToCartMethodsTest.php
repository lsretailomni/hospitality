<?php

namespace Ls\Hospitality\Test\Unit\Client\Ecommerce\Operation;

use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneList;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListItem;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListItemSubLine;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListPublishedOffer;
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
        $response = $this->client->ItemGetbyId($param);
        $result   = $response->getResult();
        $this->assertInstanceOf(LoyItem::class, $result);
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
        $response = $this->client->OneListHospCalculate($entity);
        $result   = $response->getResult();
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
        $response = $this->client->OneListHospCalculate($entity);
        $result   = $response->getResult();
        $this->assertInstanceOf(OrderHosp::class, $result);
        $this->assertEquals($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'), $result->getStoreId());
        $this->assertNotNull($result->getTotalAmount());
        $this->assertNotNull($result->getTotalNetAmount());
        $this->assertInstanceOf(ArrayOfOrderHospLine::class, $result->getOrderLines());
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
            ->setIsHospitality(1);
        $param    = ['oneList' => $oneListRequest, 'calculate' => true];
        $response = $this->client->OneListSave($param);
        $oneList  = $response->getResult();
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
        $response = $this->client->OneListGetByCardId($param);
        $result   = $response->getResult();
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
        $param    = [
            'oneList' => $oneListRequest,
            'calculate' => true
        ];
        $response = $this->client->OneListSave($param);
        $oneList  = $response->getResult();
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
        $response = $this->client->OneListHospCalculate($entity);
        $result   = $response->getResult();
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
