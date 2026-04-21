<?php

namespace Ls\Hospitality\Plugin\Catalog\Model;

use \Ls\Hospitality\Model\LSR;
use Magento\Catalog\Model\Product;

/**
 * ProductAvailabilityPlugin to modify product availability
 */
class ProductAvailabilityPlugin
{
    /**
     * @var LSR
     */
    private $lsr;

    /**
     * @param LSR $lsr
     */
    public function __construct(LSR $lsr)
    {
        $this->lsr = $lsr;
    }

    /**
     * Modify product availability based on ls_current_availability attribute
     *
     * @param Product $subject
     * @param bool $result
     * @return bool
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
