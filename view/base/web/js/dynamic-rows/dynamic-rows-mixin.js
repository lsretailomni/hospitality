define(function () {
    'use strict';

    var mixin = {
        defaults: {
            pageSize: 200
        },
    };

    return function (target) {
        return target.extend(mixin);
    };
});
