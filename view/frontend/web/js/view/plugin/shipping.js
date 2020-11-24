define([
    'Magento_Checkout/js/model/quote',
    'jquery',
    'mage/translate'
], function (quote, $, $t) {
    'use strict';

    return function (Component) {
        return Component.extend({
            validateShippingInformation: function () {
                if (quote.shippingMethod().carrier_code == 'clickandcollect') {
                    var isEnabled = window.checkoutConfig.shipping.service_mode.enabled;
                    if (isEnabled && $("[name='service-mode']").val() === '') {
                        this.errorValidationMessage($t('Please select service mode for order such as dine-in or takeaway.'));
                        return false;
                    }
                }
                return this._super();
            }
        });
    }
});
