var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Ls_Hospitality/js/view/plugin/shipping': true
            },
            'Magento_Checkout/js/model/shipping-save-processor/payload-extender': {
                'Ls_Hospitality/js/model/shipping-save-processor/default': true
            }
        }
    },
};
