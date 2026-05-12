define(['jquery'], function ($) {
    "use strict";
    return function (config) {
        // Cache references to input fields using camelCase
        const baseUrlInput = $('#ls_mag_service_base_url');
        const selectedStoreInput = $('#ls_mag_service_selected_store');
        const tenantInput = $('#ls_mag_service_tenant');
        const clientIdInput = $('#ls_mag_service_client_id');
        const clientSecretInput = $('#ls_mag_service_client_secret');
        const centralTypeInput = $('#ls_mag_service_central_type');
        const companyNameInput = $('#ls_mag_service_company_name');
        const environmentNameInput = $('#ls_mag_service_environment_name');
        const webServiceUri = $('#ls_mag_service_web_service_uri');
        const odataUri = $('#ls_mag_service_odata_service_uri');
        const usernameInput = $('#ls_mag_service_username');
        const passwordInput = $('#ls_mag_service_password');
        // Group all related actions into one object
        const api = {
            // Gathers common data used in multiple AJAX requests
            collectCommonData: function () {
                return {
                    baseUrl: baseUrlInput.val(),
                    scopeId: config.websiteId,
                    tenant: tenantInput.val(),
                    client_id: clientIdInput.val(),
                    client_secret: clientSecretInput.val(),
                    central_type: centralTypeInput.val(),
                    company_name: companyNameInput.val(),
                    environment_name: environmentNameInput.val(),
                    web_service_uri: webServiceUri.val(),
                    odata_uri: odataUri.val(),
                    username: usernameInput.val(),
                    password: passwordInput.val()
                };
            },

            // Validates base URL input using Magento's validation library
            validateBaseUrl: function () {
                baseUrlInput.validation();
                return baseUrlInput.validation('isValid');
            },

            // Fetches and populates tender types for each item
            fetchSalesTypes: function () {
                if (!api.validateBaseUrl()) {
                    return;
                }

                $.ajax({
                    url: config.ajaxUrl,
                    type: 'POST',
                    showLoader: true,
                    dataType: 'json',
                    data: {
                        ...api.collectCommonData(),
                        storeId: selectedStoreInput.val()
                    },
                    complete: function (response) {
                        const types = response.responseJSON.salesType;
                        $("#ls_mag_hospitality_delivery_salas_type option").remove();
                        $("#ls_mag_hospitality_takeaway_sales_type option").remove();
                        $.each(types, function (i, salesType) {
                            $('#ls_mag_hospitality_delivery_salas_type').append($('<option>', {
                                value: salesType.value,
                                text: salesType.label
                            }));
                        });
                        $.each(types, function (i, salesType) {
                            $('#ls_mag_hospitality_takeaway_sales_type').append($('<option>', {
                                value: salesType.value,
                                text: salesType.label
                            }));
                        });
                    }
                });
            },
        };

        // When store dropdown changes, fetch hierarchy and tender types
        selectedStoreInput.on('change', function () {
            if ($(this).val()) {
                api.fetchSalesTypes();
            }
        });
    }
});
