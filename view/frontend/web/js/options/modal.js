require(
    [
        'jquery',
        'Magento_Ui/js/modal/modal',
        'domReady!'
    ],
    function (
        $,
        modal
    ) {
        let options = {
            title: $.mage.__('Exclude Ingredients/Select Sides'),
            type: 'popup',
            responsive: true,
            innerScroll: true,
            modalClass: 'custom-options-modal',
            buttons: [{
                text: $.mage.__('Continue'),
                class: 'mymodal1',
                click: function () {
                    this.closeModal();
                }
            }]
        };
        let popupContainer = $('#options-modal'),
            popup = modal(options, popupContainer),
            body = $('body');

        if (body.find('.product-custom-option').length) {
            body.find('.product-custom-option').each(function () {
                let parent = this;
                $(this).find('option').each(function () {
                    let child = this,
                        value = $(child).attr('value'),
                        name = $(child).text();

                    let corresponding = $(parent).next().find('.custom-option-values-container li[data-custom-value-id="' + value + '"] .title');

                    if (corresponding.length) {
                        corresponding.text(name);
                    }
                });
            });
        }

        body.on('click', '.custom-field .custom-option-container', function () {
            let content = $(this).next()[0].outerHTML;
            popupContainer.empty().append(content);
            popupContainer.find('.custom-option-values-container').show();
            $('.custom-options-modal .modal-title').empty().append($(this).find('.title').html());
            popupContainer.modal('openModal');
        });
        body.on('click', '.custom-option-values-container .option', function () {
            let parent = $(this).closest('.custom-option-values-container'),
                valueId = $(this).data('custom-value-id'),
                optionId = parent.data('custom-option-id');

            if (!parent.hasClass('multiselect')) {
                parent.find('li').removeClass('selected');
            }
            $(this).addClass('selected');
            let option = $(".custom-field  .product-custom-option[name^='" + optionId +"']");
            option.next().find('.custom-option-values-container').remove();
            option.next().append(parent[0].outerHTML);
            option.next().find('.custom-option-values-container').hide();

            if (!option.hasClass('multiselect')) {
                option.find('option').removeAttr('selected');
            }
            option.find('option[value="' + valueId + '"]').attr('selected', true);
            option.trigger('change');
        });
    }
);
