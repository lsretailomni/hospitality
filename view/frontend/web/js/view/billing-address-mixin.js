define(['Magento_Checkout/js/model/quote'], function (quote) {
    'use strict';

    return function (Component) {
        return Component.extend({
            defaults: {
                template:(!window.checkoutConfig.hasOwnProperty("anonymous_order") &&
                    !window.checkoutConfig.anonymous_order.is_enabled) ||
                (!window.checkoutConfig.hasOwnProperty("remove_checkout_step_enabled") &&
                    !window.checkoutConfig.remove_checkout_step_enabled) ? 'Magento_Checkout/billing-address' : 'Ls_Hospitality/checkout/billing-address'
            }
        });
    }
});
