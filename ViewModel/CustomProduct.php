<?php

namespace Ls\Hospitality\ViewModel;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR as LSRModel;
use \Ls\Hospitality\Model\Order\CheckAvailability;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CustomProduct implements ArgumentInterface
{
    /**
     * @var LSRModel
     */
    public $lsr;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @var Repository
     */
    public $assetRepository;

    /**
     * @var CheckAvailability
     */
    public $checkAvailability;

    /**
     * @param LSRModel $lsr
     * @param HospitalityHelper $hospitalityHelper
     * @param CheckAvailability $checkAvailability
     * @param Repository $assetRepository
     */
    public function __construct(
        LSRModel $lsr,
        HospitalityHelper $hospitalityHelper,
        CheckAvailability $checkAvailability,
        Repository $assetRepository
    ) {
        $this->lsr               = $lsr;
        $this->hospitalityHelper = $hospitalityHelper;
        $this->checkAvailability = $checkAvailability;
        $this->assetRepository   = $assetRepository;
    }

    /**
     * Is hospitality store
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isHospitalityStore()
    {
        return $this->lsr->isHospitalityStore();
    }

    /**
     * Current Product has options
     *
     * @return int|void
     */
    public function currentProductHasOptions()
    {
        $product         = $this->hospitalityHelper->getCurrentProduct();
        $existingOptions = $this->hospitalityHelper->optionRepository->getProductOptions($product);

        return count($existingOptions);
    }

    /**
     * Current Product has options
     *
     * @return int|void
     */
    public function checkModifierAvailable(&$option)
    {
        return $this->checkAvailability->checkModifierAvailability($option);
    }

    /**
     * Get Media Url
     *
     * @param $swatch
     * @return string
     * @throws NoSuchEntityException
     */
    public function getMediaUrl($swatch)
    {
        if (empty($swatch)) {
            return $this->assetRepository->getUrlWithParams('Ls_Hospitality::images/placeholder.jpg', []);
        }

        return $this->hospitalityHelper
                ->storeManager
                ->getStore()
                ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $swatch;
    }
}
