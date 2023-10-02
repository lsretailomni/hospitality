<?php

namespace Ls\Hospitality\Plugin\Quote\Model;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;

class QuotePlugin
{
    /**
     * @var LSR
     */
    private $hospitalityLsr;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @param LSR $hospitalityLsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        LSR $hospitalityLsr,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->hospitalityLsr = $hospitalityLsr;
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * Set quote as virtual in case of qr code ordering
     *
     * @param Quote $subject
     * @param $result
     * @return mixed|true
     * @throws NoSuchEntityException
     */
    public function afterIsVirtual(Quote $subject, $result)
    {
        if ($this->hospitalityLsr->isHospitalityStore() &&
            $this->hospitalityHelper->removeCheckoutStepEnabled()
        ) {
            $result = true;
        }

        return $result;
    }
}
