<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Address;

use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;

/**
 * Resolver class responsible for getting json encoded config for anonymous order address attributes
 */
class Attributes implements ResolverInterface
{
    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @var SerializerJson
     */
    public $serializerJson;

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param SerializerJson $serializerJson
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        SerializerJson $serializerJson
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
        $this->serializerJson = $serializerJson;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $storeId           = (int)$context->getExtensionAttributes()->getStore()->getId();
        $addressAttributes = $this->hospitalityHelper->getformattedAddressAttributesConfig($storeId);

        return $this->serializerJson->serialize($addressAttributes);
    }
}
