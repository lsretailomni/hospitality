define(
    [
        'jquery',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/url',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Ui/js/model/messageList',
        'mage/translate'
    ],
    function ($, customer, quote, urlBuilder, urlFormatter, errorProcessor, messageContainer, __) {
        'use strict';

        return {
            validate: function () {
                var isCustomer = customer.isLoggedIn();
                var form = this.getForm();

                var comment = form.find('.input-text.order-comment').val();
                if (comment) {
                    if (this.hasMaxLength() && comment.length > this.getMaxLength()) {
                        messageContainer.addErrorMessage({message: __("Comment is too long")});
                        return false;
                    }
                }

                var quoteId = quote.getQuoteId();

                var url;
                if (isCustomer) {
                    url = urlBuilder.createUrl('/carts/mine/set-order-comment', {})
                } else {
                    url = urlBuilder.createUrl('/guest-carts/:cartId/set-order-comment', {cartId: quoteId});
                }

                var payload = {
                    cartId: quoteId,
                    orderComment: {
                        comment: comment
                    }
                };

                if (!payload.orderComment.comment) {
                    return true;
                }

                var result = true;

                $.ajax({
                    url: urlFormatter.build(url),
                    data: JSON.stringify(payload),
                    global: false,
                    contentType: 'application/json',
                    type: 'PUT',
                    async: false
                }).done(
                    function (response) {
                        result = true;
                    }
                ).fail(
                    function (response) {
                        result = false;
                        errorProcessor.process(response);
                    }
                );

                return result;
            },
            getForm: function () {
                var form = $('.payment-method input[name="payment[method]"]:checked')
                    .parents('.payment-method')
                    .find('form.order-comment-form')
                if (!form.length) {
                    form = $('form.order-comment-form');
                }

                return form;
            },
            hasMaxLength: function () {
                return window.checkoutConfig.max_length > 0;
            },
            getMaxLength: function () {
                return window.checkoutConfig.max_length;
            }
        };
    }
);
