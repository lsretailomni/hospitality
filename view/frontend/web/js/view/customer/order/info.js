define([
    "jquery",
], function ($) {
    "use strict";
    return function main(config) {
        var ajaxUrl = config.ajaxUrl,
            orderId = config.orderId,
            storeId = config.storeId,
            pickupOrderTime = config.pickupOrderTime;
        $(document).ready(function () {
            $.ajax({
                context: '#ls-hosp-order-info',
                url: ajaxUrl,
                type: "GET",
                data: {orderId: orderId, storeId: storeId, pickupOrderTime: pickupOrderTime}
            }).done(function (data) {
                $('#ls-hosp-order-info').html(data.output).find('.hosp-info-container').trigger('contentUpdated');
                return true;
            });
        });
    };
});
