<?php

namespace Ls\Hospitality\Helper;

use \Ls\Hospitality\Model\LSR;
use Magento\Checkout\Model\Session\Proxy;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\EncoderInterface;

/**
 * Encryption operation helper
 */
class EncryptionHelper extends AbstractHelper
{
    const ENCRYPT = 1;
    const DECRYPT = 2;
    /**
     * @var EncoderInterface
     */
    public $urlEncoder;
    /**
     * @var DecoderInterface
     */
    public $urlDecoder;

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @var Proxy
     */
    public $checkoutSession;

    /**
     * @param EncoderInterface $urlEncoder
     * @param DecoderInterface $urlDecoder
     * @param LSR $lsr
     * @param Proxy $checkoutSession
     * @param Context $context
     */
    public function __construct(
        EncoderInterface $urlEncoder,
        DecoderInterface $urlDecoder,
        LSR $lsr,
        Proxy $checkoutSession,
        Context $context
    ) {
        parent::__construct($context);
        $this->urlEncoder      = $urlEncoder;
        $this->urlDecoder      = $urlDecoder;
        $this->lsr             = $lsr;
        $this->checkoutSession = $checkoutSession;
    }


    /**
     * Encrypt and Decrypt url parameters
     *
     * @param $action
     * @param $string
     * @return bool|string
     * @throws NoSuchEntityException
     */
    public function encryptDecrypt($action, $string)
    {
        $result = false;

        $encryptMethod = $this->lsr->getStoreConfig(LSR::QRCODE_ENCRYPTION_METHOD, $this->lsr->getCurrentStoreId());
        $secretKey     = $this->lsr->getStoreConfig(LSR::QRCODE_SECRET_KEY, $this->lsr->getCurrentStoreId());

        $key = hash('sha256', $secretKey);

        if ($action == self::ENCRYPT) {
            $result = openssl_encrypt($string, $encryptMethod, $key);
        } elseif ($action == self::DECRYPT) {
            $result = openssl_decrypt($string, $encryptMethod, $key);
        }

        return $result;
    }

    /**
     * Decrypt parameters in url
     *
     * @param $encryptedParameterValue
     * @return bool|string
     * @throws NoSuchEntityException
     */
    public function decryptData($paramValue)
    {
        return $this->encryptDecrypt(self::DECRYPT, $paramValue);
    }

    /**
     * Set QR code ordering comment
     *
     * @param $encryptedParameterValue
     */
    public function setQrCodeOrderingComment($comment)
    {
        $this->checkoutSession->setData(LSR::LS_ORDER_COMMENT, $comment);
    }
}
