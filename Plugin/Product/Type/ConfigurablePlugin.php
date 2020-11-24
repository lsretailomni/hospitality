<?php

namespace Ls\Hospitality\Plugin\Product\Type;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * To remove dash delimiter in case of custom option
 */
class ConfigurablePlugin
{

    /**
     * @param Configurable $subject
     * @param $result
     * @param $product
     * @return mixed
     */
    public function afterGetSku(
        Configurable $subject,
        $result,
        $product
    ) {
        $simpleOption = $product->getCustomOption('simple_product');
        if ($simpleOption) {
            $optionProduct = $simpleOption->getProduct();
            $simpleSku = null;
            if ($optionProduct) {
                $simpleSku = $simpleOption->getProduct()->getSku();
                $result = $simpleSku;
            }
        }
        return $result;
    }
}
