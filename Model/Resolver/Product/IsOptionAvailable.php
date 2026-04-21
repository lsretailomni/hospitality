<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Product;

use \Ls\Hospitality\Model\Order\CheckAvailability;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class IsOptionAvailable implements ResolverInterface
{
    /**
     * @var CheckAvailability
     */
    private $checkAvailability;

    /**
     * @param CheckAvailability $checkAvailability
     */
    public function __construct(
        CheckAvailability $checkAvailability
    ) {
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
     * @throws NoSuchEntityException
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
