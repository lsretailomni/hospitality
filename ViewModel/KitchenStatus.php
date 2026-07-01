<?php

declare(strict_types=1);

namespace Ls\Hospitality\ViewModel;

use Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * View model for the Hyvä "Kitchen Status" footer link.
 */
class KitchenStatus implements ArgumentInterface
{
    /**
     * @param LSR $lsr
     */
    public function __construct(
        private LSR $lsr
    ) {
    }

    /**
     * Whether the current store runs the hospitality industry.
     *
     * @return bool
     */
    public function isHospitalityStore(): bool
    {
        try {
            return (bool)$this->lsr->isHospitalityStore();
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }
}
