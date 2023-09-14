define([], function () {
    'use strict';

    return function (Component) {
        return Component.extend({
            defaults: {
                template: (!window.checkoutConfig.hasOwnProperty("anonymous_order") || !window.checkoutConfig.anonymous_order.is_enabled) ? 'Magento_Checkout/form/element/email' : 'Ls_Hospitality/checkout/view/form/element/email',
            },

            showLoginForm: function () {
                return !(window.hasOwnProperty("checkoutConfig") &&
                    window.checkoutConfig.hasOwnProperty("anonymous_order") &&
                    window.checkoutConfig.anonymous_order.is_enabled &&
                    !window.checkoutConfig.anonymous_order.required_fields.hasOwnProperty("email"));
            },

            isEmailAutoFilled: function () {
                return window.hasOwnProperty("checkoutConfig") &&
                window.checkoutConfig.hasOwnProperty("anonymous_order") &&
                window.checkoutConfig.anonymous_order.is_enabled &&
                window.checkoutConfig.anonymous_order.required_fields.hasOwnProperty("email") &&
                window.checkoutConfig.anonymous_order.required_fields.email === "0";
            }
        });
    }
});
