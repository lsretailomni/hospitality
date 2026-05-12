define([
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Checkout/js/model/totals'
    ], function (Component, totals) {
        "use strict";
        return Component.extend({
            defaults: {
                template: 'Ls_Hospitality/totals/tips-selected'
            },

            /**
             * Get loyalty points discount total
             * @returns {*}
             */
            getTotal: function () {
                return totals.getSegment('ls_tips_selected');
            },

            /**
             * Get loyalty points discount formatted
             * @returns {*|String}
             */
            getValue: function () {
                return this.getFormattedPrice(this.getTotal().value);
            }
        });
    }
);
