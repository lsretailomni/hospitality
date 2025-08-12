<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\CustomerGraphQl\Helper;

use \Ls\CustomerGraphQl\Helper\DataHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * DataHelper plugin for sales entries
 */
class DataHelperPlugin
{
    /**
     * @param LSR $lsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        public LSR $lsr,
        public HospitalityHelper $hospitalityHelper
    ) {
    }

    /**
     * Around plugin to format modifiers and ingredients in sales entries
     *
     * @param DataHelper $subject
     * @param callable $proceed
     * @param $items
     * @param $magOrder
     * @return array
     * @throws NoSuchEntityException
     */
    public function aroundGetItems(
        DataHelper $subject,
        callable $proceed,
        $items,
        $magOrder
    ) {
        if (!$this->lsr->isHospitalityStore()) {
            return $proceed($items);
        }

        return $this->hospitalityHelper->getItems($subject, $items, $magOrder);
    }
}
