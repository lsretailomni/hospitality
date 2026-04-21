define([
    'jquery',
    'mage/translate',
    'jquery/ui'
], function ($, $t) {
    'use strict';

    return function (config, element) {
        var ajaxUrl = config.ajaxUrl,
            autoRefreshEnabled = config.autoRefreshEnabled || false,
            refreshInterval = parseInt(config.refreshInterval, 10) || 30000,
            refreshTimer = null,
            currentOrderId = null,
            $resultContainer = $('#order-status-result'),
            $errorContainer = $('#error');

        function loadOrderStatus(orderId) {
            if (!orderId) {
                return;
            }

            currentOrderId = orderId;

            $.ajax({
                url: ajaxUrl,
                type: 'GET',
                data: {
                    orderId: orderId
                },
                showLoader: true,
                success: function (response) {
                    $resultContainer.html('').hide();
                    $errorContainer.html('').hide();

                    if (response.output) {
                        $resultContainer.html(response.output).show();

                        if (autoRefreshEnabled) {
                            scheduleNextRefresh();
                        }
                    } else if (response.error) {
                        $errorContainer.html(response.error).show();
                        stopAutoRefresh();
                    } else {
                        $errorContainer.html($t('Kitchen service is down. Please try again.')).show();
                        stopAutoRefresh();
                    }
                },
                error: function () {
                    $errorContainer.html($t('Kitchen service is down. Please try again.')).show();
                    stopAutoRefresh();
                }
            });
        }

        function scheduleNextRefresh() {
            if (refreshTimer) {
                clearTimeout(refreshTimer);
            }

            if (autoRefreshEnabled && currentOrderId && refreshInterval > 0) {
                refreshTimer = setTimeout(function () {
                    loadOrderStatus(currentOrderId);
                }, refreshInterval);
            }
        }

        function stopAutoRefresh() {
            if (refreshTimer) {
                clearTimeout(refreshTimer);
                refreshTimer = null;
            }
        }

        $(element).on('submit', function (e) {
            e.preventDefault();

            var orderId = $('#order_id').val();

            if (orderId) {
                stopAutoRefresh();
                loadOrderStatus(orderId);
            }
        });

        $(window).on('beforeunload', function () {
            stopAutoRefresh();
        });
    };
});
