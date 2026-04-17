define([
    'jquery',
    'ko',
    'mage/url'
], function ($, ko, urlBuilder) {
    'use strict';

    return {
        isLoading: ko.observable(false),
        isLoaded: ko.observable(false),
        options: ko.observableArray([]),
        error: ko.observable(false),

        load: function () {
            var self = this;

            console.log('tipsLoader.load called');

            self.isLoading(true);
            self.error(false);

            return $.ajax({
                url: urlBuilder.build('ls_hospitality/ajax/tipsSuggestions'),
                type: 'GET',
                dataType: 'json'
            }).done(function (response) {
                if (response && response.success) {
                    self.options(Array.isArray(response.suggestions) ? response.suggestions : []);
                    self.isLoaded(true);
                } else {
                    self.options([]);
                    self.isLoaded(false);
                    self.error(true);
                }
            }).fail(function (xhr) {
                self.options([]);
                self.isLoaded(false);
                self.error(true);
            }).always(function () {
                self.isLoading(false);
            });
        }
    };
});