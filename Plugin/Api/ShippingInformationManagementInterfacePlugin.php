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
use Psr\Log\LoggerInterface;

/**
 * Interceptor to intercept ShippingInformationManagementInterface methods
 */
class ShippingInformationManagementInterfacePlugin
{
    /**
     * @var HospitalityHelper
     */
    private $hospitalityHelper;

    /**
     * @var BasketHelper
     */
    private $basketHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param BasketHelper $basketHelper
     * @param LoggerInterface $logger
     * @param RequestInterface $request
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        BasketHelper $basketHelper,
        LoggerInterface $logger,
        RequestInterface $request
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
        $this->basketHelper      = $basketHelper;
        $this->logger            = $logger;
        $this->request           = $request;
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
}
