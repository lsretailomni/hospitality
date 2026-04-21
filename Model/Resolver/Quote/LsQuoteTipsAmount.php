<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Quote;

use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver class responsible for returning ls_tip_amount on quote
 */
class LsQuoteTipsAmount implements ResolverInterface
{
    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        $quote = $value['model'];
        $lsTipAmountInQuote = $quote->getData(LSR::LS_TIP_AMOUNT);

        return !empty($lsTipAmountInQuote) ? $lsTipAmountInQuote : null;
    }
}
