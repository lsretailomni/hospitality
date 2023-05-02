<?php

namespace Ls\Hospitality\Plugin\Omni\Helper;

use \Ls\Core\Model\LSR;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Helper\StockHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\CatalogInventory\Model\Configuration;

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
     * @var StockItemRepository
     */
    public $stockItemRepository;

    /**
     * @var CheckoutSession
     */
    public $checkoutSession;

    /**
     * @var Configuration
     */
    public $configuration;

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param StockItemRepository $stockItemRepository
     * @param CheckoutSession $checkoutSession
     * @param Configuration $configuration
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        StockItemRepository $stockItemRepository,
        CheckoutSession $checkoutSession,
        Configuration $configuration
    ) {
        $this->hospitalityHelper   = $hospitalityHelper;
        $this->stockItemRepository = $stockItemRepository;
        $this->checkoutSession     = $checkoutSession;
        $this->configuration       = $configuration;
    }

    /**
     * Around plugin to get main deal item sku and pass it
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
            != LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return $proceed(
                $items,
                $storeId
            );
        }
        $useManageStockConfiguration = $this->configuration->getManageStock();
        $stockCollection             = [];
        $useManageStockItemArray     = [];
        $counter                     = 0;
        foreach ($items as &$item) {

            $itemQty = $item->getQty();
            list($parentProductSku, $childProductSku, , , $uomQty) = $subject->itemHelper->getComparisonValues(
                $item->getSku()
            );

            if (!empty($uomQty)) {
                $itemQty = $itemQty * $uomQty;
            }
            $sku     = $item->getSku();
            $product = $this->hospitalityHelper->getProductFromRepositoryGivenSku($sku);
            try {
                $stockItem     = $this->stockItemRepository->get($product->getId());
                $useMangeStock = $stockItem->getUseConfigManageStock();
            } catch (\Exception $e) {
                $useMangeStock = false;
            }
            if ($product->getData(\Ls\Hospitality\Model\LSR::LS_ITEM_IS_DEAL_ATTRIBUTE)) {
                $lineNo = $this->hospitalityHelper->getMealMainItemSku(
                    $product->getData(\Ls\Hospitality\Model\LSR::LS_ITEM_ID_ATTRIBUTE_CODE)
                );

                if ($lineNo) {
                    $stockCollection[] = [
                        'item_id'    => $lineNo,
                        'variant_id' => $childProductSku,
                        'name'       => $item->getName(),
                        'qty'        => $itemQty
                    ];
                }
            } else {
                $stockCollection[] = [
                    'item_id'    => $parentProductSku,
                    'variant_id' => $childProductSku,
                    'name'       => $item->getName(),
                    'qty'        => $itemQty
                ];
            }

            if (!$useManageStockConfiguration) {
                $useMangeStock = false;
            }

            if ($useMangeStock) {
                $item = [
                    'parent' => $parentProductSku,
                    'child'  => $childProductSku
                ];
            } else {
                unset($items[$counter]);
                unset($stockCollection[$counter]);
            }

            $useManageStockItemArray[] = $useMangeStock;

            $counter++;
        }

        if (!in_array(true, $useManageStockItemArray)) {
            $this->checkoutSession->setNoManageStock(1);
        }

        return [
            $subject->getAllItemsStockInSingleStore(
                $storeId,
                $items
            ), $stockCollection
        ];
    }

    /**
     * Before plugin to replace all deal type items sku
     *
     * @param StockHelper $subject
     * @param $storeId
     * @param $items
     * @return array
     * @throws NoSuchEntityException
     */
    public function beforeGetItemsStockInStoreFromSourcingLocation(StockHelper $subject, $storeId, $items)
    {
        if ($this->hospitalityHelper->getLSR()->getCurrentIndustry()
            != LSR::LS_INDUSTRY_VALUE_HOSPITALITY
        ) {
            return [$storeId, $items];
        }

        foreach ($items as &$item) {
            if (isset($item['parent'])) {
                $product = $this->hospitalityHelper->getProductsByItemId($item['parent']);

                if (!empty($product) && $product->getData(\Ls\Hospitality\Model\LSR::LS_ITEM_IS_DEAL_ATTRIBUTE)) {
                    $lineNo = $this->hospitalityHelper->getMealMainItemSku($item['parent']);

                    if ($lineNo) {
                        $item['parent'] = $lineNo;
                    }
                }

            }
        }

        return [$storeId, $items];
    }
}
