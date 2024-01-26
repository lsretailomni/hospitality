<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Quote;

use \Ls\Hospitality\Helper\QrCodeHelper;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver class responsible for removing relevant QR params
 */
class RemoveQRCodeParams implements ResolverInterface
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
        if (empty($args['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        $maskedCartId  = $args['input']['cart_id'];
        $storeId       = (int)$context->getExtensionAttributes()->getStore()->getId();
        $currentUserId = $context->getUserId();
        $cart          = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
        $this->removeQrCode($cart);

        if ($cart) {
            return [
                'cart_id' => $args['input']['cart_id']
            ];
        }

        return [];
    }

    /**
     * Remove QR Code
     *
     * @param object $cart
     * @return void
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function removeQrCode($cart)
    {
        if (!empty($cart)) {
            $this->qrCodeHelper->removeQrCodeParams($cart->getId());
        }
        $this->qrCodeHelper->removeQrCodeOrderingInSession();
        $this->qrCodeHelper->removeQrCodeInCheckoutSession();
    }
}
