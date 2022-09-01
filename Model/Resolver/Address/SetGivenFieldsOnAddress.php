<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Address;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

/**
 * Resolver class responsible for setting given fields on shipping & billing address of cart
 */
class SetGivenFieldsOnAddress implements ResolverInterface
{
    /**
     * @var Lsr
     */
    private $hospitalityLsr;

    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @param GetCartForUser $getCartForUser
     * @param LSR $hospitalityLsr
     * @param HospitalityHelper $hospitalityHelper
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        Lsr $hospitalityLsr,
        HospitalityHelper $hospitalityHelper,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->getCartForUser    = $getCartForUser;
        $this->hospitalityLsr    = $hospitalityLsr;
        $this->hospitalityHelper = $hospitalityHelper;
        $this->quoteRepository   = $quoteRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        if (empty($args['input']['address_fields'])) {
            throw new GraphQlInputException(__('Required parameter "address_fields" is missing'));
        }

        $maskedCartId          = $args['input']['cart_id'];
        $addressFields         = $args['input']['address_fields'];
        $currentUserId         = $context->getUserId();
        $storeId               = (int)$context->getExtensionAttributes()->getStore()->getId();
        $anonymousOrderEnabled = $this->hospitalityLsr->getStoreConfig(Lsr::ANONYMOUS_ORDER_ENABLED, $storeId);
        $cart                  = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);

        if ($anonymousOrderEnabled) {
            $anonymousAddressShipping = $cart->getShippingAddress();
            $anonymousAddressBilling  = $cart->getBillingAddress();
            $addressAttributes        = $this->hospitalityHelper->getAllAddressAttributesCodes();

            foreach ($addressFields as $addressField) {
                if (in_array($addressField['field_name'], $addressAttributes)
                    && $addressField['field_value']
                ) {
                    $anonymousAddressShipping->setData($addressField['field_name'], $addressField['field_value']);
                    $anonymousAddressBilling->setData($addressField['field_name'], $addressField['field_value']);
                }
            }

            try {
                $cart->getShippingAddress()->setCollectShippingRates(true);
                $this->quoteRepository->save($cart);
            } catch (\Exception $e) {
                throw new CouldNotSaveException(__("Could not set given fields on address"));
            }
        }

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }
}
