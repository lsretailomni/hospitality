var config = {
    paths: {
        'options-modal': 'Ls_Hospitality/js/options/modal'
    },
    shim: {
        'options-modal': {
            deps: ['jquery']
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Ls_Hospitality/js/view/plugin/shipping': true
            },
            'Magento_Checkout/js/model/shipping-save-processor/payload-extender': {
                'Ls_Hospitality/js/model/shipping-save-processor/default': true
            }
        }
    }
};
