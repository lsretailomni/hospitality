<?php

namespace Ls\Hospitality\Plugin\Replication\Helper;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Replication\Helper\ReplicationHelper;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * ReplicationHelper plugin responsible for intercepting required methods
 */
class ReplicationHelperPlugin
{
    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @param HospitalityHelper $hospitalityHelper
     */

    public function __construct(HospitalityHelper $hospitalityHelper)
    {
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * Around plugin to save inventory of deal type item
     *
     * @param ReplicationHelper $subject
     * @param $proceed
     * @param $sku
     * @param $replInvStatus
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function aroundUpdateInventory(
        ReplicationHelper $subject,
        $proceed,
        $sku,
        $replInvStatus
    ) {
        $result = $proceed($sku, $replInvStatus);

        if ($this->hospitalityHelper->lsr->isHospitalityStore($this->hospitalityHelper->lsr->getCurrentStoreId())) {
            $deals = $this->hospitalityHelper->getAllDealsGivenMainItemSku($sku, $replInvStatus->getScopeId());

            foreach ($deals as $deal) {
                $result = $proceed($deal->getDealNo(), $replInvStatus);
            }
        }

        return $result;
    }
}
