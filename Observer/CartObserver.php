<?php

namespace Ls\Hospitality\Observer;

use Exception;
use \Ls\Core\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity\OneList;
use Magento\Framework\Event\Observer;

/**
 * CartObserver Observer
 */
class CartObserver extends \Ls\Omni\Observer\CartObserver
{
    public function execute(Observer $observer)
    {
        /*
          * Adding condition to only process if LSR is enabled.
          */
        if ($this->lsr->isLSR($this->lsr->getCurrentStoreId())) {
            try {
                if ($this->lsr->getCurrentIndustry() != LSR::LS_INDUSTRY_VALUE_HOSPITALITY) {
                    return parent::execute($observer);
                }
                $quote      = $this->checkoutSession->getQuote();
                $couponCode = $this->basketHelper->getCouponCodeFromCheckoutSession();
                // This will create one list if not created and will return onelist if its already created.
                /** @var OneList|null $oneList */
                $oneList = $this->basketHelper->get();
                // add items from the quote to the oneList and return the updated onelist
                $oneList = $this->basketHelper->setOneListQuote($quote, $oneList);
                if (!empty($couponCode)) {
                    $status = $this->basketHelper->setCouponCode($couponCode);
                    if (!is_object($status)) {
                        $this->basketHelper->setCouponCodeInCheckoutSession('');
                    }
                }
                if (count($quote->getAllItems()) == 0) {
                    $quote->setLsGiftCardAmountUsed(0);
                    $quote->setLsGiftCardNo(null);
                    $quote->setLsPointsSpent(0);
                    $quote->setLsPointsEarn(0);
                    $quote->setGrandTotal(0);
                    $quote->setBaseGrandTotal(0);
                    $this->basketHelper->quoteRepository->save($quote);
                }
                /**
                 * Entity\OrderHosp $basketData
                 */
                $basketData = $this->basketHelper->update($oneList);
                $this->itemHelper->setDiscountedPricesForItems($quote, $basketData);
                if ($this->checkoutSession->getQuote()->getLsGiftCardAmountUsed() > 0 ||
                    $this->checkoutSession->getQuote()->getLsPointsSpent() > 0) {
                    $this->data->orderBalanceCheck(
                        $this->checkoutSession->getQuote()->getLsGiftCardNo(),
                        $this->checkoutSession->getQuote()->getLsGiftCardAmountUsed(),
                        $this->checkoutSession->getQuote()->getLsPointsSpent(),
                        $basketData
                    );
                }
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
        return $this;
    }
}
