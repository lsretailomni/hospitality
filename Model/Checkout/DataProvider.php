<?php

namespace Ls\Hospitality\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class DataProvider for passing values to checkout */
class DataProvider implements ConfigProviderInterface
{
    const SERVICE_MODE_ENABLED = 'ls_mag/service_mode/status';
    const SERVICE_MODE_OPTIONS = 'ls_mag/service_mode/options';

    /** @var StoreManagerInterface */
    public $storeManager;

    /** @var ScopeConfigInterface */
    public $scopeConfig;

    /**
     * @var PageFactory
     */
    public $resultPageFactory;

    /**
     * DataProvider constructor.
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        PageFactory $resultPageFactory
    ) {
        $this->storeManager      = $storeManager;
        $this->scopeConfig       = $scopeConfig;
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        $config = [
            'shipping' => [
                'service_mode' => [
                    'options' => $this->getServiceModeValues(),
                    'enabled' => $this->isServiceModeEnabled()
                ]
            ]
        ];
        return $config;
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getStoreId()
    {
        return $this->storeManager->getStore()->getStoreId();
    }

    /**
     * @return mixed
     */
    public function isServiceModeEnabled()
    {
        return $this->scopeConfig->getValue(
            self::SERVICE_MODE_ENABLED,
            ScopeInterface::SCOPE_WEBSITES,
            $this->storeManager->getStore()->getWebsiteId()
        );
    }

    /**
     * @return mixed
     */
    public function getServiceModeOptions()
    {
        return $this->scopeConfig->getValue(
            self::SERVICE_MODE_OPTIONS,
            ScopeInterface::SCOPE_WEBSITES,
            $this->storeManager->getStore()->getWebsiteId()
        );
    }

    /**
     * @return array
     */
    public function getServiceModeValues()
    {
        $serviceOptions = [];
        $options        = $this->getServiceModeOptions();
        if (!empty($options)) {
            $optionsArray      = explode(",", $options);
            foreach ($optionsArray as $optionValue) {
                $serviceOptions[$optionValue] = $optionValue;
            }
        }

        return $serviceOptions;
    }
}
