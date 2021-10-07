define([
    'jquery',
    'ko',
    'uiRegistry',
    'Magento_Ui/js/form/element/select',
    'Magento_Checkout/js/model/quote',
    'mage/translate',
], function ($, ko, uiRegistry, select, quote, $t) {
    'use strict';
    var self;
    return select.extend({
        initialize: function () {
            self = this;
            this._super();
            this.selectedShippingMethod = quote.shippingMethod();
            quote.shippingMethod.subscribe(function () {
                let method = quote.shippingMethod();
                if (method && method['carrier_code'] !== undefined) {
                    if (!self.selectedShippingMethod || (self.selectedShippingMethod && self.selectedShippingMethod['carrier_code'] != method['carrier_code'])) {
                        self.selectedShippingMethod = method;
                        self.updateDropdownValues([{'value': '', 'label': $t('Please select date')}]);
                        self.updateDropdownValues(self.getDateValues());
                    }
                }
            }, null, 'change');
        },
        updateDropdownValues: function (values) {
            this.setOptions(values);
        },
        getDateValues: function () {
            return _.map(window.checkoutConfig.shipping.pickup_date_timeslots.options, function (value, key) {
                return {
                    'value': key,
                    'label': key,
                }
            });
        },
        onUpdate: function (value) {
            var pickupTimSlot = $("[name='pickup-timeslot']");
            var values = window.checkoutConfig.shipping.pickup_date_timeslots.options;
            pickupTimSlot.html('');
            $.each(values, function (index, val) {
                console.log("index:"+index);
                console.log("value");
                if(index == value) {
                    console.log("inside");
                    $.each(val, function (index, v) {
                        pickupTimSlot.append(new Option(v, v));
                    });
                }
            });
        },
    });
});
