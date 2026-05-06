<?php

namespace Ls\Hospitality\Plugin\Catalog\Model;

use \Ls\Hospitality\Model\LSR;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * ProductAvailabilityPlugin to modify product availability
 */
class ProductAvailabilityPlugin
{  
    /**
     * @param LSR $lsr
     */
    public function __construct(
        private LSR $lsr
    )
    {
    }

    /**
     * Modify product availability based on ls_current_availability attribute
     *
     * @param Product $subject
     * @param bool $result
     * @return bool
     * @throws NoSuchEntityException
     */
    public function afterIsAvailable(Product $subject, $result)
    {
        if (!$this->lsr->isHospitalityStore()) {
            return $result;
        }

        $isUnavailable = $subject->getData(LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE);

        if ($isUnavailable === null) {
            return $result;
        }

        if ($isUnavailable) {
            return false;
        }

        return $result;
    }
}
