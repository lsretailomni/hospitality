<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Product;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\Order\CheckAvailability;
use Magento\Catalog\Model\Product\Option;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class IsOptionAvailable implements ResolverInterface
{
    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @var CheckAvailability
     */
    private $checkAvailability;

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param CheckAvailability $checkAvailability
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        CheckAvailability $checkAvailability
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
        $this->checkAvailability = $checkAvailability;
    }

    /**
     * Resolve is_available for product option
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (empty($value)) {
            return false;
        }
        return $this->checkAvailability->checkModifierAvailabilityForGraphQl($value);
    }
}
