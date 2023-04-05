<?php
declare(strict_types=1);

namespace hospitality\Model\Resolver\Store;

use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;

/**
 * Resolver class responsible for getting store information for anonymous ordering
 */
class StoreInfo implements ResolverInterface
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
        $this->serializerJson    = $serializerJson;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $storeInfo              = [];
        $storeInformation       = $this->hospitalityHelper->getStoreInformation();
        $storeInfo['firstname'] = $storeInformation->getData(HospitalityHelper::ADDRESS_ATTRIBUTE_MAPPER['firstname']);
        $storeInfo['lastname']  = $storeInformation->getData(HospitalityHelper::ADDRESS_ATTRIBUTE_MAPPER['lastname']);
        $storeInfo['phone']     = $storeInformation->getData(HospitalityHelper::ADDRESS_ATTRIBUTE_MAPPER['phone']);
        $storeInfo['email']     = $this->hospitalityHelper->getAnonymousOrderCustomerEmail();
        return $this->serializerJson->serialize($storeInfo);
    }
}
