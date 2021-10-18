<?php

namespace Ls\Hospitality\Model\Checkout;

use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Helper\StoreHelper;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class DataProvider for passing values to checkout */
class DataProvider implements ConfigProviderInterface
{
    /**
     * @var LSR
     */
    public $hospLsr;

    /**
     * @var StoreHelper
     */
    public $storeHelper;

    /**
     * @param LSR $hospLsr
     * @param StoreHelper $storeHelper
     */
    public function __construct(
        LSR $hospLsr,
        StoreHelper $storeHelper
    ) {
        $this->hospLsr     = $hospLsr;
        $this->storeHelper = $storeHelper;
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        $dateTimeSlotsValues = $this->storeHelper->formatDateTimeSlotsValues();
        return [
            'shipping' => [
                'service_mode'          => [
                    'options' => $this->getServiceModeValues(),
                    'enabled' => $this->hospLsr->isServiceModeEnabled()
                ],
                'pickup_date_timeslots' => [
                    'options' => $dateTimeSlotsValues,
                    'enabled' => $this->hospLsr->isPickupTimeslotsEnabled()
                ]
            ]
        ];
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getServiceModeValues()
    {
        $serviceOptions = [];
        $options        = $this->hospLsr->getServiceModeOptions();
        if (!empty($options)) {
            $optionsArray = explode(",", $options);
            foreach ($optionsArray as $optionValue) {
                $serviceOptions[$optionValue] = $optionValue;
            }
        }

        return $serviceOptions;
    }
}
