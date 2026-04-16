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
            template: 'Ls_Hospitality/checkout/tips',
            tipsEnabled: false
        },

        initialize: function () {
            this._super();

            // Check if tips are enabled via configuration
            

            // Example options - replace to load from server or checkout config
            // this.options = ko.observableArray([
            //     {label: 'No tip', value: 0},
            //     {label: '5%', value: 5},
            //     {label: '10%', value: 10},
            //     {label: '15%', value: 15},
            //     {label: 'Other', value: 'other'}
            // ]);

            this.options = ko.observableArray([]);
            this.selected = tipsState.selected;
            this.customValue = tipsState.customValue;
            this.loadSuggestions();
            this.selectedLabel = ko.computed(function () {
                var s = this.selected();
                if (s === 'other') {
                    return this.customValue() || 'Other';
                }
                var found = this.options().find(function (o) { return o.value === s; });
                return found ? found.label : '';
            }, this);

            var self = this;            

            // Refresh totals
            function updateTotals(expectedGrandTotal) {
                try {
                    getTotalsAction([]).done(function () {
                        // small delay to let observables update then refresh customer-data
                        setTimeout(function () {
                            try { customerData.reload(['cart', 'checkout-data'], true); } catch (e) { console.warn(e); }
                        }, 300);
                    }).fail(function () {
                        console.warn('getTotalsAction failed');
                    });
                } catch (e) {
                    console.warn('updateTotals error', e);
                }
            }

            //Save Tip to quote
            function saveTip(amount, callback) {
                var data = {tip: amount};
                // include form_key if present on window (Magento places it on window.FORM_KEY)
                if (typeof window.FORM_KEY !== 'undefined') {
                    data.form_key = window.FORM_KEY;
                }

                $.ajax({
                    url: '/ls_hospitality/ajax/savetip',
                    method: 'POST',
                    data: data,
                    dataType: 'json'
                }).done(function (res) {
                    console.log('saveTip response', res);
                    try {
                        // invalidate and reload customer-data sections used in checkout
                        customerData.invalidate(['cart', 'checkout-data']);
                    } catch (e) { console.warn('customerData.invalidate failed', e); }

                    // trigger totals refresh and then reload customer-data
                    getTotalsAction([]).done(function () {
                        try { customerData.reload(['cart', 'checkout-data'], true); } catch (e) { console.warn('customerData.reload failed', e); }
                        if (typeof callback === 'function') callback(null, res);
                    }).fail(function (err) {
                        console.warn('getTotalsAction failed after saveTip', err);
                        // Attempt customerData reload
                        try { customerData.reload(['cart', 'checkout-data'], true); } catch (e) { console.warn(e); }
                        if (typeof callback === 'function') callback(err, res);
                    });
                }).fail(function (err) {
                    console.error('Failed to save tip', err);
                    if (typeof callback === 'function') callback(err);
                });
            }

            // save to quote on tip selection
            this.selected.subscribe(function (val) {
                var amount = 0;
                if (val === 'other') {
                    amount = parseFloat(tipsState.customValue()) || 0;
                } else {
                    var percentage = parseFloat(val) || 0;
                    var totals = quote.totals() || {};
                    var base = parseFloat(totals.base_grand_total || totals.grand_total || 0);
                    amount = (percentage / 100) * base;
                    amount = Math.round(amount * 100) / 100; // 2 decimals
                }

                var label = (val === 'other') ? (tipsState.customValue() || 'Other') : (val + '%');
                tipsState.selectedLabel(label);

                // post to controller to save on quote
                saveTip(amount, function (err, res) {
                    if (err) {
                        console.error('Failed to save tip', err);
                        return;
                    }                    
                    updateTotals(res && res.grand_total !== undefined ? res.grand_total : undefined);
                });
            });

            // save on custom value selection
            this.customValue.subscribe(function (v) {
                if (tipsState.selected() === 'other') {
                    var amount = parseFloat(v) || 0;
                    tipsState.selectedLabel(v);
                    saveTip(amount, function (err, res) {
                        if (err) {
                            console.error('Failed to save custom tip', err);
                            return;
                        }
                        updateTotals(res && res.grand_total !== undefined ? res.grand_total : undefined);
                    });
                }
            });

            return this;
        },
        loadSuggestions: function () {
            if (!this.tipsEnabled) {
                return this;
            }
            $.ajax({
                url: '/ls_hospitality/ajax/tipssuggestions',
                method: 'GET',
                dataType: 'json'
            }).done(function (res) {
                self.tipsEnabled(!!res.enabled);
                self.options(Array.isArray(res.suggestions) ? res.suggestions : []);
            }).fail(function (err) {
                console.warn('Unable to load tip suggestions', err);
            });
            
            // var self = this;
            //
            // $.getJSON(urlBuilder.build('lshospitality/ajax/tipssuggestions'))
            //     .done(function (response) {
            //         self.tipsEnabled = !!response.enabled;
            //         self.options(Array.isArray(response.suggestions) ? response.suggestions : []);
            //     })
            //     .fail(function () {
            //         console.warn('Unable to load tip suggestions');
            //     });
        }
    });
});
