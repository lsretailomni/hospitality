;define([
    'jquery',
    'mage/utils/wrapper',
    'underscore'
], function ($, wrapper, _) {
    'use strict';

    return function (payloadExtender) {
        return wrapper.wrap(payloadExtender, function (originalFunction, payload) {
            payload = originalFunction(payload);

            if (!window.checkoutConfig.ls_enabled) {
                return payload;
            }

            let serviceMode = $('[name="service-mode"]') ? $('[name="service-mode"]').val() : '',
                pickupDate = $('[name="pickup-date"]') ? $('[name="pickup-date"]').val() : '',
                pickupTimeslot = $('[name="pickup-timeslot"]') ? $('[name="pickup-timeslot"]').val() : '';

            _.extend(payload.addressInformation, {
                extension_attributes: {
                    'service_mode': serviceMode,
                    'pickup_date': pickupDate,
                    'pickup_timeslot': pickupTimeslot,
                    'pickup_store': $('#pickup-store').val()
                }
            });

            return payload;
        });
    };
});
