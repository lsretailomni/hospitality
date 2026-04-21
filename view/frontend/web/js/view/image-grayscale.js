define([
    'jquery',
    'domReady!'
], function($) {
    'use strict';

    function applyGrayscaleToUnavailable() {
        // Handle product listing pages
        $('.item.product.product-item').each(function() {
            var $productItem = $(this);

            if ($productItem.find('.stock.unavailable').length > 0) {
                $productItem.addClass('product-unavailable');
            }
        });

        if ($('.product-info-main .stock.unavailable').length > 0) {
            $('.product.media').addClass('product-unavailable');

            var checkFotorama = setInterval(function() {
                if ($('.fotorama__stage__frame').length > 0) {
                    $('.fotorama__stage__frame').addClass('product-unavailable');
                    clearInterval(checkFotorama);
                }
            }, 200);
        }
    }

    $(document).ready(function() {
        applyGrayscaleToUnavailable();

        $(document).on('contentUpdated', function() {
            applyGrayscaleToUnavailable();
        });
    });

    return {};
});
