<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver;

use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * For returning Enable/Disable status of config path mappings
 * based on system configuration and Omni online/offline mode
 */
class IsServiceUpResolver implements ResolverInterface
{
    private const CONFIG_PATHS_MAPPING = [
        'ls_mag_hospitality_order_tracking' => LSR::ORDER_TRACKING_ON_SUCCESS_PAGE
    ];

    /**
     * @var LSR
     */
    public $hospitalityLsr;

    /**
     * @param LSR $hospitalityLsr
     */
    public function __construct(
        LSR $hospitalityLsr
    ) {
        $this->hospitalityLsr = $hospitalityLsr;
    }

    /**
     * Fetch store configuration value based on omni online/offline status.
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return bool
     * @throws NoSuchEntityException
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): bool
    {
        return $this->hospitalityLsr->isLSR($this->hospitalityLsr->getCurrentStoreId()) &&
            isset(self::CONFIG_PATHS_MAPPING[$field->getName()]) &&
            $this->hospitalityLsr->getStoreConfig(
                self::CONFIG_PATHS_MAPPING[$field->getName()],
                $this->hospitalityLsr->getCurrentStoreId()
            );
    }
}
