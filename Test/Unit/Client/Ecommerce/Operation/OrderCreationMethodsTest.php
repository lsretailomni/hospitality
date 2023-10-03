<?php

namespace Ls\Hospitality\Test\Unit\Client\Ecommerce\Operation;

use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfInventoryRequest;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfInventoryResponse;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListItem;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOneListItemSubLine;
use \Ls\Omni\Client\Ecommerce\Entity\ArrayOfOrderPayment;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\ListType;
use \Ls\Omni\Client\Ecommerce\Entity\InventoryRequest;
use \Ls\Omni\Client\Ecommerce\Entity\ItemsInStoreGetEx;
use \Ls\Omni\Client\Ecommerce\Entity\OneList;
use \Ls\Omni\Client\Ecommerce\Entity\OneListHospCalculate;
use \Ls\Omni\Client\Ecommerce\Entity\OneListItem;
use \Ls\Omni\Client\Ecommerce\Entity\OrderHosp;
use \Ls\Omni\Client\Ecommerce\Entity\OrderPayment;
use \Ls\Omni\Client\Ecommerce\Entity\SalesEntry;
use \Ls\Omni\Exception\InvalidEnumException;
use \Ls\Omni\Test\Unit\Client\Ecommerce\Operation\OmniClientSetupTest;

/**
 * It will cover all the methods used for Order Creation Cycle
 */
class OrderCreationMethodsTest extends OmniClientSetupTest
{

    /**
     * Generate GUID
     *
     * @return string
     */
    public function generateGUID()
    {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        //phpcs:disable
        return sprintf(
            '{%04X%04X-%04X-%04X-%04X-%04X%04X%04X}',
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535)
        );
        //phpcs:enable
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
            ->setQuantity(1)
            ->setIsADeal(0)
            ->setOnelistSubLines((new ArrayOfOneListItemSubLine())->setOneListItemSubLine([]));
        $itemsArray = new ArrayOfOneListItem();
        $itemsArray->setOneListItem($listItems);
        $oneListRequest = new OneList();
        $oneListRequest
            ->setItems($itemsArray)
            ->setCardId($cardId)
            ->setStoreId($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'))
            ->setListType(ListType::BASKET)
            ->setName(ListType::BASKET)
            ->setIsHospitality(1);
        $param = [
            'oneList' => $oneListRequest,
            'calculate' => true
        ];

        return $this->client->OneListSave($param);
    }

