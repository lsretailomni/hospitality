define(['jquery'], function ($) {
    'use strict';

    return function (Component) {
        return Component.extend({
            /**
             * Callback on changing email property
             */
            emailHasChanged: function () {
                if (window.hasOwnProperty("checkoutConfig") &&
                    window.checkoutConfig.anonymous_order.is_enabled &&
                    !window.checkoutConfig.anonymous_order.required_fields.hasOwnProperty("email")
                ) {
                    var loginFormSelector = $('form[data-role=email-with-possible-login]');
                    loginFormSelector.hide();
                }
                return this._super();
            }
        });
    }
});
