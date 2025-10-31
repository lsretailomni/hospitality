define([
    'jquery',
    'mage/translate',
    'jquery/ui'
], function ($, $t) {
    'use strict';

    return function (config, element) {
        $(element).on('submit', function (e) {
            e.preventDefault();

            var formData = {
                orderId: $('#order_id').val()
            };

            $.ajax({
                url: config.ajaxUrl,
                type: 'GET',
                data: formData,
                showLoader: true,
                success: function (response) {
                    $('#order-status-result').html('').hide();
                    $('#error').html('').hide();
                    if (response.output) {
                        $('#order-status-result').html(response.output).show();
                    } else if (response.error) {
                        $('#error').html(response.error).show();
                    } else {
                        $('#error').html($t('Kitchen service is down. Please try again.')).show();
                    }
                },
                error: function () {
                    $('#error').html($t('Kitchen service is down. Please try again.')).show();
                }
            });
        });
    };
});