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
                let isEnabledDeliveryTimeSlots = window.checkoutConfig.shipping.pickup_date_timeslots.delivery_hours_enabled,
                    isEnabledTakeawayTimeSlots = window.checkoutConfig.shipping.pickup_date_timeslots.enabled,
                    storeType = window.checkoutConfig.shipping.pickup_date_timeslots.store_type;

                if (quote.shippingMethod().carrier_code === 'clickandcollect') {
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

                    if (isEnabledTakeawayTimeSlots === "1" &&
                        storeType === 1
                    ) {
                        if (($("[name='pickup-date']").val() === '')) {
                            this.errorValidationMessage($t('Please select pick up date for your order.'));
                            return false;
                        }

                        if (($("[name='pickup-timeslot']").val() === '')) {
                            this.errorValidationMessage($t('Please select pick up time for your order.'));
                            return false;
                        }
                    }
                } else {
                    if (isEnabledDeliveryTimeSlots === "1" &&
                        storeType === 1
                    ) {
                        if (($("[name='pickup-date']").val() === '')) {
                            this.errorValidationMessage($t('Please select delivery date for your order.'));
                            return false;
                        }

                        if (($("[name='pickup-timeslot']").val() === '')) {
                            this.errorValidationMessage($t('Please select delivery time for your order.'));
                            return false;
                        }
                    }
                }
                return this._super();
            }
        });
    }
});
