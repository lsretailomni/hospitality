<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\SubLineType;
use \Ls\Omni\Helper\StockHelper;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * StockHelper plugin responsible for intercepting required methods
 */
class StockHelperPlugin
{
    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
    }

    /**
     * Around plugin to filter out deal type items before inventory lookup call
     *
     * @param StockHelper $subject
     * @param $proceed
     * @param $items
     * @param $storeId
     * @return array|mixed
     * @throws NoSuchEntityException
     */
    public function aroundGetGivenItemsStockInGivenStore(StockHelper $subject, $proceed, $items, $storeId = '')
    {
        if ($this->hospitalityHelper->getLSR()->getCurrentIndustry()
            != \Ls\Core\Model\LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed(
                $items,
                $storeId
            );
        }

        $stockCollection = [];

        foreach ($items as $i => &$item) {
            $itemQty = $item->getQty();
            list($parentProductSku, $childProductSku, , , $uomQty) = $subject->itemHelper->getComparisonValues(
                $item->getProductId(),
                $item->getSku()
            );

            if (!empty($uomQty)) {
                $itemQty = $itemQty * $uomQty;
            }
            $sku            = $item->getSku();
            $searchCriteria = $this->hospitalityHelper->searchCriteriaBuilder->addFilter(
                'sku',
                $sku,
                'eq'
            )->create();

            $productList = $this->hospitalityHelper->productRepository->getList($searchCriteria)->getItems();

            $product = array_pop($productList);

            if ($product->getData(LSR::LS_ITEM_IS_DEAL_ATTRIBUTE)) {
                $stockCollection[] = [
                    'sku' => $sku, 'name' => $item->getName(), 'qty' => $itemQty, 'type' => SubLineType::DEAL
                ];
                unset($items[$i]);
                continue;
            } else {
                $stockCollection[] = ['sku' => $sku, 'name' => $item->getName(), 'qty' => $itemQty];
            }

            $item = ['parent' => $parentProductSku, 'child' => $childProductSku];
        }

        return [$subject->getAllItemsStockInSingleStore(
            $storeId,
            $items
        ), $stockCollection];
    }

    /**
     * After plugin to add all deal type items status
     *
     * @param StockHelper $subject
     * @param $result
     * @param $response
     * @param $stockCollection
     * @return mixed
     */
    public function afterUpdateStockCollection(
        StockHelper $subject,
        $result,
        $response,
        $stockCollection
    ) {
        foreach ($result as &$values) {
            if (isset($values['type']) && $values['type'] == SubLineType::DEAL) {
                $values['status']  = '1';
                $values['display'] = __('This item is available');
            }
        }

        return $result;
    }
}
