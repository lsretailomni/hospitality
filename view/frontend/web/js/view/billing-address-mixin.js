define([], function () {
    'use strict';

    return function (Component) {
        return Component.extend({
            defaults: {
                template: (!window.checkoutConfig.hasOwnProperty("anonymous_order") || !window.checkoutConfig.anonymous_order.is_enabled) ? 'Magento_Checkout/billing-address' : 'Ls_Hospitality/checkout/billing-address'
            }
        });
    }
});
