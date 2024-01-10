define([], function () {
    'use strict';

    return function (Component) {
        return Component.extend({
            initObservable: function () {
                this._super();
                if (window.hasOwnProperty("checkoutConfig") &&
                    window.checkoutConfig.hasOwnProperty("anonymous_order") &&
                    window.checkoutConfig.anonymous_order.is_enabled &&
                    !window.checkoutConfig.anonymous_order.required_fields.hasOwnProperty("street")
                ) {
                    this.setVisible(false);
                    return this;
                }
                return this;
            },
        });
    }
});
