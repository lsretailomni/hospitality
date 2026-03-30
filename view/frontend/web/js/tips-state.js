define(['ko'], function (ko) {
    'use strict';

    // Shared observable state for tips between components
    return {
        // Temporarily default to 10% so you can see it in the summary while testing
        selected: ko.observable(10),
        customValue: ko.observable(''),
        selectedLabel: ko.observable('10%')
    };
});
