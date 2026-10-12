$(function () {
    StockModule.init();
});

const StockModule = (function () {
    const s = {
        table: '.stock-table',

        stockInForm: '#stock-in-form',
        stockOutForm: '#stock-out-form',

        stockInModal: '#stock-in-modal',
        stockOutModal: '#stock-out-modal',

        stockOutOrder: '#stock-out-order',
        stockOutJobOrder: '#stock-out-job-order',
        stockOutItem: '#stock-out-item',
        inventoryId: '#inventory_id',

        releaseDetails: '#stock-out-release-details',
        releaseConfirmGroup: '#stock-out-release-confirm-group',
        confirmRelease: '#stock-out-confirm-release'
    };

    let table = null;

    function init() {
        bindEvents();
        loadStockTransactions();
        formatStockItemOptions();
    }

    function bindEvents() {
        submitStockForm(
            s.stockInForm,
            'stockIn',
            s.stockInModal
        );

        submitStockForm(
            s.stockOutForm,
            'stockOut',
            s.stockOutModal
        );

        $(s.stockOutOrder).on(
            'change',
            handleOrderChange
        );

        $(s.stockOutModal).on(
            'show.bs.modal',
            function () {
                $(s.stockOutOrder).trigger('change');
            }
        );

        $(`${s.stockInModal}, ${s.stockOutModal}`).on(
            'hidden.bs.modal',
            function () {
                const form = $(this).find('form').first();
                if (form.length) form[0].reset();
            }
        );
    }

    function loadStockTransactions() {
        const stockTable = $(s.table);

        if (!stockTable.length) {
            return;
        }

        table = stockTable.DataTable({
            ajax: {
                url: `${base_url}/api/Stock/all`,
                dataSrc: window.apiData,
                cache: false,
                error: handleAjaxError
            },

            columns: [
                {
                    data: 'transaction_id',
                    defaultContent: '',
                    render: window.renderTableTransactionNumber
                },
                {
                    data: 'reference_no',
                    defaultContent: '',
                    render: window.renderTableText
                },
                {
                    data: 'order_id',
                    defaultContent: '',
                    render: window.renderTableOrderNumber
                },
                {
                    data: 'job_order_id',
                    defaultContent: '',
                    render: window.renderTableJobOrderNumber
                },
                {
                    data: 'item_name',
                    defaultContent: '',
                    render: window.renderTableText
                },
                {
                    data: 'transaction_type',
                    defaultContent: '',
                    render: window.renderTableText
                },
                {
                    data: 'quantity',
                    defaultContent: '',
                    render: window.renderTableQuantity
                },
                {
                    data: 'remarks',
                    defaultContent: '',
                    render: window.renderTableText
                },
                {
                    data: 'transaction_date',
                    defaultContent: '',
                    render: window.renderTableText
                },
                {
                    data: null,
                    render: function (row) {
                        return window.renderTableText(
                            row.full_name || row.username || '',
                            'display'
                        );
                    }
                }
            ],

            paging: false,
            info: false
        });
    }

    function submitStockForm(formSelector, endpoint, modalSelector) {
        $(formSelector).on('submit', function (event) {
            event.preventDefault();

            const form = this;
            const button = $(form)
                .find('[type="submit"]')
                .prop('disabled', true);

            $.ajax({
                url: `${base_url}/api/Stock/${endpoint}`,
                method: 'POST',
                data: new FormData(form),
                processData: false,
                contentType: false
            })
                .done(function (response) {
                    if (!window.isApiSuccess(response)) {
                        showMessage(response && response.message ? response.message : 'Unable to save stock transaction.');
                        return;
                    }
                    showMessage(
                        response.message ||
                        'Stock transaction saved successfully.'
                    );

                    if (
                        endpoint === 'stockOut' &&
                        $(s.stockOutOrder).val()
                    ) {
                        $(s.stockOutOrder)
                            .find('option:selected')
                            .remove();

                        $(s.stockOutOrder).val('');
                        $(s.stockOutItem).val('');
                    }

                    $(modalSelector).modal('hide');

                    if (table) {
                        table.ajax.reload(null, false);
                    }

                    $(document).trigger(
                        'northlandia:stock-transaction-saved',
                        [response]
                    );
                })
                .fail(function (xhr) {
                    handleAjaxError(xhr);
                })
                .always(function () {
                    button.prop('disabled', false);
                });
        });
    }

    function formatStockItemOptions() {
        $('[data-stock-item-option]').each(function () {
            const option = $(this);

            const itemName =
                option.attr('data-item-name') || '';

            const itemQuantity =
                window.formatQuantity(
                    option.attr('data-item-quantity')
                );

            const itemUnit =
                option.attr('data-item-unit') || '';

            option.text(
                `${itemName} — ${itemQuantity} ${itemUnit}`
            );
        });
    }

    function handleOrderChange() {
        const selected = $(this).find(':selected');
        const isRelease = this.value !== '';

        const jobOrderId = isRelease
            ? selected.attr('data-job-order-id') || ''
            : '';

        const details = $(s.releaseDetails);

        const inventory = $(
            `${s.stockOutItem}, ${s.inventoryId}`
        ).first();

        $(s.stockOutJobOrder).val(jobOrderId);

        $(s.releaseConfirmGroup).toggleClass(
            'd-none',
            !isRelease
        );

        $(s.confirmRelease)
            .prop('checked', false)
            .prop('required', isRelease);

        updateReleaseDetails(
            selected,
            jobOrderId,
            isRelease,
            details
        );

        updateInventoryOptions(
            inventory,
            isRelease
        );
    }

    function updateReleaseDetails(
        selected,
        jobOrderId,
        isRelease,
        details
    ) {
        if (
            details.find('[data-release-field]').length
        ) {
            details
                .find('[data-release-field="customer"]')
                .text(
                    selected.attr('data-customer') || '—'
                );

            details
                .find('[data-release-field="job-order"]')
                .text(jobOrderId || '—');

            details
                .find('[data-release-field="product"]')
                .text(
                    selected.attr('data-product') || '—'
                );

            details
                .find('[data-release-field="specifications"]')
                .text(
                    selected.attr('data-specifications') ||
                    'No specifications provided'
                );

            details
                .find('[data-release-field="quantity"]')
                .text(
                    window.formatQuantity(
                        selected.attr('data-order-quantity')
                    )
                );

            details
                .find('[data-release-field="status"]')
                .text(
                    selected.attr('data-production-status') ||
                    '—'
                );
        } else if (isRelease) {
            details
                .text(
                    `${window.formatOrderNumber(selected.val())} · ${window.formatJobOrderNumber(jobOrderId)}`
                )
                .removeClass('d-none');
        }

        details.toggleClass(
            'd-none',
            !isRelease
        );
    }

    function updateInventoryOptions(
        inventory,
        isRelease
    ) {
        inventory.find('option').each(function () {
            if (!this.value) {
                return;
            }

            const category =
                $(this).attr('data-category');

            let releasable = false;

            if (
                category === 'Finished Products' ||
                category === 'Modulars'
            ) {
                releasable = true;
            }

            $(this).prop(
                'disabled',
                isRelease && !releasable
            );
        });

        if (
            isRelease &&
            inventory
                .find('option:selected')
                .prop('disabled')
        ) {
            inventory.val('');
        }
    }

    return {
        init
    };
})();
