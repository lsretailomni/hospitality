<?php

namespace Ls\Hospitality\Plugin\OmniGraphQl\Helper;

use \Ls\Hospitality\Model\LSR;
use \Ls\OmniGraphQl\Helper\DataHelper;
use \Ls\Replication\Model\ResourceModel\ReplStore\Collection;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * DataHelper plugin responsible for intercepting required methods from OmniGraphql DataHelper
 */
class DataHelperPlugin
{
    /**
     * @var LSR
     */
    public $hospitalityLsr;

    /**
     * @param LSR $hospitalityLsr
     */
    public function __construct(LSR $hospitalityLsr)
    {
        $this->hospitalityLsr = $hospitalityLsr;
    }

    /**
     * Around plugin to filter click and collect stores based on configuration for takeaway
     *
     * @param DataHelper $subject
     * @param Collection $result
     * @param string $scopeId
     * @return Collection
     * @throws NoSuchEntityException
     */
    public function aroundGetStores(
        DataHelper $subject,
        $result,
        $scopeId
    ) {
        $storeCollection = $subject->storeCollectionFactory->create();

        if ($this->hospitalityLsr->isHospitalityStore()) {
            $takeAwaySalesType = $this->hospitalityLsr->getTakeAwaySalesType();

            if (!empty($takeAwaySalesType)) {
                $storeCollection->addFieldToFilter('HospSalesTypes', ['like' => '%'.$takeAwaySalesType.'%']);
            }
        }

        return $storeCollection
            ->addFieldToFilter('scope_id', $scopeId)
            ->addFieldToFilter('ClickAndCollect', 1);
    }
}
