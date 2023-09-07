define([], function () {
    'use strict';

    return function (Component) {
        return Component.extend({
            hideRegion: function (option) {
                if (window.hasOwnProperty("checkoutConfig") &&
                    window.checkoutConfig.anonymous_order.is_enabled &&
                    !window.checkoutConfig.anonymous_order.required_fields.hasOwnProperty("region") &&
                    !window.checkoutConfig.anonymous_order.required_fields.hasOwnProperty("region_id")
                ) {
                    this.setVisible(false);
                    return;
                }
                return this._super(option);
            }
        });
    }
});
