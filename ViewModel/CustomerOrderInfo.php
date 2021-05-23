<?php

namespace Ls\Hospitality\ViewModel;

use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Class for handling customer order additional info
 */
class CustomerOrderInfo implements ArgumentInterface
{
    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * CustomerOrderInfo constructor.
     * @param HospitalityHelper $hospitalityHelper
     * @param LSR $lsr
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        LSR $lsr
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
        $this->lsr               = $lsr;
    }

    /**
     * @return bool
     */
    public function isHospitalityEnabled()
    {
        return $this->lsr->isHospitalityStore();
    }

}
