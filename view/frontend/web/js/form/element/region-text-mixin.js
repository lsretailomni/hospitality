define(['jquery'], function ($) {
    'use strict';

    return function (Component) {
        return Component.extend({
            /**
             * @inheritdoc
             */
            initObservable: function () {
                this._super();

                if (this.dataScope === 'shippingAddress.region') {
                    this.checkAndHideRegion();
                }

                return this;
            },

            /**
             * Check and hide region text field if not required
             */
            checkAndHideRegion: function () {
                if (window.hasOwnProperty("checkoutConfig") &&
                    window.checkoutConfig.hasOwnProperty("anonymous_order") &&
                    window.checkoutConfig.anonymous_order.is_enabled &&
                    !window.checkoutConfig.anonymous_order.required_fields.hasOwnProperty("region") &&
                    !window.checkoutConfig.anonymous_order.required_fields.hasOwnProperty("region_id")
                ) {
                    this.visible(false);
                    this.required(false);
                    this.validation = {};
                    if (this.additionalClasses) {
                        this.additionalClasses += ' ls-field-hide';
                    } else {
                        this.additionalClasses = ' ls-field-hide';
                    }
                }
            }
        });
    };
});
