<?php

namespace Ls\Hospitality\Plugin\Model\Resolver;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\Lsr;
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
     * @var Lsr
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
     * @param Lsr $hospitalityLsr
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
            $anonymousOrderEmailAddress = $this->hospitalityLsr->getStoreConfig(
                Lsr::ANONYMOUS_ORDER_EMAIL_ADDRESS_ENABLED,
                $storeId
            );
            $quote = $this->getCartForUser->execute($maskedQuoteId, $currentUserId, $storeId);
            $prefillAttributes = $this->hospitalityHelper->getAnonymousOrderPrefillAttributes($storeId);

            if (!empty($prefillAttributes)) {
                $anonymousAddress = $this->hospitalityHelper->getAnonymousAddress($prefillAttributes);
                $quote->setShippingAddress($anonymousAddress);
                $quote->setBillingAddress($anonymousAddress);
            }

            if (!$anonymousOrderEmailAddress) {
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
