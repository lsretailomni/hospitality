;define([
    'jquery',
    'ko',
    'Magento_Ui/js/form/element/select',
    'Magento_Checkout/js/model/quote',
    'mage/translate',
], function ($, ko, select, quote, $t) {
    'use strict';

    var self;

    return select.extend({

        initialize: function () {
            self = this;
            this._super();
            this.selectedShippingMethod = quote.shippingMethod();

            quote.shippingMethod.subscribe(function () {

                var method = quote.shippingMethod();

                if (method && method['carrier_code'] !== undefined) {
                    if (!self.selectedShippingMethod || (self.selectedShippingMethod && self.selectedShippingMethod['carrier_code'] != method['carrier_code'])) {
                        self.selectedShippingMethod = method;
                        self.updateDropdownValues([{'value':'','label':$t('Please Select')}]);
                        self.updateDropdownValues(self.getServiceModeOptions());
                    }
                }

            }, null, 'change');
        },

        updateDropdownValues: function (values) {
            this.setOptions(values);
        },
        getServiceModeOptions: function () {
            return _.map(window.checkoutConfig.shipping.service_mode.options, function (value, key) {
                return {
                    'value': key,
                    'label': value
                }
            });
        }
    });
});

