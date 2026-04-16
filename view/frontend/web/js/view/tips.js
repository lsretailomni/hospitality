define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Ls_Hospitality/js/tips-state',
    'jquery',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Customer/js/customer-data'
], function (Component, ko, quote, tipsState, $, getTotalsAction, customerData) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Ls_Hospitality/checkout/tips'
        },

        initialize: function () {
            this._super();

            this.tipsEnabled = ko.observable(false);
            this.tipOptions = ko.observableArray([]);
            this.selected = tipsState.selected;
            this.customValue = tipsState.customValue;

            this.loadSuggestions();

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

            var self = this;

            function updateTotals() {
                try {
                    getTotalsAction([]).done(function () {
                        setTimeout(function () {
                            try {
                                customerData.reload(['cart', 'checkout-data'], true);
                            } catch (e) {
                                console.warn(e);
                            }
                        }, 300);
                    }).fail(function () {
                        console.warn('getTotalsAction failed');
                    });
                } catch (e) {
                    console.warn('updateTotals error', e);
                }
            }

            function saveTip(amount, callback) {
                var postData = {tip: amount};

                if (typeof window.FORM_KEY !== 'undefined') {
                    postData.form_key = window.FORM_KEY;
                }

                $.ajax({
                    url: '/ls_hospitality/ajax/savetip',
                    method: 'POST',
                    data: postData,
                    dataType: 'json'
                }).done(function (res) {
                    try {
                        customerData.invalidate(['cart', 'checkout-data']);
                    } catch (e) {
                        console.warn('customerData.invalidate failed', e);
                    }

                    getTotalsAction([]).done(function () {
                        try {
                            customerData.reload(['cart', 'checkout-data'], true);
                        } catch (e) {
                            console.warn('customerData.reload failed', e);
                        }

                        if (typeof callback === 'function') {
                            callback(null, res);
                        }
                    }).fail(function (err) {
                        if (typeof callback === 'function') {
                            callback(err, res);
                        }
                    });
                }).fail(function (err) {
                    if (typeof callback === 'function') {
                        callback(err);
                    }
                });
            }

            this.selected.subscribe(function (val) {
                var amount = 0;

                if (val === 'other') {
                    amount = parseFloat(tipsState.customValue()) || 0;
                } else {
                    var percentage = parseFloat(val) || 0;
                    var totals = quote.totals() || {};
                    var base = parseFloat(totals.base_grand_total || totals.grand_total || 0);
                    amount = (percentage / 100) * base;
                    amount = Math.round(amount * 100) / 100;
                }

                var label = (val === 'other') ? (tipsState.customValue() || 'Other') : (val + '%');
                tipsState.selectedLabel(label);

                saveTip(amount, function (err, res) {
                    if (err) {
                        console.error('Failed to save tip', err);
                        return;
                    }
                    updateTotals();
                });
            });

            this.customValue.subscribe(function (v) {
                if (tipsState.selected() === 'other') {
                    var amount = parseFloat(v) || 0;
                    tipsState.selectedLabel(v);

                    saveTip(amount, function (err, res) {
                        if (err) {
                            console.error('Failed to save custom tip', err);
                            return;
                        }
                        updateTotals();
                    });
                }
            });

            return this;
        },

        loadSuggestions: function () {
            var self = this;

            $.ajax({
                url: '/ls_hospitality/ajax/tipssuggestions',
                method: 'GET',
                dataType: 'json'
            }).done(function (res) {
                self.tipsEnabled(!!res.enabled);
                self.tipOptions(Array.isArray(res.suggestions) ? res.suggestions : []);
            }).fail(function (err) {
                console.warn('Unable to load tip suggestions', err);
            });
        }
    });
});