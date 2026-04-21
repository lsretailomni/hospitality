<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Quote;

use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver class responsible for saving tips to quote
 */
class SaveTipsToQuote implements ResolverInterface
{
    /**
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        public GetCartForUser $getCartForUser,
        public HospitalityHelper $hospitalityHelper
    )
    {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!isset($args['input']['tips_amount'])) {
            throw new GraphQlInputException(__('Required parameter "tips_amount" is missing'));
        }

        $tipAmount = $args['input']['tips_amount'];
        $cart     = null;
        if (isset($args['input']['cart_id'])) {
            $maskedCartId  = $args['input']['cart_id'];
            $storeId       = (int)$context->getExtensionAttributes()->getStore()->getId();
            $currentUserId = $context->getUserId();
            $cart          = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
        }       

        if (!empty($cart)) {
            $quote = $this->hospitalityHelper->saveTipsToQuote($cart, $tipAmount);

            if ($quote) {
                $grandTotal = (float)$quote->getGrandTotal();
                $baseGrandTotal = (float)$quote->getBaseGrandTotal();
                $savedTip = $quote->getData('ls_tip_amount');
                $savedBaseTip = $quote->getData('base_ls_tip_amount');

                return [
                    'success' => true,
                    'message' => __('Tip amount saved successfully.'),
                    'tip' => $tipAmount,
                    'saved_tip' => $savedTip,
                    'saved_base_tip' => $savedBaseTip,
                    'grand_total' => $grandTotal,
                    'base_grand_total' => $baseGrandTotal
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Could not save tip. Please try again later.'),
                ];
            }
        }
        
        return [];
        
    }    
}
