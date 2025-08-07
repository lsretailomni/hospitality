<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\OmniGraphQl\Helper;

use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Helper\StockHelper;
use \Ls\Omni\Model\Checkout\DataProvider;
use \Ls\OmniGraphQl\Helper\DataHelper;
use Ls\Replication\Model\ResourceModel\ReplStoreview\Collection;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * DataHelper plugin responsible for intercepting required methods from OmniGraphql DataHelper
 */
class DataHelperPlugin
{
    /**
     * @param LSR $hospitalityLsr
     * @param CheckoutSession $checkoutSession
     * @param StockHelper $stockHelper
     * @param DataProvider $dataProvider
     */
    public function __construct(
        public LSR $hospitalityLsr,
        public CheckoutSession $checkoutSession,
        public StockHelper $stockHelper,
        public DataProvider $dataProvider
    ) {
    }

    /**
     * Around plugin to filter click and collect stores based on configuration for takeaway
     *
     * @param DataHelper $subject
     * @param Collection $result
     * @param string $scopeId
     * @return Collection
     * @throws NoSuchEntityException|LocalizedException
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
            ->addFieldToFilter('click_and_collect', 1);

        if (!$this->availableStoresOnlyEnabled()) {
            return $storesData;
        }

        $itemsCount = $this->checkoutSession->getQuote()->getItemsCount();
        if ($itemsCount > 0) {
            $items = $this->checkoutSession->getQuote()->getAllVisibleItems();
            list($response) = $this->stockHelper->getGivenItemsStockInGivenStore($items);

            if ($response && !empty($response->getInventorybufferout())) {
                $clickNCollectStoresIds = $this->dataProvider->getClickAndCollectStoreIds($storesData);
                $this->dataProvider->filterClickAndCollectStores($response, $clickNCollectStoresIds);

                return $this->dataProvider->filterStoresOnTheBasisOfQty($response, $items);
            }
        }

        return $storesData;
    }

    /**
     * Available Stores only enabled
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function availableStoresOnlyEnabled()
    {
        return $this->hospitalityLsr->getStoreConfig(
            DataProvider::XPATH_CHECKOUT_ITEM_AVAILABILITY,
            $this->hospitalityLsr->getStoreId()
        );
    }
}
