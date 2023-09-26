define([
    'jquery',
    'jquery/ui',
    'Magento_Ui/js/modal/modal'
], function ($, _, modal) {
    'use strict';

    $.widget('mage.optionsmodal', {
        options: {
            widgetOptions: {
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
            },
            optionsContainerId: '#options-modal',
            mainCustomOptionsWrapper: '.main-custom-options-wrapper',
            customOptionValuesContainer: '.custom-option-values-container',
            customOptionValuesOption: '.custom-option-values-container .option',
            customOptionContainer: '.custom-field .custom-option-container',
            productCustomOption: '.custom-field  .product-custom-option',
            modalTitleClass: '.custom-options-modal .modal-title',
        },

        /**
         * Widget initialization
         * @private
         */
        _create: function () {
            this._createCustomOptions();
        },

        /**
         * Adding widget events
         * @private
         */
        _init: function () {
            let self = this,
                body = $('body');

            body.on('click', self.options.customOptionContainer, function () {
                let content = $(this).next()[0].outerHTML,
                    popupContainer;
                $('.' + self.options.widgetOptions.modalClass).remove();
                $(self.options.mainCustomOptionsWrapper).append('<div id="options-modal" style="display: none;"></div>');
                popupContainer = $(self.options.optionsContainerId);
                modal(self.options.widgetOptions, popupContainer);
                popupContainer.empty().append(content);
                popupContainer.find(self.options.customOptionValuesContainer).show();
                $(self.options.modalTitleClass).empty().append($(this).find('.title').html());
                popupContainer.modal('openModal');
            });

            body.on('click', self.options.customOptionValuesOption, function () {
                let parent = $(this).closest(self.options.customOptionValuesContainer),
                    valueId = $(this).data('custom-value-id'),
                    optionId = parent.data('custom-option-id'),
                    option = $(self.options.productCustomOption + "[name^='" + optionId + "']");

                if (!parent.hasClass('multiselect')) {
                    if ($(this).hasClass('selected')) {
                        parent.find('li').removeClass('selected');
                    } else {
                        parent.find('li').removeClass('selected');
                        $(this).toggleClass('selected');
                    }
                } else {
                    $(this).toggleClass('selected');
                }
                option.next().find(self.options.customOptionValuesContainer).remove();
                option.next().append(parent[0].outerHTML);
                option.next().find(self.options.customOptionValuesContainer).hide();

                if (!option.hasClass('multiselect')) {
                    if (option.find('option[value="' + valueId + '"]').attr('selected')) {
                        option.find('option').removeAttr('selected');
                    } else {
                        option.find('option').removeAttr('selected');
                        option.find('option[value="' + valueId + '"]').attr('selected', true);
                    }
                } else {
                    if (option.find('option[value="' + valueId + '"]').attr('selected')) {
                        option.find('option[value="' + valueId + '"]').attr('selected', false);
                    } else {
                        option.find('option[value="' + valueId + '"]').attr('selected', true);
                    }
                }

                option.trigger('change');
            });
        },

        /**
         * Create Custom Options
         * @private
         */
        _createCustomOptions: function () {
            let self = this,
                body = $('body');

            if (body.find(self.options.productCustomOption).length) {
                body.find(self.options.productCustomOption).each(function () {
                    let parent = this;
                    $(this).find('option').each(function () {
                        let child = this,
                            value = $(child).attr('value'),
                            name = $(child).text();

                        let corresponding = $(parent).next().find(
                            self.options.customOptionValuesContainer + ' li[data-custom-value-id="' + value + '"] .title'
                        );

                        if (corresponding.length) {
                            corresponding.text(name);
                        }
                    });
                });
            }
        }
    });

    return $.mage.optionsmodal;
});
