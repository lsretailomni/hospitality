<?php

namespace Ls\Hospitality\Plugin\Api;

use \Ls\Core\Model\LSR;
use \Ls\Omni\Exception\InvalidEnumException;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Omni\Helper\BasketHelper;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Interceptor to intercept ShippingInformationManagementInterface methods
 */
class ShippingInformationManagementInterfacePlugin
{    

    /**
     * @var array
     */
    private $previousShippingMethod = [];

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param BasketHelper $basketHelper
     * @param LoggerInterface $logger
     * @param RequestInterface $request
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private HospitalityHelper $hospitalityHelper,
        private BasketHelper $basketHelper,
        private LoggerInterface $logger,
        private RequestInterface $request,
        private CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * After plugin to check basket calculation went through successfully with Central
     *
     * @param $subject
     * @param $result
     * @param $cartId
     * @param ShippingInformationInterface $addressInformation
     * @return mixed
     * @throws InvalidEnumException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws InputException
     * @throws GraphQlInputException
     */
    public function afterSaveAddressInformation(
        $subject,
        $result,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        if (!is_numeric($cartId)) {
            return $result;
        }
        
        // Clear tip amounts if shipping method has changed
        $this->clearTipsIfShippingMethodChanged($cartId, $addressInformation);
        
        if ($this->hospitalityHelper->getLSR()->isHospitalityStore()) {
            $basketData = $this->basketHelper->getOneListCalculationFromCheckoutSession();
            if (!$this->hospitalityHelper->verifyBasketSync($basketData)) {
                $errMsg = $this->hospitalityHelper->getLSR()->getStoreConfig(LSR::LS_ERROR_MESSAGE_ON_BASKET_FAIL);
                $this->logger->critical($errMsg);
                $isGraphQl = str_contains($this->request->getOriginalPathInfo(), "graphql");
                if ($isGraphQl) {
                    throw new GraphQlInputException(__($errMsg));
                }
                throw new InputException(__($errMsg));
            }
        }

        return $result;
    }

    /**
     * Before plugin to capture the current shipping method before it changes
     *
     * @param $subject
     * @param $cartId
     * @param ShippingInformationInterface $addressInformation
     * @return null
     */
    public function beforeSaveAddressInformation(
        $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        if (!is_numeric($cartId)) {
            return null;
        }

        try {
            $quote = $this->cartRepository->getActive($cartId);
            $shippingAddress = $quote->getShippingAddress();
            
            if ($shippingAddress && $shippingAddress->getShippingMethod()) {
                $this->previousShippingMethod[$cartId] = $shippingAddress->getShippingMethod();
            }
        } catch (\Exception $e) {
            $this->logger->error('Error capturing previous shipping method: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Clear tip amounts from quote if shipping method has changed
     *
     * @param int|string $cartId
     * @param ShippingInformationInterface $addressInformation
     * @return void
     */
    public function clearTipsIfShippingMethodChanged(
        $cartId,
        ShippingInformationInterface $addressInformation
    )  {
        try {
            $quote = $this->cartRepository->getActive($cartId);
            $newShippingMethod = $addressInformation->getShippingMethodCode()."_".$addressInformation->getShippingCarrierCode();
            $previousMethod = $this->previousShippingMethod[$cartId] ?? null;

            // Clear tips amount if shipping method changed
            if ($previousMethod && $newShippingMethod && $previousMethod !== $newShippingMethod) {
                $quote->setData('ls_tip_amount', 0);
                $quote->setData('ls_tip_amount_label', "");
                
                $this->cartRepository->save($quote);
                
                $this->logger->info(
                    sprintf(
                        'Tips cleared for cart %s: shipping method changed from %s to %s',
                        $cartId,
                        $previousMethod,
                        $newShippingMethod
                    )
                );
            }
            unset($this->previousShippingMethod[$cartId]);
        } catch (\Exception $e) {
            $this->logger->error('Error clearing tips on shipping method change: ' . $e->getMessage());
        }
    }
}
