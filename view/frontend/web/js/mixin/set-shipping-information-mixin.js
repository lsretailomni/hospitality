define([
    'jquery',
    'Ls_Hospitality/js/model/tips-loader'
], function ($, tipsLoader) {
    'use strict';

    return function (originalAction) {
        return function () {
            var result = originalAction.apply(this, arguments);

            $.when(result).done(function () {
                tipsLoader.load();
            });

            return result;
        };
    };
});