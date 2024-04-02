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
        $this->hospitalityLsr    = $hospitalityLsr;
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
            $subject->getShippingAddress()->setShippingMethod('clickandcollect_clickandcollect');
            if (empty($subject->getCustomerEmail())) {
                $subject->setCustomerEmail($this->hospitalityHelper->getAnonymousOrderCustomerEmail());
            }
            if (empty($subject->getBillingAddress()->getFirstname())) {
                $storeInformation = $this->hospitalityHelper->getStoreInformation();
                $subject->getBillingAddress()->setFirstname($storeInformation['name']);
                $subject->getBillingAddress()->setLastname($storeInformation['name']);
            }
            $result = true;
        }

        return $result;
    }
}
