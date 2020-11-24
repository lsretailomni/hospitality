;define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote'

], function (Component, ko, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Ls_Hospitality/checkout/shipping/service-mode-shipping-option'
        },

        initObservable: function () {
            var self = this._super();

            this.showAdditionalOption = ko.computed(function () {
                var method = quote.shippingMethod();

                if (method && method['carrier_code'] !== undefined) {
                    if (method['carrier_code'] === 'clickandcollect') {
                        return true;
                    }
                }

                return false;

            }, this);

            return this;
        },
        isDisplay: function () {
            return window.checkoutConfig.shipping.service_mode.enabled === "1";
        }
    });
});
