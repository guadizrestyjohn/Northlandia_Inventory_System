$(function () {
    const modal = $('#tax-setting-modal');
    const form = $('#tax-setting-form');

    function reset() {
        form[0].reset();
        $('#tax-setting-id').val('');
        $('#tax-setting-name').val('VAT');
        $('#tax-setting-mode').val('Inclusive');
        $('#tax-setting-status').val('Active');
        $('#tax-setting-modal-title').text('New Tax Configuration');
    }

    $('#tax-setting-new').on('click', function () {
        const vatRows = $('#tax-settings-table .js-edit-tax-setting').filter(function () {
            return String($(this).attr('data-tax-name') || '').trim().toUpperCase() === 'VAT';
        });
        const activeVatRows = vatRows.filter(function () {
            return $(this).attr('data-status') === 'Active';
        });
        if (activeVatRows.length === 1) {
            activeVatRows.trigger('click');
            return;
        }
        if (vatRows.length === 1) {
            vatRows.trigger('click');
            return;
        }
        reset();
        modal.modal('show');
    });

    $('#tax-settings-table').on('click', '.js-edit-tax-setting', function () {
        $('#tax-setting-id').val($(this).attr('data-tax-id'));
        $('#tax-setting-name').val($(this).attr('data-tax-name'));
        $('#tax-setting-rate').val($(this).attr('data-tax-rate'));
        $('#tax-setting-mode').val($(this).attr('data-calculation-mode'));
        $('#tax-setting-status').val($(this).attr('data-status'));
        $('#tax-setting-modal-title').text('Edit Tax Configuration');
        modal.modal('show');
    });

    form.on('submit', function (event) {
        event.preventDefault();
        const button = $('#tax-setting-save').prop('disabled', true);
        $.ajax({
            url: `${base_url}/api/TaxSettings/save`,
            method: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json'
        })
            .done(function (response) {
                showMessage(response && response.message
                    ? response.message
                    : 'Unable to save Tax Settings.');
                if (window.isApiSuccess(response)) {
                    modal.modal('hide');
                    window.location.reload();
                }
            })
            .fail(handleAjaxError)
            .always(function () {
                button.prop('disabled', false);
            });
    });
});
