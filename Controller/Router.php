<?php

namespace Ls\Hospitality\Controller;

use \Ls\Hospitality\Helper\QrCodeHelper;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;

/**
 * Forward to route marching controller
 */
class Router implements RouterInterface
{
    /**
     * @var ActionFactory
     */
    public $actionFactory;

    /**
     * @var QrCodeHelper
     */
    public $qrCodeHelper;

    /**
     * @param ActionFactory $actionFactory
     * @param QrCodeHelper $qrCodeHelper
     */
    public function __construct(
        ActionFactory $actionFactory,
        QrCodeHelper $qrCodeHelper
    ) {
        $this->actionFactory = $actionFactory;
        $this->qrCodeHelper  = $qrCodeHelper;
    }

    /**
     * Matching route and forward to controller with require parameters
     *
     * @param RequestInterface $request
     * @return ActionInterface|void
     */
    public function match(RequestInterface $request)
    {
        $resultArray = [];
        $identifier  = trim($request->getPathInfo(), '/');
        $paramsArray = explode('/', $identifier);
        if (count($paramsArray) == 3) {
            if ($paramsArray[1] &&
                strtolower($paramsArray[1]) == 'qrcode' &&
                $this->qrCodeHelper->isQrCodeOrderingEnabled()
            ) {
                $request->setPathInfo('/hospitality/qrcode/');
                $params           = $this->qrCodeHelper->decrypt($paramsArray[2]);
                $resultParamArray = explode('&', $params);
                foreach ($resultParamArray as $resultParam) {
                    $keyValueArray                   = explode('=', $resultParam);
                    $resultArray [$keyValueArray[0]] = $keyValueArray[1];
                    $request->setParams($resultArray);
                }

                return $this->actionFactory->create(Forward::class, ['request' => $request]);
            }
        }
    }
}
