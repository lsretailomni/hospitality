<?php

namespace Ls\Hospitality\Plugin\Controller\WebApi;

use Magento\Webapi\Controller\Rest;

/**
 * Plugin for rest webapi plugin
 */
class RestPlugin
{

    /**
     * After plugin to intercept dispatch method for rest webapi
     *
     * @param Rest $subject
     * @param $result
     * @return array|mixed
     */
    public function afterDispatch(Rest $subject, $result)
    {
        $result->setHeader('errorRedirectAction', '');

        return $result;
    }
}
