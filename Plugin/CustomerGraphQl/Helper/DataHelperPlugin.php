<?php

namespace Ls\Hospitality\Plugin\CustomerGraphQl\Helper;

use \Ls\Hospitality\Model\LSR;
use \Ls\CustomerGraphQl\Helper\DataHelper;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * DataHelper plugin for sales entries
 */
class DataHelperPlugin
{

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @param LSR $lsr
     */
    public function __construct(
        LSR $lsr
    ) {
        $this->lsr = $lsr;
    }

    /**
     * Around plugin to format modifiers and ingredients in sales entries
     *
     * @param DataHelper $subject
     * @param callable $proceed
     * @param $items
     * @return array
     * @throws NoSuchEntityException
     */
    public function aroundGetItems(
        DataHelper $subject,
        callable $proceed,
        $items
    ) {

        if (!$this->lsr->isHospitalityStore()) {
            return $proceed($items);
        }

        $itemsArray = [];
        $parent     = 0;
        foreach ($items->getSalesEntryLine() as $item) {
            $data = [
                'amount'                 => $item->getAmount(),
                'click_and_collect_line' => $item->getClickAndCollectLine(),
                'discount_amount'        => $item->getDiscountAmount(),
                'discount_percent'       => $item->getDiscountPercent(),
                'item_description'       => $item->getItemDescription(),
                'item_id'                => $item->getItemId(),
                'item_image_id'          => $item->getItemImageId(),
                'line_number'            => $item->getLineNumber(),
                'line_type'              => $item->getLineType(),
                'net_amount'             => $item->getNetAmount(),
                'net_price'              => $item->getNetPrice(),
                'parent_line'            => $item->getParentLine(),
                'price'                  => $item->getPrice(),
                'quantity'               => $item->getQuantity(),
                'store_id'               => $item->getStoreId(),
                'tax_amount'             => $item->getTaxAmount(),
                'uom_id'                 => $item->getUomId(),
                'variant_description'    => $item->getVariantDescription(),
                'variant_id'             => $item->getVariantId()
            ];
            if (empty($item->getParentLine())) {
                $itemsArray [$item->getLineNumber()] = $data;
                $parent                              = $item->getLineNumber();
            } else {
                if ($parent != $item->getParentLine()) {
                    $itemsArray[$parent]['modifiers'][$item->getParentLine()]['ingredients']
                    [$item->getLineNumber()] = $data;
                } else {
                    $itemsArray[$parent]['modifiers'][$item->getLineNumber()] = $data;
                }
            }
        }

        return $itemsArray;
    }
}
