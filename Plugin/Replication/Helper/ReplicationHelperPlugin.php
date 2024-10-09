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
     * @param $product
     * @param $replInvStatus
     * @param $isSyncInventory
     * @param $sourceItems
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function aroundUpdateInventory(
        ReplicationHelper $subject,
        $proceed,
        $product,
        $replInvStatus,
        $isSyncInventory = false,
        $sourceItems = []
    ) {
        $result = $proceed($product, $replInvStatus, $isSyncInventory, $sourceItems);

        if ($this->hospitalityHelper->getLSR()->isHospitalityStore($this->hospitalityHelper->lsr->getCurrentStoreId())) {
            $deals = $this->hospitalityHelper->getAllDealsGivenMainItemSku(
                $product,
                $replInvStatus->getScopeId(),
                $replInvStatus
            );

            foreach ($deals as $deal) {
                $replInvStatus->setSku($deal->getDealNo());
                $result = $proceed(null, $replInvStatus, true, $sourceItems);
            }
        }

        return $result;
    }

    /**
     * Around plugin to change status of manage stock
     *
     * @param ReplicationHelper $subject
     * @param $proceed
     * @param $product
     * @param $type
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function aroundManageStock(
        ReplicationHelper $subject,
        $proceed,
        $product,
        $type
    ) {
        $result = $proceed($product, $type);
        if ($this->hospitalityHelper->lsr->isHospitalityStore($this->hospitalityHelper->lsr->getCurrentStoreId())) {
            $disableInventoryChecking = $this->hospitalityHelper->getLSR()->isDisableInventory();
            if ($disableInventoryChecking) {
                $useManageStock = 0;
                $result->setStockData([
                    'use_config_manage_stock' => $useManageStock
                ]);
            }
        }

        return $result;
    }
}
