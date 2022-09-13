<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Session;

use \Ls\Hospitality\Helper\QrCodeHelper;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver class responsible for setting relevant QR perms in session
 */
class SetQRCodeParamsInSession implements ResolverInterface
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
        if (empty($args['qr_code_id'])) {
            throw new GraphQlInputException(__('Required parameter "qr_code_id" is missing'));
        }
        $qrCodeId = $args['qr_code_id'];

        $params =  explode('&', $this->qrCodeHelper->decrypt($qrCodeId));

        foreach ($params as $index => $param) {
            $params[explode('=', $param)[0]] = explode('=', $param)[1];
            unset($params[$index]);
        }

        $storeId = $params['?store_no'] ?? null;
        if (!$storeId) {
            $storeId = $params['store_id'] ?? null;

            if (!$storeId) {
                throw new GraphQlInputException(__('Invalid "qr_code_id" is being used'));
            }
        }

        if (!empty($storeId) && $this->qrCodeHelper->validateStoreId($storeId)) {
            $this->qrCodeHelper->setQrCodeOrderingInSession($params);
        }

        return [];
    }
}
