<?php

namespace Ls\Hospitality\Plugin\OmniGraphQl\Helper;

use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Helper\StockHelper;
use \Ls\Omni\Model\Checkout\DataProvider;
use \Ls\OmniGraphQl\Helper\DataHelper;
use \Ls\Replication\Model\ResourceModel\ReplStore\Collection;
use Magento\Checkout\Model\Session as CheckoutSession;
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
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;
    /**
     * @var StockHelper
     */
    private StockHelper $stockHelper;
    /**
     * @var DataProvider
     */
    private DataProvider $dataProvider;

    /**
     * @param LSR $hospitalityLsr
     * @param CheckoutSession $checkoutSession
     * @param StockHelper $stockHelper
     * @param DataProvider $dataProvider
     */
    public function __construct(
        LSR $hospitalityLsr,
        CheckoutSession $checkoutSession,
        StockHelper $stockHelper,
        DataProvider $dataProvider
    ) {
        $this->hospitalityLsr    = $hospitalityLsr;
        $this->checkoutSession   = $checkoutSession;
        $this->stockHelper       = $stockHelper;
        $this->dataProvider      = $dataProvider;
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

        $storesData = $storeCollection
            ->addFieldToFilter('scope_id', $scopeId)
            ->addFieldToFilter('ClickAndCollect', 1);

        $items = $this->checkoutSession->getQuote()->getAllVisibleItems();
        list($response) = $this->stockHelper->getGivenItemsStockInGivenStore($items);

        if ($response) {
            if (is_object($response)) {
                if (!is_array($response->getInventoryResponse())) {
                    $response = [$response->getInventoryResponse()];
                } else {
                    $response = $response->getInventoryResponse();
                }
            }

            $clickNCollectStoresIds = $this->dataProvider->getClickAndCollectStoreIds($storesData);
            $this->dataProvider->filterClickAndCollectStores($response, $clickNCollectStoresIds);

            return $this->dataProvider->filterStoresOnTheBasisOfQty($response, $items);
        }

        return null;
    }
}
