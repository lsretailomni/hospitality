<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Quote;

use \Ls\Hospitality\Helper\QrCodeHelper;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver class responsible for setting relevant QR perms in session
 */
class SetQRCodeParams implements ResolverInterface
{
    /**
     * @var QrCodeHelper
     */
    public $qrCodeHelper;

    /**
     * @var GetCartForUser
     */
    public $getCartForUser;

    /**
     * @param QrCodeHelper $qrCodeHelper
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(QrCodeHelper $qrCodeHelper, GetCartForUser $getCartForUser)
    {
        $this->qrCodeHelper   = $qrCodeHelper;
        $this->getCartForUser = $getCartForUser;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['input']['qr_code_id'])) {
            throw new GraphQlInputException(__('Required parameter "qr_code_id" is missing'));
        }

        $qrCodeId = $args['input']['qr_code_id'];
        $params   = explode('&', $this->qrCodeHelper->decrypt($qrCodeId));
        $cart     = null;
        if (isset($args['input']['cart_id'])) {
            $maskedCartId  = $args['input']['cart_id'];
            $storeId       = (int)$context->getExtensionAttributes()->getStore()->getId();
            $currentUserId = $context->getUserId();
            $cart          = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
        }


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
            $this->saveQrCode($cart, $params);
        }

        if ($cart) {
            return [
                'cart_id' => $args['input']['cart_id']
            ];
        }

        return [];
    }


    /**
     * Save QR Code
     *
     * @param $cart
     * @param $params
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function saveQrCode($cart, $params)
    {
        if (!empty($cart)) {
            $this->qrCodeHelper->saveQrCodeParams($cart->getId(), $params);
        } else {
            $this->qrCodeHelper->setQrCodeOrderingInSession($params);
        }
    }
}
