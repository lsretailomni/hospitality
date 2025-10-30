define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return function (config) {
        var ajaxUrl = config.ajaxUrl,
            orderId = config.orderId,
            storeId = config.storeId,
            pickupOrderTime = config.pickupOrderTime,
            autoRefreshEnabled = config.autoRefreshEnabled,
            refreshInterval = parseInt(config.refreshInterval, 10) || 10000,
            refreshTimer = null,
            $container = $('#ls-hosp-order-info');

        function ensureLoaderExists() {
            if ($container.find('.loader').length === 0) {
                $container.prepend(
                    '<div class="loader" style="display: none;">' +
                    '<img src="' + require.toUrl('images/loader-1.gif') + '" alt="Loading..." />' +
                    '<p>' + $t('Refreshing Kitchen Info') + '</p>' +
                    '</div>'
                );
            }
        }

        function showLoader() {
            ensureLoaderExists();
            $container.find('.loader').show();
            $container.find('.hosp-info-container').hide();
        }

        function hideLoader() {
            $container.find('.loader').hide();
            $container.find('.hosp-info-container').show();
        }

        function loadOrderInfo() {
            showLoader();

            $.ajax({
                url: ajaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    orderId: orderId,
                    storeId: storeId,
                    pickupOrderTime: pickupOrderTime
                },
                showLoader: false,
                success: function (response) {
                    if (response.output) {
                        var $response = $(response.output);
                        var $contentContainer = $container.find('.hosp-info-container');

                        if ($contentContainer.length) {
                            var $newContent = $response.find('.hosp-info-container');
                            if ($newContent.length) {
                                $contentContainer.replaceWith($newContent);
                            }
                        } else {
                            $container.html(response.output);
                            ensureLoaderExists();
                        }

                        $container.find('.hosp-info-container').trigger('contentUpdated');
                    }
                },
                error: function () {
                    console.error($t('Failed to load kitchen information'));
                },
                complete: function () {
                    hideLoader();
                    if (autoRefreshEnabled) {
                        scheduleNextRefresh();
                    }
                }
            });
        }

        function scheduleNextRefresh() {
            if (refreshTimer) {
                clearTimeout(refreshTimer);
            }
            refreshTimer = setTimeout(loadOrderInfo, refreshInterval);
        }

        // Initial load
        loadOrderInfo();

        $(window).on('beforeunload', function () {
            if (refreshTimer) {
                clearTimeout(refreshTimer);
            }
        });
    };
});