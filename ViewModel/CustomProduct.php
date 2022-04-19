<?php

namespace Ls\Hospitality\ViewModel;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR as LSRModel;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
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
     * @param LSRModel $lsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(LSRModel $lsr, HospitalityHelper $hospitalityHelper)
    {
        $this->lsr               = $lsr;
        $this->hospitalityHelper = $hospitalityHelper;
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
        $product = $this->hospitalityHelper->getCurrentProduct();
        $existingOptions = $this->hospitalityHelper->optionRepository->getProductOptions($product);

        return count($existingOptions);
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
        return $this->hospitalityHelper
                    ->storeManager
                    ->getStore()
                    ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA).$swatch;
    }
}
