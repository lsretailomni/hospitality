var config = {
    paths: {
        'options-modal': 'Ls_Hospitality/js/options/modal',
        'imageGrayscale': 'Ls_Hospitality/js/view/image-grayscale'
    },
    shim: {
        'options-modal': {
            deps: ['jquery']
        }
    },
    deps: [
        'Ls_Hospitality/js/view/image-grayscale'
    ],
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
            'Magento_Ui/js/form/element/abstract': {
                'Ls_Hospitality/js/form/element/region-text-mixin': true
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
            'Magento_Ui/js/view/messages': {
                'Ls_Hospitality/js/view/messages-mixin': true
            },
            'Magento_Checkout/js/action/place-order': {
                'Ls_Hospitality/js/mixin/order-comment-place-order-mixin' : true
            }
        }
    },

    map: {
        'Ls_Omni/js/model/shipping-save-processor/checkout': {
            'Ls_Omni/js/model/shipping-save-processor/payload-extender': 'Ls_Hospitality/js/model/shipping-save-processor/payload-extender'
        }
    },
};
