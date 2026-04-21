<?php

namespace Ls\Hospitality\Plugin\Customer\ViewModel;

use \Ls\Customer\ViewModel\ItemRenderer;
use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Format items for hospitality
 */
class ItemRendererPlugin
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
     * @param LSR $lsr
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        LSR $lsr,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->lsr               = $lsr;
        $this->hospitalityHelper = $hospitalityHelper;
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
        ItemRenderer $subject,
        callable $proceed,
        $item
    ) {
        if (!$this->lsr->isHospitalityStore()) {
            return $proceed($item);
        }
        $items = $subject->getOrder()->getLines()->getSalesEntryLine();
        return $this->hospitalityHelper->getItems($subject, $items, null);
    }
}