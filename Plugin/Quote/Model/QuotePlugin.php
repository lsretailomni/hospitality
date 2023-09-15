<?php

namespace Ls\Hospitality\Plugin\Quote\Model;

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
     * @param LSR $hospitalityLsr
     */
    public function __construct(
        LSR $hospitalityLsr
    ) {
        $this->hospitalityLsr = $hospitalityLsr;
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
            $this->hospitalityLsr->getStoreConfig(Lsr::ANONYMOUS_REMOVE_CHECKOUT_STEPS, $subject->getStoreId())
        ) {
            $result = true;
        }

        return $result;
    }
}
