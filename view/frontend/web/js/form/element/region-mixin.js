define(['jquery'], function ($) {
    'use strict';

    return function (Component) {
        return Component.extend({
            hideRegion: function (option) {
                if (window.hasOwnProperty("checkoutConfig") &&
                    window.checkoutConfig.hasOwnProperty("anonymous_order") &&
                    window.checkoutConfig.anonymous_order.is_enabled &&
                    !window.checkoutConfig.anonymous_order.required_fields.hasOwnProperty("region") &&
                    !window.checkoutConfig.anonymous_order.required_fields.hasOwnProperty("region_id")
                ) {
                    this.setVisible(false);
                    $('[name="region"]').closest('.field').hide();
                    return;
                }
                return this._super(option);
            }
        });
    }
});
