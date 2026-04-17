define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Ls_Hospitality/js/tips-state',
    'jquery',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Customer/js/customer-data',
    'Ls_Hospitality/js/model/tips-loader',
    'Magento_Checkout/js/model/step-navigator'
], function (
    Component,
    ko,
    quote,
    tipsState,
    $,
    getTotalsAction,
    customerData,
    tipsLoader,
    stepNavigator
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Ls_Hospitality/checkout/tips'
        },

        initialize: function () {
            this._super();

            this.tipOptions = tipsLoader.options;
            this.isLoading = tipsLoader.isLoading;
            this.isLoaded = tipsLoader.isLoaded;
            this.error = tipsLoader.error;
            this.selected = tipsState.selected;
            this.customValue = tipsState.customValue;

            // show/hide based on payment step visibility
            this.isPaymentStep = ko.observable(false);
            var self = this;
            function updateStepVisibility() {
                var paymentStep = stepNavigator.steps().find(function (step) {
                    return step.code === 'payment';
                });

                self.isPaymentStep(
                    !!(paymentStep && typeof paymentStep.isVisible === 'function' && paymentStep.isVisible())
                );
            }

            // initial state
            updateStepVisibility();

            stepNavigator.steps.subscribe(function () {
                updateStepVisibility();
            });

            // react when payment step visibility changes
            var paymentStep = stepNavigator.steps().find(function (step) {
                return step.code === 'payment';
            });

            if (paymentStep && typeof paymentStep.isVisible === 'function') {
                paymentStep.isVisible.subscribe(function () {
                    updateStepVisibility();
                });
            }

            if (!this.isLoaded()) {
                tipsLoader.load();
            }
            this.selectedLabel = ko.computed(function () {
                var s = this.selected();
                if (s === 'other') {
                    return this.customValue() || 'Other';
                }
                var found = this.tipOptions().find(function (o) {
                    return o.value === s;
                });
                return found ? found.label : '';
            }, this);

            this.selected.subscribe(function (val) {
                var amount = 0;
                if (val === 'other') {
                    amount = parseFloat(tipsState.customValue()) || 0;
                } else {
                    var percentage = parseFloat(val) || 0;
                    var totals = quote.totals() || {};
                    var base = parseFloat(totals.base_grand_total || totals.grand_total || 0);
                    amount = Math.round(((percentage / 100) * base) * 100) / 100;
                }
                saveTip(amount);
            });

            this.customValue.subscribe(function (v) {
                if (tipsState.selected() === 'other') {
                    saveTip(parseFloat(v) || 0);
                }
            });

            function saveTip(amount) {
                var postData = { tip: amount };
                if (typeof window.FORM_KEY !== 'undefined') {
                    postData.form_key = window.FORM_KEY;
                }
                $.ajax({
                    url: '/ls_hospitality/ajax/savetip',
                    method: 'POST',
                    data: postData,
                    dataType: 'json'
                }).done(function () {
                    getTotalsAction([]);
                    customerData.reload(['cart', 'checkout-data'], true);
                }).fail(function (err) {
                    console.error('Failed to save tip', err);
                });
            }
            return this;
        }
    });
});