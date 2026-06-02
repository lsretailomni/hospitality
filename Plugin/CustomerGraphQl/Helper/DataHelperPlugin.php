<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\CustomerGraphQl\Helper;

use \Ls\CustomerGraphQl\Helper\DataHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Client\Ecommerce\Entity\SalesEntry;
use \Ls\Omni\Helper\OrderHelper;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * DataHelper plugin for sales entries
 */
class DataHelperPlugin
{
    /**
     * @param LSR $lsr
     * @param HospitalityHelper $hospitalityHelper
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        public LSR $lsr,
        public HospitalityHelper $hospitalityHelper,
        public OrderHelper $orderHelper
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

        return $this->hospitalityHelper->getItems($items);
    }

    /**
     * After plugin on getSaleEntry to inject tip amount into SalesEntry GraphQL response.
     * First tries to get tip from LS Central sales entry lines via TIPS_ITEM_ID.
     * Falls back to Magento order ls_tip_amount if not found.
     *
     * @param DataHelper $subject
     * @param array $result
     * @param SalesEntry $salesEntry
     * @param mixed|null $magOrder
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterGetSaleEntry(
        DataHelper $subject,
        array $result,
        $salesEntry,
        $magOrder = null
    ): array {
        if ($this->lsr->isHospitalityStore()) {
            $tip        = 0.0;
            $tipsItemId = $this->lsr->getStoreConfig(
                LSR::TIPS_ITEM_ID,
                $this->lsr->getCurrentStoreId()
            );

            if ($tipsItemId) {
                $orderLines = $salesEntry->getLines();

                if ($orderLines) {
                    foreach ($orderLines->getSalesEntryLine() as $line) {
                        if ($line->getItemId() == $tipsItemId) {
                            $tip = $line->getAmount();
                            break;
                        }
                    }
                }
            }

            if (!$tip) {
                if (!$magOrder) {
                    $magOrder = $this->orderHelper->getOrderByDocumentId($salesEntry);
                }

                if ($magOrder) {
                    $tip = $magOrder->getData('ls_tip_amount');
                }
            }

            $result['tip'] = $tip;
        }

        return $result;
    }
}
