<?php

namespace Ls\Hospitality\Model\Order;

use Exception;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\Ecommerce\Entity\HospAvailabilityResponse;
use \Ls\Omni\Client\Ecommerce\Operation;
use \Ls\Omni\Helper\ItemHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Psr\Log\LoggerInterface;

/**
 * Checking current availability of items, deals and modifiers.
 */
class CheckAvailability
{
    /**
     * @var ProductRepositoryInterface
     */
    public $productRepository;

    /** @var  LSR $lsr */
    public $lsr;

    /**
     * @var ItemHelper
     */
    public $itemHelper;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @param Context $context
     * @param ProductRepositoryInterface $productRepository
     * @param LSR $lsr
     * @param ItemHelper $itemHelper
     * @param HospitalityHelper $hospitalityHelper
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ProductRepositoryInterface $productRepository,
        LSR $lsr,
        ItemHelper $itemHelper,
        HospitalityHelper $hospitalityHelper,
        CheckoutSession $checkoutSession,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->lsr               = $lsr;
        $this->itemHelper        = $itemHelper;
        $this->hospitalityHelper = $hospitalityHelper;
        $this->checkoutSession   = $checkoutSession;
        $this->logger            = $logger;
    }

    /**
     * Api call to check the current availability of items
     *
     * @param string $storeId
     * @param array $availabilityRequestArray
     * @return HospAvailabilityResponse[]|null
     */
    public function availability($storeId, $availabilityRequestArray)
    {
        $response          = null;
        $request           = new Operation\CheckAvailability();
        $availabilityArray = new Entity\ArrayOfHospAvailabilityRequest();
        $availabilityArray->setHospAvailabilityRequest($availabilityRequestArray);
        $availability = new Entity\CheckAvailability();
        $availability->setStoreId($storeId);
        $availability->setRequest($availabilityArray);
        try {
            $response = $request->execute($availability);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
        if (!empty($response) &&
            !empty($response->getCheckAvailabilityResult()) &&
            !empty($response->getCheckAvailabilityResult()->getHospAvailabilityResponse())) {
            return $response->getCheckAvailabilityResult()->getHospAvailabilityResponse();
        }
        return null;
    }

    /**
     * Validate current availability of modifiers and deals
     *
     * @param bool $isItem
     * @param string $qty
     * @param object $item
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ValidatorException
     */
    public function validateQty($isItem = false, $qty = null, $item = null)
    {
        $checkAvailabilityCollection = [];
        $availabilityRequestArray    = [];
        $lineNo                      = null;

        if ($this->lsr->isCheckAvailabilityEnabled()) {
            if ($isItem == true) {
                $items      = [$item];
                $itemsCount = 1;
            } else {
                $itemsCount = $this->checkoutSession->getQuote()->getItemsCount();
                $items      = $this->checkoutSession->getQuote()->getAllVisibleItems();
            }
            if ($itemsCount > 0) {
                foreach ($items as $item) {
                    $itemQty = $item->getQty();
                    list($itemId, , $unitOfMeasure) = $this->itemHelper->getComparisonValues(
                        $item->getSku()
                    );
                    if (in_array($itemId, $checkAvailabilityCollection)) {
                        $itemQty = $checkAvailabilityCollection[$itemId]['qty'] + $itemQty;
                    }
                    $checkAvailabilityCollection[$itemId] = [
                        'item_id' => $itemId,
                        'name'    => $item->getName(),
                        'qty'     => $itemQty
                    ];
                    $this->setModifiersForCheckingAvailability(
                        $item,
                        $availabilityRequestArray,
                        $checkAvailabilityCollection
                    );
                    $availabilityRequest = new Entity\HospAvailabilityRequest();
                    $availabilityRequest->setItemId($itemId);
                    $availabilityRequest->setUnitOfMeasure($unitOfMeasure);
                    $availabilityRequestArray[] = $availabilityRequest;
                }
            }

            $responseResult = $this->availability($this->lsr->getActiveWebStore(), $availabilityRequestArray);
            if ($responseResult) {
                $this->processResponse($checkAvailabilityCollection, $responseResult);
            }
        }
    }

    /**
     * Set modifiers for check availability
     *
     * @param string $item
     * @param string $availabilityRequestArray
     * @param string $checkAvailabilityCollection
     * @return void
     */
    public function setModifiersForCheckingAvailability(
        $item,
        &$availabilityRequestArray,
        &$checkAvailabilityCollection
    ) {
        $options = $this->hospitalityHelper->getCustomOptionsFromQuoteItem($item);
        if ($options) {
            foreach ($options as $option) {
                if (isset($option['ls_modifier_recipe_id'])
                    && $option['ls_modifier_recipe_id'] != LSR::LSR_RECIPE_PREFIX) {
                    $qty            = 1;
                    $modifier       = current($this->hospitalityHelper->getModifierByDescription($option['value']));
                    $modifierItemId = $modifier->getTriggerCode();
                    $code           = $modifier->getCode();
                    $unitOfMeasure  = $modifier->getUnitOfMeasure();

                    if (in_array($modifierItemId, $checkAvailabilityCollection)) {
                        $qty = $checkAvailabilityCollection[$modifierItemId]['qty'] + $qty;
                    }

                    $checkAvailabilityCollection[$modifierItemId] = [
                        'item_id'      => $modifierItemId,
                        'name'         => $modifier->getDescription(),
                        'product_name' => $item->getName(),
                        'qty'          => $qty,
                        'is_modifier'  => true,
                        'code'         => $code
                    ];

                    $availabilityRequest = new Entity\HospAvailabilityRequest();
                    $availabilityRequest->setItemId($modifierItemId);
                    $availabilityRequest->setUnitOfMeasure($unitOfMeasure);
                    $availabilityRequestArray[] = $availabilityRequest;
                }
            }
        }
    }

    /**
     * Process and validate the response get from check availability Api
     *
     * @param array $checkAvailabilityCollection
     * @param array $responseResult
     * @return void
     * @throws ValidatorException
     */
    public function processResponse(
        $checkAvailabilityCollection,
        $responseResult
    ) {
        $message = '';
        foreach ($responseResult as $result) {
            if (in_array($result->getNumber(), array_column($checkAvailabilityCollection, 'item_id'))) {
                $qty       = $checkAvailabilityCollection[$result->getNumber()]['qty'];
                $resultQty = (int)$result->getQuantity();
                if ($qty > $resultQty || $resultQty == 0) {
                    $name = $checkAvailabilityCollection[$result->getNumber()]['name'];

                    if (isset($checkAvailabilityCollection[$result->getNumber()]['is_modifier'])) {
                        $code        = $checkAvailabilityCollection[$result->getNumber()]['code'];
                        $productName = $checkAvailabilityCollection[$result->getNumber()]['product_name'];
                        $message     .= __(
                            '%1 modifier option %2 (%3) has quantity of %4 which is greater then currently available quantity %5. Please select different option for this modifier.',
                            $productName,
                            $code,
                            $name,
                            $qty,
                            $resultQty
                        );
                    } else {
                        $message .= __(
                            'Product %1 has quantity of %2 which is greater then current available quantity %3. Please adjust the product quantity.',
                            $name,
                            $qty,
                            $resultQty
                        );
                    }

                    throw new ValidatorException(__($message));
                }
            }
        }
    }
}
