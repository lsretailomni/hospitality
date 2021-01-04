<?php

namespace Ls\Hospitality\Model\Checkout;

use \Ls\Hospitality\Model\LSR;
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
     * DataProvider constructor.
     * @param LSR $hospLsr
     */
    public function __construct(
        LSR $hospLsr
    ) {
        $this->hospLsr = $hospLsr;
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        return [
            'shipping' => [
                'service_mode' => [
                    'options' => $this->getServiceModeValues(),
                    'enabled' => $this->hospLsr->isServiceModeEnabled()
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
