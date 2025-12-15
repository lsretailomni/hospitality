define([
    'jquery',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'mage/url',
    'mage/storage',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Ui/js/model/messageList',
    'mage/translate'
], function ($, customer, quote, urlBuilder, urlFormatter, storage, errorProcessor, messageList, $t) {
    'use strict';

    return function validateOrderComment() {

        var deferred = $.Deferred();

        var form = $('.payment-method input[name="payment[method]"]:checked')
            .parents('.payment-method')
            .find('form.order-comment-form');

        if (!form.length) {
            form = $('form.order-comment-form');
        }

        var comment = form.find('.input-text.order-comment').val();
        var isCustomer = customer.isLoggedIn();
        var quoteId = quote.getQuoteId();

        // No comment → OK
        if (!comment) {
            deferred.resolve();
            return deferred.promise();
        }

        // Length validation
        if (window.checkoutConfig.max_length > 0 &&
            comment.length > window.checkoutConfig.max_length) {

            messageList.addErrorMessage({ message: $t('Comment is too long') });
            deferred.reject();
            return deferred.promise();
        }

        // Build API URL
        var url = isCustomer
            ? urlBuilder.createUrl('/carts/mine/set-order-comment', {})
            : urlBuilder.createUrl('/guest-carts/:cartId/set-order-comment', { cartId: quoteId });

        // Payload
        var payload = {
            cartId: quoteId,
            orderComment: { comment: comment }
        };

        // Async call
        $.ajax({
            url: urlFormatter.build(url),
            type: 'PUT',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            global: false
        })
            .done(function () {
                deferred.resolve();
            })
            .fail(function (response) {
                errorProcessor.process(response);
                deferred.reject();
            });

        return deferred.promise();
    };
});
