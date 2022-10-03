define([
    'Magento_Checkout/js/model/quote',
    'jquery',
    'mage/translate'
], function (quote, $, $t) {
    'use strict';

    return function (Component) {
        return Component.extend({
            validateShippingInformation: function () {
                if (!window.checkoutConfig.ls_enabled) {
                    return this._super();
                }

                if (quote.shippingMethod().carrier_code == 'clickandcollect') {
                    let isEnabled = window.checkoutConfig.shipping.service_mode.enabled;
                    let stores = $.parseJSON(window.checkoutConfig.shipping.select_store.stores);
                    if (stores.totalRecords > 0 && $('#pickup-store').val() == '') {
                        this.errorValidationMessage($t('Please provide where (if suitable) you prefer to pick your order.'));
                        return false;
                    }
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
