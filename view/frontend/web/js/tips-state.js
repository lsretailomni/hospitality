define(['ko'], function (ko) {
    'use strict';
    return {
        selected: ko.observable(null),
        customValue: ko.observable(''),
        selectedLabel: ko.observable('')
    };
});