    /**
     * Get stock status of an item from all stores
     * If storeId is empty, only store that are marked in LS Nav/Central with
     * check box Loyalty or Mobile checked (Omni Section) will be returned
     */
    public function testItemsInStockGetAllStores()
    {
        $itemStock = new ItemsInStoreGetEx();
        $itemStock->setUseSourcingLocation(true);
        $itemStock->setLocationId('');
        $inventoryRequest = new InventoryRequest();
        $inventoryRequest->setItemId($this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'));
        $inventoryRequestArray = new ArrayOfInventoryRequest();
        $inventoryRequestCollection[] = $inventoryRequest;
        $inventoryRequestArray->setInventoryRequest($inventoryRequestCollection);
        $itemStock->setItems($inventoryRequestArray);
        $itemStock->setStoreId('');
        $response = $this->client->ItemsInStoreGetEx($itemStock);
        $result   = $response->getResult();
        $this->assertInstanceOf(ArrayOfInventoryResponse::class, $result);
        foreach ($result as $inventoryResponse) {
            $this->assertEquals(
                $this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'),
                $inventoryResponse->getItemId()
            );
            $this->assertNotNull($inventoryResponse->getStoreId());
            $this->assertTrue(property_exists($inventoryResponse, 'QtyInventory'));
            $this->assertTrue(is_string($inventoryResponse->getQtyInventory()));
        }
    }

    /**
     * Get stock status for list of items from one store
     */
    public function testItemsInStoreGetSingleStore()
    {
        $itemStock = new ItemsInStoreGetEx();
        $itemStock
            ->setUseSourcingLocation(true)
            ->setLocationId('')
            ->setStoreId($this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'));
        $inventoryRequest = new InventoryRequest();
        $inventoryRequest->setItemId($this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'));
        $inventoryRequestArray = new ArrayOfInventoryRequest();
        $inventoryRequestCollection[] = $inventoryRequest;
        $inventoryRequestArray->setInventoryRequest($inventoryRequestCollection);
        $itemStock->setItems($inventoryRequestArray);
        $response = $this->client->ItemsInStoreGetEx($itemStock);
        $result   = $response->getResult();
        $this->assertInstanceOf(ArrayOfInventoryResponse::class, $result);
        foreach ($result as $inventoryResponse) {
            $this->assertEquals(
                $this->getEnvironmentVariableValueGivenName('HOSP_ITEM_ID'),
                $inventoryResponse->getItemId()
            );
            $this->assertEquals(
                $this->getEnvironmentVariableValueGivenName('HOSP_STORE_ID'),
                $inventoryResponse->getStoreId()
            );
            $this->assertTrue(property_exists($inventoryResponse, 'QtyInventory'));
            $this->assertTrue(is_string($inventoryResponse->getQtyInventory()));
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
     * Create Customer Order for Takeaway using pay at the store Payment Line only
     * Type - Takeaway
     * User - Member
     * PaymentLine - pay at the store
     * @depends testOneListSaveBasket
     */
    public function testOrderHospCreate()
    {
        $response       = $this->getOneList($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'));
        $oneListRequest = $response->getResult();
        $entity         = new OneListHospCalculate();
        $entity->setOneList($oneListRequest);
        $response = $this->client->OneListHospCalculate($entity);
        $result   = $response->getResult();
        $this->assertInstanceOf(OrderHosp::class, $result);
        $datetime = new \DateTime('tomorrow + 1day');
        $result
            ->setId($this->generateGUID())
            ->setExternalId('test' . substr(preg_replace("/[^A-Za-z0-9 ]/", '', $result->getId()), 0, 10))
            ->setSalesType($this->getEnvironmentVariableValueGivenName('HOSP_SALES_TYPE'))
            ->setRestaurantNo($result->getStoreId())
            ->setPickupTime($datetime->format('Y-m-d'). 'T01:00:00');
        // Order creation request
        $paramOrderCreate  = [
            'request' => $result
        ];
        $responseOrder     = $this->client->OrderHospCreate($paramOrderCreate);
        $resultOrderCreate = $responseOrder->getResult();
        $this->assertInstanceOf(SalesEntry::class, $resultOrderCreate);
        $this->assertTrue(property_exists($resultOrderCreate, 'Id'));
        $this->assertTrue(property_exists($resultOrderCreate, 'CardId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'ExternalId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'StoreId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalAmount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalDiscount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalNetAmount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Status'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Payments'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Lines'));
    }

    /**
     * Create Customer Order for Takeaway using Online Payment Line only
     * Type - Takeaway
     * User - Member
     * PaymentLine - Online Card
     * @depends testOneListSaveBasket
     */
    public function testOrderHospCreateOnlinePayment()
    {
        $response       = $this->getOneList($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'));
        $oneListRequest = $response->getResult();
        $entity         = new OneListHospCalculate();
        $entity->setOneList($oneListRequest);
        $response = $this->client->OneListHospCalculate($entity);
        $result   = $response->getResult();
        $this->assertInstanceOf(OrderHosp::class, $result);
        $datetime = new \DateTime('tomorrow + 1day');
        $orderPayment = new OrderPayment();
        $orderPayment->setCurrencyFactor(1)
            ->setAmount($result->getTotalAmount())
            ->setLineNumber('1')
            ->setExternalReference('TEST0012345')
            ->setTenderType($this->getEnvironmentVariableValueGivenName('HOSP_CREDIT_CARD_TENDER_TYPE'))
            ->setCardType('VISA')
            ->setCardNumber('4111111111111111')
            ->setTokenNumber('1276349812634981234')
            ->setPaymentType('Payment');
        $orderPayments = new ArrayOfOrderPayment();
        $orderPayments->setOrderPayment([$orderPayment]);
        $result->setOrderPayments($orderPayments);
        $result
            ->setId($this->generateGUID())
            ->setExternalId('test' . substr(preg_replace("/[^A-Za-z0-9 ]/", '', $result->getId()), 0, 10))
            ->setSalesType($this->getEnvironmentVariableValueGivenName('HOSP_SALES_TYPE'))
            ->setRestaurantNo($result->getStoreId())
            ->setPickupTime($datetime->format('Y-m-d'). 'T01:00:00');
        // Order creation request
        $paramOrderCreate  = [
            'request' => $result
        ];
        $responseOrder     = $this->client->OrderHospCreate($paramOrderCreate);
        $resultOrderCreate = $responseOrder->getResult();
        $this->assertInstanceOf(SalesEntry::class, $resultOrderCreate);
        $this->assertTrue(property_exists($resultOrderCreate, 'Id'));
        $this->assertTrue(property_exists($resultOrderCreate, 'CardId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'ExternalId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'StoreId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalAmount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalDiscount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalNetAmount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Status'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Payments'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Lines'));
    }

    /**
     * Create Customer Order for Takeaway using Pay at the store Payment Line only
     * Type - Takeaway
     * User - Guest
     * PaymentLine - Pay at the store
     */
    public function testOrderHospCreateGuest()
    {
        $response       = $this->getOneList();
        $oneListRequest = $response->getResult();
        $entity         = new OneListHospCalculate();
        $entity->setOneList($oneListRequest);
        $response = $this->client->OneListHospCalculate($entity);
        $result   = $response->getResult();
        $this->assertInstanceOf(OrderHosp::class, $result);
        $datetime = new \DateTime('tomorrow + 1day');
        $result
            ->setId($this->generateGUID())
            ->setEmail($this->getEnvironmentVariableValueGivenName('HOSP_EMAIL'))
            ->setExternalId('test' . substr(preg_replace("/[^A-Za-z0-9 ]/", '', $result->getId()), 0, 10))
            ->setSalesType($this->getEnvironmentVariableValueGivenName('HOSP_SALES_TYPE'))
            ->setRestaurantNo($result->getStoreId())
            ->setPickupTime($datetime->format('Y-m-d'). 'T01:00:00');
        // Order creation request
        $paramOrderCreate  = [
            'request' => $result
        ];
        $responseOrder     = $this->client->OrderHospCreate($paramOrderCreate);
        $resultOrderCreate = $responseOrder->getResult();
        $this->assertInstanceOf(SalesEntry::class, $resultOrderCreate);
        $this->assertTrue(property_exists($resultOrderCreate, 'Id'));
        $this->assertTrue(property_exists($resultOrderCreate, 'CardId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'ExternalId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'StoreId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalAmount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalDiscount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalNetAmount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Status'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Payments'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Lines'));
    }

    /**
     * Create Customer Order for Takeaway using Credit Card, Gift Card and Loyalty Payment Line
     * Type - Takeaway
     * User - Member
     * PaymentLines - Credit Card + Gift Card + Loyalty
     */
    public function testOrderHospCreateOnlinePaymentWithGiftCardAndLoyalty()
    {
        $response       = $this->getOneList($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'));
        $oneListRequest = $response->getResult();
        $entity         = new OneListHospCalculate();
        $entity->setOneList($oneListRequest);
        $response = $this->client->OneListHospCalculate($entity);
        $result   = $response->getResult();
        $this->assertInstanceOf(OrderHosp::class, $result);
        $datetime = new \DateTime('tomorrow + 1day');
        $preApprovedDate   = date('Y-m-d', strtotime('+1 years'));
        $orderPayment      = new OrderPayment();
        $orderPayment->setCurrencyFactor(1)
            ->setAmount($result->getTotalAmount() - 0.1 - 1)
            ->setLineNumber('1')
            ->setExternalReference('TEST0012345')
            ->setTenderType($this->getEnvironmentVariableValueGivenName('HOSP_CREDIT_CARD_TENDER_TYPE'));
        $orderPaymentLoyalty = new OrderPayment();
        $orderPaymentLoyalty->setCurrencyCode('LOY')
            ->setCurrencyFactor('0.10000000000000000000')
            ->setLineNumber('2')
            ->setCardNumber($this->getEnvironmentVariableValueGivenName('HOSP_CARD_ID'))
            ->setExternalReference('TEST0012345')
            ->setAmount('1')
            ->setPreApprovedValidDate($preApprovedDate)
            ->setTenderType($this->getEnvironmentVariableValueGivenName('HOSP_LOYALTY_POINTS_TENDER_TYPE'));

        $orderPaymentGift    = new OrderPayment();
        $orderPaymentGift->setCurrencyFactor(1)
            ->setAmount('1')
            ->setLineNumber('3')
            ->setCardNumber($this->getEnvironmentVariableValueGivenName('GIFTCARDCODE'))
            ->setExternalReference('TEST0012345')
            ->setPreApprovedValidDate($preApprovedDate)
            ->setTenderType($this->getEnvironmentVariableValueGivenName('HOSP_GIFT_CARD_TENDER_TYPE'));
        $orderPaymentArray = [$orderPayment, $orderPaymentLoyalty, $orderPaymentGift];
        $orderPayments       = new ArrayOfOrderPayment();
        $orderPayments->setOrderPayment($orderPaymentArray);
        $result->setOrderPayments($orderPayments);
        $result
            ->setId($this->generateGUID())
            ->setExternalId('test' . substr(preg_replace("/[^A-Za-z0-9 ]/", '', $result->getId()), 0, 10))
            ->setSalesType($this->getEnvironmentVariableValueGivenName('HOSP_SALES_TYPE'))
            ->setRestaurantNo($result->getStoreId())
            ->setPickupTime($datetime->format('Y-m-d'). 'T01:00:00');

        // Order creation request
        $paramOrderCreate = ['request' => $result];
        $responseOrder     = $this->client->OrderHospCreate($paramOrderCreate);

        $resultOrderCreate = $responseOrder->getResult();
        $this->assertInstanceOf(SalesEntry::class, $resultOrderCreate);
        $this->assertTrue(property_exists($resultOrderCreate, 'Id'));
        $this->assertTrue(property_exists($resultOrderCreate, 'CardId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'ExternalId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'StoreId'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalAmount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalDiscount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'TotalNetAmount'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Status'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Payments'));
        $this->assertTrue(property_exists($resultOrderCreate, 'Lines'));
    }
}
