define(['jquery'], function ($) {
    "use strict";
    return function (config) {
        $('#ls_mag_service_selected_store').on('change', function () {
            let baseUrl = $('#ls_mag_service_base_url').val();
            let storeId = $('#ls_mag_service_selected_store').val();
            if (storeId == "") {
                return false;
            }
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                showLoader: true,
                dataType: 'json',
                data: {baseUrl: baseUrl, storeId: storeId},
                complete: function (response) {
                    let salesTypes = response.responseJSON.salesType;
                    $("#ls_mag_hospitality_delivery_salas_type option").remove();
                    $("#ls_mag_hospitality_takeaway_sales_type option").remove();
                    $.each(salesTypes, function (i, salesType) {
                        $('#ls_mag_hospitality_delivery_salas_type').append($('<option>', {
                            value: salesType.value,
                            text: salesType.label
                        }));
                    });
                    $.each(salesTypes, function (i, salesType) {
                        $('#ls_mag_hospitality_takeaway_sales_type').append($('<option>', {
                            value: salesType.value,
                            text: salesType.label
                        }));
                    });
                },
                error: function (xhr, status, errorThrown) {
                    console.log(errorThrown);
                }
            });
        });
    }
});
