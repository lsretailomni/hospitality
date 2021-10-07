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
                    let isEnabled = window.checkoutConfig.shipping.service_mode.enabled;
                    let isEnabledTimeSlots = window.checkoutConfig.shipping.pickup_date_timeslots.enabled;
                    if (isEnabled && $("[name='service-mode']").val() === '') {
                        this.errorValidationMessage($t('Please select service mode for order such as dine-in or takeaway.'));
                        return false;
                    }
                    if (isEnabledTimeSlots && ($("[name='pickup-date']").val() === '' || $("[name='pickup-timeslot']").val() === '')) {
                        this.errorValidationMessage($t('Please select date and time slot for your order.'));
                        return false;
                    }
                }
                return this._super();
            }
        });
    }
});
