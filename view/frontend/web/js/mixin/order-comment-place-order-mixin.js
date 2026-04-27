define([
    'Ls_Hospitality/js/model/checkout/order-comment-validator'
], function (validateOrderComment) {
    'use strict';

    return function (placeOrderAction) {
        return function (paymentData, messageContainer) {

            return validateOrderComment().then(function () {

                return placeOrderAction(paymentData, messageContainer);

            }).fail(function () {

                return $.Deferred().reject().promise();

            });
        };
    };
});
