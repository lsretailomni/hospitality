<?php

namespace Ls\Hospitality\Helper;

use Exception;
use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Model\ResourceModel\ReplStore\CollectionFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\Url\DecoderInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;

/**
 * QR code operation helper
 */
class QrCodeHelper extends AbstractHelper
{
    /**
     * @var CustomerSession
     */
    public $customerSession;

    /**
     * @var CheckoutSession
     */
    public $checkoutSession;

    /**
     * @var CollectionFactory
     */
    public $storeCollection;

    /**
     * @var LSR
     */
    public $lsr;

    /**
     *
     * @var CartRepositoryInterface
     */
    public $quoteRepository;

    /**
     * @var SerializerJson
     */
    public $serializerJson;

    /**
     * @var DecoderInterface
     */
    public $urlDecoder;

    /**
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param CollectionFactory $storeCollection
     * @param LSR $lsr
     * @param CartRepositoryInterface $quoteRepository
     * @param SerializerJson $serializerJson
     * @param Context $context
     * @param DecoderInterface $urlDecoder
     */
    public function __construct(
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        CollectionFactory $storeCollection,
        LSR $lsr,
        CartRepositoryInterface $quoteRepository,
        SerializerJson $serializerJson,
        Context $context,
        DecoderInterface $urlDecoder
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->lsr             = $lsr;
        $this->storeCollection = $storeCollection;
        $this->quoteRepository = $quoteRepository;
        $this->serializerJson  = $serializerJson;
        $this->urlDecoder      = $urlDecoder;
    }

    /**
     * Decrypt the parameters pass to QR ordering
     *
     * @param string $params
     * @return string
     */
    public function decrypt($params)
    {
        return $this->urlDecoder->decode($params);
    }

    /**
     * Set QR code ordering data
     *
     * @param string $params
     */
    public function setQrCodeOrderingInSession($params)
    {
        $this->setQrCodeInCheckoutSession(null);
        $this->customerSession->setData(LSR::LS_QR_CODE_ORDERING, $params);
        if ($this->isPersistQrCodeEnabled()) {
            $this->setQrCodeInCheckoutSession($params);
        }
    }

    /**
     * Remove QR code ordering data
     */
    public function removeQrCodeOrderingInSession()
    {
        $this->customerSession->setData(LSR::LS_QR_CODE_ORDERING, null);
    }

    /**
     * Get QR code ordering data
     *
     * @return array|mixed
     * @throws NoSuchEntityException
     */
    public function getQrCodeOrderingInSession()
    {
        $qrcodeData = $this->customerSession->getData(LSR::LS_QR_CODE_ORDERING);
        if (empty($qrcodeData) && $this->isPersistQrCodeEnabled()) {
            $qrcodeData = $this->getQrCodeInCheckoutSession();
        }
        return $qrcodeData;
    }

    /**
     * Validate store id
     *
     * @param string $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function validateStoreId($storeId)
    {
        $check      = false;
        $collection = $this->storeCollection
            ->create()
            ->addFieldToFilter('scope_id', $this->lsr->getCurrentWebsiteId())
            ->addFieldToFilter('nav_id', $storeId);
        if ($collection->getSize() > 0) {
            $check = true;
        }

        return $check;
    }

    /**
     * Is Qr Code ordering enabled
     *
     * @return array|string
     * @throws NoSuchEntityException
     */
    public function isQrCodeOrderingEnabled()
    {
        return $this->lsr->isQrCodeOrderingEnabled();
    }

    /**
     * Save QR code in quote
     *
     * @param string $cartId
     * @param string $qrCodeParams
     * @return mixed
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function saveQrCodeParams($cartId, $qrCodeParams)
    {
        $this->setQrCodeInCheckoutSession(null);
        $quote = $this->quoteRepository->getActive($cartId);

        try {
            if ($this->isPersistQrCodeEnabled()) {
                $this->setQrCodeInCheckoutSession($qrCodeParams);
            }
            $qrCodeParams = $this->serializerJson->serialize($qrCodeParams);
            $quote->setData(LSR::LS_QR_CODE_ORDERING, $qrCodeParams);
            $this->quoteRepository->save($quote);
        } catch (GraphQlNoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(
                __('Could not find a cart with ID "%cart_id"', ['cart_id' => $cartId])
            );
        }

        return $qrCodeParams;
    }

    /**
     * Remove QR code in quote
     *
     * @param string $cartId
     * @return void
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function removeQrCodeParams($cartId)
    {
        $quote = $this->quoteRepository->getActive($cartId);
        $quote->setData(LSR::LS_QR_CODE_ORDERING);
        $this->quoteRepository->save($quote);
    }

    /**
     * Get QR Code Params from Quote
     *
     * @param $cartId
     * @param $unserialize
     * @return array|bool|float|int|mixed|string|null
     * @throws Exception
     */
    public function getQrCode($cartId, $unserialize = true)
    {
        $qrCodeParams = null;
        try {
            $quote              = $this->quoteRepository->getActive($cartId);
            $qrCodeOrderingData = $quote->getData(LSR::LS_QR_CODE_ORDERING) ? $this->serializerJson->unserialize($quote->getData(LSR::LS_QR_CODE_ORDERING)) : '';

            if (empty($qrCodeOrderingData)) {
                $qrCodeOrderingData = $this->getQrCodeOrderingInSession();
                if ($qrCodeOrderingData) {
                    $quote->setData(LSR::LS_QR_CODE_ORDERING, $this->serializerJson->serialize($qrCodeOrderingData));
                    $this->quoteRepository->save($quote);
                }
            }

            if ($qrCodeOrderingData) {
                $qrCodeParams = $unserialize ? $qrCodeOrderingData : $quote->getData(LSR::LS_QR_CODE_ORDERING);
            }
        } catch (Exception $e) {
            throw new Exception(__($e->getMessage()));
        }

        return $qrCodeParams;
    }

    /**
     * Get formatted Qr Code Params
     *
     * @param array $qrCodeParams
     * @return array
     */
    public function getFormattedQrCodeParams($qrCodeParams)
    {
        $formattedValues = [];

        foreach ($qrCodeParams ?? [] as $index => $param) {
            $formattedValues[] = ['param_name' => $index, 'param_value' => $param];
        }

        return $formattedValues;
    }

    /**
     * Check if persist qrcode enabled
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function isPersistQrCodeEnabled()
    {
        return $this->lsr->getStoreConfig(LSR::PERSIST_QRCODE_ORDERING, $this->lsr->getCurrentStoreId());
    }

    /**
     * Set Qr Code in checkout session
     *
     * @param string $qrCodeParams
     * @return void
     */
    public function setQrCodeInCheckoutSession($qrCodeParams)
    {
        $this->checkoutSession->setData(LSR::LS_QR_CODE_ORDERING, $qrCodeParams);
    }

    /**
     * Get Qr Code in checkout session
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getQrCodeInCheckoutSession()
    {
        return $this->checkoutSession->getData(LSR::LS_QR_CODE_ORDERING);
    }

    /**
     * Remove Qr Code in checkout session
     *
     * @throws NoSuchEntityException
     */
    public function removeQrCodeInCheckoutSession()
    {
        $this->checkoutSession->setData(LSR::LS_QR_CODE_ORDERING, null);
    }

   /**
    * Get checkout session qr code ordering
    *
    * @return mixed
    */
    public function getCheckoutSessionObject()
    {
        return $this->checkoutSession;
    }
}
