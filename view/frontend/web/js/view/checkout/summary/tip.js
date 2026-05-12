define(['uiComponent','ko','Ls_Hospitality/js/tips-state'], function(Component, ko, tipsState) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Ls_Hospitality/checkout/summary/tip'
        },
        initialize: function () {
            this._super();
            this.selectedLabel = tipsState.selectedLabel;
            console.log('Ls_Hospitality/checkout/summary/tip initialized, label:', this.selectedLabel());
            return this;
        }
    });
});
