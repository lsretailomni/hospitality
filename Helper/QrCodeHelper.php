<?php

namespace Ls\Hospitality\Helper;

use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Model\ResourceModel\ReplStore\CollectionFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Customer\Model\Session\Proxy;
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
     * @var Proxy
     */
    public $customerSession;

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
     * @param Proxy $customerSession
     * @param CollectionFactory $storeCollection
     * @param LSR $lsr
     * @param CartRepositoryInterface $quoteRepository
     * @param SerializerJson $serializerJson
     * @param Context $context
     */
    public function __construct(
        Proxy $customerSession,
        CollectionFactory $storeCollection,
        LSR $lsr,
        CartRepositoryInterface $quoteRepository,
        SerializerJson $serializerJson,
        Context $context
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->lsr             = $lsr;
        $this->storeCollection = $storeCollection;
        $this->quoteRepository = $quoteRepository;
        $this->serializerJson  = $serializerJson;
    }

    /**
     * Decrypt the parameters pass to QR ordering
     *
     * @param $params
     * @return false|string
     */
    public function decrypt($params)
    {
        return base64_decode($params);
    }

    /**
     * Set QR code ordering data
     *
     * @param $params
     */
    public function setQrCodeOrderingInSession($params)
    {
        $this->customerSession->setData(LSR::LS_QR_CODE_ORDERING, $params);
    }

    /**
     * Get QR code ordering data
     */
    public function getQrCodeOrderingInSession()
    {
        return $this->customerSession->getData(LSR::LS_QR_CODE_ORDERING);
    }

    /**
     * validate store id
     *
     * @param $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function validateStoreId($storeId)
    {
        $check      = false;
        $collection = $this->storeCollection
            ->create()
            ->addFieldToFilter('scope_id', $this->lsr->getCurrentStoreId())
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
     * @param $cartId
     * @param $qrCodeParams
     * @return mixed
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function saveQrCodeParams($cartId, $qrCodeParams)
    {
        $quote = $this->quoteRepository->getActive($cartId);

        try {
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
     * Get QR Code Params from Quote
     *
     * @return array
     * @throws \Exception
     */
    public function getQrCode($cartId)
    {
        $qrCodeParams = null;
        try {
            $quote        = $this->quoteRepository->getActive($cartId);
            $qrCodeParams = $this->serializerJson->unserialize($quote->getData(LSR::LS_QR_CODE_ORDERING));
        } catch (\Exception $e) {
            throw new \Exception(__($e->getMessage()));
        }

        return $qrCodeParams;
    }

    /**
     * Get formatted Qr Code Params
     *
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
}
