define([
    'jquery',
    'underscore',
    'Ls_Omni/js/model/shipping-save-processor/payload-extender'
], function ($, _, parentPayloadExtender) {
    'use strict';

    return function (payload) {
        parentPayloadExtender(payload);
        let serviceMode = $('[name="service-mode"]') ? $('[name="service-mode"]').val() : '';
        _.extend(payload.addressInformation, {
            extension_attributes: _.extend(payload.addressInformation.extension_attributes ,{
                'service_mode': serviceMode,
            })
        });
    };
});
