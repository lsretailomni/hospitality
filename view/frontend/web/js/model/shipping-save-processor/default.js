;define([
    'jquery',
    'mage/utils/wrapper',
    'underscore'
], function ($, wrapper, _) {
    'use strict';

    return function (payloadExtender) {
        return wrapper.wrap(payloadExtender, function (originalFunction, payload) {
            var serviceMode = $('[name="service-mode"]') ? $('[name="service-mode"]').val() : '';

            payload = originalFunction(payload);

            _.extend(payload.addressInformation, {
                extension_attributes: {
                    'service_mode': serviceMode,
                    'pickup_store': $('#pickup-store').val()
                }
            });

            return payload;
        });
    };
});
