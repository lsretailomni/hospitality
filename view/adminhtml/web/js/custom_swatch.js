/******* Start Jquery *******/
define([
    'jquery',
    'jquery/ui',
    "Magento_Ui/js/modal/modal",
    "Magento_Ui/js/lib/view/utils/async",
  ], function(jQuery){
    "use strict";
    // DOM ready
    jQuery(function(){
      setTimeout(function(){
        if(jQuery('.catalog-product-edit .fieldset-wrapper-title').length) {
            jQuery('.catalog-product-edit .fieldset-wrapper[data-index="custom_options"] .fieldset-wrapper-title').on('click', function() {
            setTimeout(function() {
              var cntPreview = jQuery('.catalog-product-edit .admin__field.preview-icon').length;
              var cntUpload = jQuery('.catalog-product-edit .admin__field.awesome-upload').length;
                jQuery('.catalog-product-edit .admin__field.preview-icon').each(function() {
                addPreview(jQuery(this));
                if (!--cntPreview) removeEvent();
              });
                jQuery('.catalog-product-edit .admin__field.awesome-upload').each(function() {
                    jQuery('.admin__field-control', this).append('<label><svg xmlns="http://www.w3.org/2000/svg" width="20" height="17" viewBox="0 0 20 17"><path d="M10 0l-5.2 4.9h3.3v5.1h3.8v-5.1h3.3l-5.2-4.9zm9.3 11.5l-3.2-2.1h-2l3.4 2.6h-3.5c-.1 0-.2.1-.2.1l-.8 2.3h-6l-.8-2.2c-.1-.1-.1-.2-.2-.2h-3.6l3.4-2.6h-2l-3.2 2.1c-.4.3-.7 1-.6 1.5l.6 3.1c.1.5.7.9 1.2.9h16.3c.6 0 1.1-.4 1.3-.9l.6-3.1c.1-.5-.2-1.2-.7-1.5z"></path></svg> <span>Choose a file…</span></label>');
                if (!--cntUpload) addEvent();
              });
            }, 2000);
          });
        }
      },4000);
      var addPreview = function($obj) {
        var imgpath = jQuery('.admin__control-text', $obj).val();
        if(imgpath) $obj.append('<div class="img-wrapper"><img src="/media/'+imgpath+'" class="preview img-responsive" /></div><div class="x-close">remove</div>');
      };
      var removeEvent = function() {
          jQuery('.catalog-product-edit .x-close').on('click', function () {
              jQuery(this).parent().find('.admin__control-text').val('');
              jQuery(this).parent().find('.img-wrapper').remove();
        });
      };
      var addEvent = function() {
          jQuery('.admin__control-file[type="file"]').on('change', function () {
            var val = jQuery(this).val();
              jQuery(this).next('label').text(val);
        });
          jQuery('.catalog-product-edit .admin__field.awesome-upload .admin__field-control label').on('click', function () {
              jQuery(this).prev().trigger('click');
        });
      }

    });
  }
);
