<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Product;

use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\UrlInterface;

class SwatchPath implements ResolverInterface
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
     * Add proper swatch image path
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     *
     * @return string
     *
     * @throws NoSuchEntityException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['swatch']) || empty($value['swatch'])) {
            return '';
        }

        return $this->hospitalityHelper
                ->storeManager
                ->getStore()
                ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $value['swatch'];
    }
}
