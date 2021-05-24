define([
    "jquery",
    "jquery/ui",
    "OwlCarousel"
], function ($) {
    "use strict";
    return function main(config, element)
    {
        var $element = $(element);
        var ajaxUrl = config.ajaxUrl;
        var orderId = config.orderId;
        var storeId = config.storeId;
        $(document).ready(function () {
            setTimeout(function () {
                $.ajax({
                    context: '#ls-hosp-order-info',
                    url: ajaxUrl,
                    type: "POST",
                    data: {orderId: orderId, storeId: storeId}
                }).done(function (data) {
                    $('#ls-hosp-order-info').html(data.output).find('.hosp-info-container').trigger('contentUpdated');
                    return true;
                });
            }, 2000);
        });
    };
});
