<?php
declare(strict_types=1);

namespace hospitality\Model\Resolver\Quote;

use \Ls\Hospitality\Helper\QrCodeHelper;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

/**
 * Resolver class responsible for returning qr code params from quote
 */
class QrCodeParams implements ResolverInterface
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
        $cartId         = $args['cart_id'];
        $lsQrCodeParams = null;
        if (isset($cartId)) {
            $maskedCartId  = $args['cart_id'];
            $storeId       = (int)$context->getExtensionAttributes()->getStore()->getId();
            $currentUserId = $context->getUserId();
            $cart          = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
            if (!empty($cart)) {
                $lsQrCodeParams = $this->qrCodeHelper->getQrCode($cart->getId());
            }
        } else {
            $lsQrCodeParams = $this->qrCodeHelper->getQrCodeOrderingInSession();
        }

        return !empty($lsQrCodeParams) ? $this->qrCodeHelper->getFormattedQrCodeParams($lsQrCodeParams) : null;
    }
}
