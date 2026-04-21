<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\InventoryGraphQl\Model\Resolver;

use Magento\InventoryGraphQl\Model\Resolver\StockStatusProvider;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use \Ls\Hospitality\Model\LSR;

/**
 * Plugin to override stock status for hospitality products without stock management
 */
class StockStatusProviderPlugin
{
    private const IN_STOCK = "IN_STOCK";
    private const OUT_OF_STOCK = "OUT_OF_STOCK";

    /**
     * @var LSR
     */
    private $lsr;

    /**
     * @var ProductResource
     */
    private $productResource;

    /**
     * @param LSR $lsr
     * @param ProductResource $productResource
     */
    public function __construct(
        LSR $lsr,
        ProductResource $productResource
    ) {
        $this->lsr             = $lsr;
        $this->productResource = $productResource;
    }

    /**
     * Override stock status for hospitality products
     *
     * @param StockStatusProvider $subject
     * @param callable $proceed
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return string
     */
    public function aroundResolve(
        StockStatusProvider $subject,
        callable $proceed,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): string {

        if (!$this->lsr->isHospitalityStore($context->getExtensionAttributes()->getStore()->getId())) {
            return $proceed($field, $context, $info, $value, $args);
        }

        if (!isset($value['model']) || !$value['model'] instanceof ProductInterface) {
            return $proceed($field, $context, $info, $value, $args);
        }
        $product       = $value['model'];
        $availability  = $this->productResource->getAttributeRawValue(
            $product->getId(),
            LSR::LS_CURRENT_AVAILABILITY_ATTRIBUTE,
            $context->getExtensionAttributes()->getStore()->getId()
        );
        $isUnavailable = (int)$availability === 1;
        return $isUnavailable ? self::OUT_OF_STOCK : self::IN_STOCK;
    }
}
