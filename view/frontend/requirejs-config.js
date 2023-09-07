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
            'Magento_Checkout/js/view/billing-address': {
                'Ls_Hospitality/js/view/billing-address-mixin': true
            },
            'Magento_Checkout/js/model/shipping-save-processor/payload-extender': {
                'Ls_Hospitality/js/model/shipping-save-processor/default': true
            },
            'Magento_Ui/js/form/element/region': {
                'Ls_Hospitality/js/form/element/region-mixin': true
            },
            'Magento_Ui/js/form/components/group': {
                'Ls_Hospitality/js/form/components/group-mixin': true
            },
            'Magento_Checkout/js/view/form/element/email': {
                'Ls_Hospitality/js/view/form/element/email-mixin': true
            },
        }
    }
};
