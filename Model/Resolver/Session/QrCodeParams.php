<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Session;

use \Ls\Hospitality\Helper\QrCodeHelper;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver class responsible for returning qr_code_params
 */
class QrCodeParams implements ResolverInterface
{
    /**
     * @var QrCodeHelper
     */
    public $qrCodeHelper;

    /**
     * @param QrCodeHelper $qrCodeHelper
     */
    public function __construct(QrCodeHelper $qrCodeHelper)
    {
        $this->qrCodeHelper = $qrCodeHelper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        return $this->qrCodeHelper->getFormattedQrCodeParamsInCustomerSession();
    }
}
