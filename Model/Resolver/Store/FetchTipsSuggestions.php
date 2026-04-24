<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Store;

use Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;

/**
 * Resolver class responsible for exposing tip suggestions in StoreConfig.
 */
class FetchTipsSuggestions implements ResolverInterface
{
    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param JsonFactory $serializerJson
     */
    public function __construct(
        private HospitalityHelper $hospitalityHelper,
        private JsonFactory $resultJsonFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!empty($value) && !isset($value['cart_id'])) {
            return $value;
        }
        $tipsEnabled = (bool)$this->hospitalityHelper->isTipsEnabled();
        if(!$tipsEnabled) {
            return [];
        }
        $result = $this->resultJsonFactory->create();

        try {
            $suggestions = $this->hospitalityHelper->getTipsSuggestionsFromStore();

            return [
                'success' => true,
                'message' => '',
                'suggestions' => is_array($suggestions) && !empty($suggestions) ? $suggestions : []
            ];
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
                'suggestions' => []
            ]);
        }
    }
}

