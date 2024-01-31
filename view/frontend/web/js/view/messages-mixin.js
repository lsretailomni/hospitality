define([
    'ko',
    'jquery',
    'uiComponent',
    'jquery-ui-modules/effect-blind'
], function (ko, $, Component, globalMessages) {
    'use strict';
    var mixin = {
        /**
         * @param {Boolean} isHidden
         */
        onHiddenChange: function (isHidden) {
            // Hide message block if needed
            if (isHidden) {
                setTimeout(function () {
                    $(this.selector).hide('slow');
                }.bind(this), 30000000);
            }
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
