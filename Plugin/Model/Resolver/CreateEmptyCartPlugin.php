<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Model\Resolver;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

class CreateEmptyCartPlugin
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var LSR
     */
    private $hospitalityLsr;

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
        LSR $hospitalityLsr,
        HospitalityHelper $hospitalityHelper,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->getCartForUser    = $getCartForUser;
        $this->hospitalityLsr    = $hospitalityLsr;
        $this->hospitalityHelper = $hospitalityHelper;
        $this->quoteRepository   = $quoteRepository;
    }

    /**
     * After plugin to update shipping and billing address for anonymous orders quotes
     *
     * @param mixed $subject
     * @param mixed $result
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @return mixed
     * @throws CouldNotSaveException
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     * @throws NoSuchEntityException
     */
    public function afterResolve(
        $subject,
        $result,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null
    ) {
        $maskedQuoteId = $result;
        $currentUserId = $context->getUserId();
        $storeId       = (int)$context->getExtensionAttributes()->getStore()->getId();

        $anonymousOrderEnabled = $this->hospitalityLsr->getStoreConfig(Lsr::ANONYMOUS_ORDER_ENABLED, $storeId);

        if ($anonymousOrderEnabled && $maskedQuoteId) {
            $quote                            = $this->getCartForUser->execute(
                $maskedQuoteId,
                $currentUserId,
                $storeId
            );
            $anonymousOrderRequiredAttributes = $this->hospitalityHelper->getformattedAddressAttributesConfig($storeId);
            $prefillAttributes                = $this->hospitalityHelper->getAnonymousOrderPrefillAttributes(
                $anonymousOrderRequiredAttributes
            );

            if (!empty($prefillAttributes)) {
                $anonymousAddress = $this->hospitalityHelper->getAnonymousAddress($prefillAttributes);
                $quote->setShippingAddress($anonymousAddress);
                $quote->setBillingAddress($anonymousAddress);
            }

            if (((isset($anonymousOrderRequiredAttributes['email']) &&
                    $anonymousOrderRequiredAttributes['email'] == '0')) ||
                !isset($anonymousOrderRequiredAttributes['email'])
            ) {
                $quote->setCustomerEmail($this->hospitalityHelper->getAnonymousOrderCustomerEmail());
            }

            try {
                $quote->getShippingAddress()->setCollectShippingRates(true);
                $this->quoteRepository->save($quote);
            } catch (\Exception $e) {
                throw new CouldNotSaveException(__("Anonymous order address can't be prefilled."));
            }
        }

        return $result;
    }
}
