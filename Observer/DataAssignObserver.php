<?php

namespace Ls\Hospitality\Observer;

use Carbon\Carbon;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\StoreHourCalendarType;
use \Ls\Omni\Helper\StoreHelper;
use \Ls\Hospitality\Model\Order\CheckAvailability;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Phrase;

/**
 * Class DataAssignObserver for assigning service mode value to order
 */
class DataAssignObserver implements ObserverInterface
{
    /**
     * @var Http
     */
    private Http $request;
    /**
     * @var LSR
     */
    private LSR $lsr;
    /**
     * @var StoreHelper
     */
    private StoreHelper $storeHelper;

    /**
     * @var CheckAvailability
     */
    private $checkAvailability;

    /**
     * @param StoreHelper $storeHelper
     * @param CheckAvailability $checkAvailability
     * @param Http $request
     * @param LSR $lsr
     */
    public function __construct(
        StoreHelper $storeHelper,
        CheckAvailability $checkAvailability,
        Http $request,
        LSR $lsr
    ) {
        $this->storeHelper       = $storeHelper;
        $this->checkAvailability = $checkAvailability;
        $this->request           = $request;
        $this->lsr               = $lsr;
    }

    /**
     * For setting values in quote
     *
     * @param Observer $observer
     * @return DataAssignObserver
     * @throws NoSuchEntityException
     * @throws ValidatorException
     * @throws \Zend_Log_Exception
     */
    public function execute(Observer $observer)
    {
        $quote                      = $observer->getQuote();
        $order                      = $observer->getOrder();
        $shippingMethod = $quote->getShippingAddress()->getShippingMethod();
        $validatePickupDateRangeMsg = "";
        $pickupStore = "";
        if ($quote->getServiceMode()) {
            $order->setServiceMode($quote->getServiceMode());
        }

        if ($quote->getData(LSR::LS_ORDER_COMMENT)) {
            $order->setData(LSR::LS_ORDER_COMMENT, $quote->getData(LSR::LS_ORDER_COMMENT));
        }

        if ($quote->getData(LSR::LS_QR_CODE_ORDERING)) {
            $order->setData(LSR::LS_QR_CODE_ORDERING, $quote->getData(LSR::LS_QR_CODE_ORDERING));
        }

        if ($this->lsr->isHospitalityStore()) {
            $this->checkAvailability->validateQty();
        }

        if ($this->lsr->isHospitalityStore() &&
            ($shippingMethod == 'clickandcollect_clickandcollect' || $shippingMethod == 'flatrate_flatrate')
        ) {
            if ($shippingMethod == 'flatrate_flatrate') {
                $pickupStore = $this->lsr->getActiveWebStore();
            } else {
                $pickupStore = $quote->getPickupStore();
            }
            if (str_contains($this->request->getOriginalPathInfo(), "graphql")) {
                $validatePickupDateRangeMsg = ($this->lsr->getStoreConfig(
                    LSR::LSR_GRAPHQL_DATETIME_RANGE_VALIDATION_ACTIVE,
                    $this->lsr->getCurrentStoreId()
                )) ? $this->validatePickupDateRange($quote, $pickupStore, $shippingMethod) : '';
            } else {
                $validatePickupDateRangeMsg = ($this->lsr->getStoreConfig(
                    LSR::LSR_DATETIME_RANGE_VALIDATION_ACTIVE,
                    $this->lsr->getCurrentStoreId()
                )) ? $this->validatePickupDateRange($quote, $pickupStore, $shippingMethod) : '';
            }
        }

        if ($validatePickupDateRangeMsg) {
            throw new ValidatorException($validatePickupDateRangeMsg);
        }

        return $this;
    }

    /**
     * Validate pickup date and time range
     *
     * @param $quote
     * @param $storeId
     * @param $shippingMethod
     * @return Phrase|null
     * @throws NoSuchEntityException
     */
    public function validatePickupDateRange($quote, $storeId, $shippingMethod)
    {
        $message       = null;
        $validDateTime = false;
        if ($storeId && !empty($quote->getPickupDateTimeslot())) {
            $pickupDateTimeArr = explode(" ", $quote->getPickupDateTimeslot());

            $pickupTimeStamp = Carbon::parse($quote->getPickupDateTimeslot());
            /**
             * @var \Magento\Quote\Model\Quote $quote
             */
            $websiteId       = $quote->getStore()->getWebsiteId();
            $store           = $this->storeHelper->getStore($websiteId, $storeId);
            $storeHoursArray = $this->storeHelper->formatDateTimeSlotsValues(
                $store->getStoreHours(),
                $shippingMethod == 'flatrate_flatrate' ? StoreHourCalendarType::RECEIVING : null
            );

            foreach ($storeHoursArray as $date => $hoursArr) {
                $openHoursCnt = count($hoursArr);
                if ($date == "Today") {
                    $date = $this->storeHelper->getCurrentDate();
                }

                if ($openHoursCnt > 0 && $date == $pickupDateTimeArr[0]) {
                    $storeOpeningTimeStamp = Carbon::parse($date . " " . $hoursArr[0]);
                    $storeClosingTimeStamp = Carbon::parse($date . " " . $hoursArr[$openHoursCnt - 1]);

                    //Validate time range for orders with date and time pick up
                    if ((count($pickupDateTimeArr) > 1)
                        && $pickupTimeStamp->between($storeOpeningTimeStamp, $storeClosingTimeStamp, true)
                    ) {
                        $validDateTime = true;
                    }
                    break;
                }
            }
        }

        if (!$validDateTime) {
            $message = __('Please select a date & time within store opening hours.');
        }

        return $message;
    }
}
