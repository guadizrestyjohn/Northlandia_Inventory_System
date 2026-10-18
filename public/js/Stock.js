$(function () {
    StockModule.init();
});

const StockModule = (function () {
    const s = {
        table: '.stock-table',

        stockInForm: '#stock-in-form',
        stockOutForm: '#stock-out-form',

        stockInModal: '#stock-in-modal',
        stockInMode: '#stock-in-mode',
        stockInItemGroup: '#stock-in-item-group',
        stockInItem: '#stock-in-item',
        stockInOutputGroup: '#stock-in-output-group',
        stockInVerificationSection: '#stock-in-output-verification-section',
        stockInOutput: '#stock-in-output-id-select',
        stockInOutputId: '#stock-in-production-output-id',
        stockInProductionInventoryId: '#stock-in-production-inventory-id',
        verifyOutputSelect: '#production-output-to-verify',
        verifyInventorySelect: '#production-output-verify-item',
        verifyOutputButton: '#verify-production-output',
        verifyOutputMessage: '#production-output-verification-message',
        verifyOutputPanel: '#production-output-verification-panel',
        stockOutModal: '#stock-out-modal',

        stockOutMode: '#stock-out-mode',
        stockOutOrderGroup: '#stock-out-order-group',
        stockOutOrder: '#stock-out-order',
        stockOutJobOrder: '#stock-out-job-order',
        stockOutOutputGroup: '#stock-out-production-output-group',
        stockOutOutputs: '#stock-out-production-outputs',
        stockOutOutputMessage: '#stock-out-production-output-message',
        stockOutLinkedInventory: '#stock-out-linked-inventory-id',
        stockOutItem: '#stock-out-item',
        inventoryId: '#inventory_id',

        releaseDetails: '#stock-out-release-details',
        releaseConfirmGroup: '#stock-out-release-confirm-group',
        confirmRelease: '#stock-out-confirm-release',
        manualItemGroup: '#stock-out-manual-item-group',
        movementQuantityGroup: '#stock-out-quantity-group'
    };

    let table = null;

    function init() {
        bindEvents();
        loadStockTransactions();
        formatStockItemOptions();
        handleStockInModeChange();
        $(`${s.stockInForm}, ${s.stockOutForm}`).each(function () {
            $(this).find('[name="idempotency_key"]').val(newRequestKey());
        });
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
        $(s.stockOutOutputs).on('change', handleReleaseOutputChange);

        $(s.stockInMode).on('change', handleStockInModeChange);
        $(s.stockInOutput).on('change', handleProductionOutputChange);
        $('#toggle-output-verification').on('click', function () {
            $(s.verifyOutputPanel).toggleClass('d-none');
        });
        $(s.verifyOutputButton).on('click', verifyProductionOutput);

        $(s.stockOutMode).on(
            'change',
            handleStockOutModeChange
        );

        $(s.stockOutModal).on(
            'show.bs.modal',
            function () {
                handleStockOutModeChange();
            }
        );

        $(`${s.stockInModal}, ${s.stockOutModal}`).on('show.bs.modal', function () {
            $(this).find('[name="idempotency_key"]').val(newRequestKey());
        });
        $(s.stockInModal).on('show.bs.modal', function () {
            loadProductionOutputs();
            handleStockInModeChange();
        });

        $(`${s.stockInModal}, ${s.stockOutModal}`).on(
            'hidden.bs.modal',
            function () {
                const form = $(this).find('form').first();
                if (form.length) form[0].reset();
                if (this.id === 'stock-in-modal') {
                    $(s.stockInOutputId).val('');
                    $(s.stockInProductionInventoryId).val('');
                    handleStockInModeChange();
                }
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
                    data: null,
                    defaultContent: '',
                    render: function (_value, type, row) {
                        const label = row.production_output_id
                            ? `Output ${row.production_output_id} · Release ${row.release_id || '—'}`
                            : '—';
                        return window.renderTableText(label, type);
                    }
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

            const requestData = new FormData(form);

            $.ajax({
                url: `${base_url}/api/Stock/${endpoint}`,
                method: 'POST',
                data: requestData,
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

                    $(form).find('[name="idempotency_key"]').val(newRequestKey());

                    $(document).trigger(
                        'northlandia:stock-transaction-saved',
                        [response]
                    );
                    if (endpoint === 'stockIn') loadProductionOutputs();
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

            if (!option.closest(s.stockOutItem).length) {
                const width = option.attr('data-item-width') || '';
                const height = option.attr('data-item-height') || '';
                const size = option.attr('data-item-size') || '';
                const dimensions = width && height ? `${width}x${height} cm` : size;
                const description = option.attr('data-item-description') || '';
                const dimensionLabel = dimensions || 'Dimensions not specified';
                const descriptionLabel = description ? ` · ${description}` : '';
                option.text(
                    `Item ${option.val()} — ${itemName} · ${dimensionLabel}${descriptionLabel} — ${itemQuantity} ${itemUnit}`
                );
                return;
            }

            const width = option.attr('data-item-width') || '';
            const height = option.attr('data-item-height') || '';
            const size = option.attr('data-item-size') || '';
            const dimensions = width && height ? `${width}x${height} cm` : size;
            const description = option.attr('data-item-description') || '';
            const itemId = option.val();

            option.text(
                `Item ${itemId} — ${itemName}${dimensions ? ` · ${dimensions}` : ''}${description ? ` · ${description}` : ''} — ${itemQuantity} ${itemUnit}`
            );
        });
    }

    function handleStockInModeChange() {
        const receiptMode = $(s.stockInMode).val() === 'production_receipt';
        const item = $(s.stockInItem);
        const productionInventory = $(s.stockInProductionInventoryId);
        const output = $(s.stockInOutput);

        $(s.stockInItemGroup).toggleClass('d-none', receiptMode);
        $(s.stockInOutputGroup).toggleClass('d-none', !receiptMode);
        $(s.stockInVerificationSection).toggleClass('d-none', !receiptMode);
        item.prop('disabled', receiptMode).prop('required', !receiptMode);
        if (receiptMode) {
            item.removeAttr('name');
            productionInventory.attr('name', 'inventory_id').prop('disabled', false).prop('required', true);
            output.prop('disabled', false).prop('required', true);
        } else {
            item.attr('name', 'inventory_id').prop('disabled', false);
            productionInventory.removeAttr('name').prop('disabled', true).prop('required', false).val('');
            output.prop('disabled', true).prop('required', false).val('');
            $(s.stockInOutputId).val('');
            $('#stock-in-quantity').removeAttr('max').val('');
        }
    }

    function loadProductionOutputs() {
        if (!$(s.stockInModal).length) return;
        $.ajax({
            url: `${base_url}/api/Stock/productionOutputs`,
            method: 'GET',
            dataType: 'json',
            cache: false
        }).done(function (response) {
            if (response && response.status === 'error') {
                $(s.verifyOutputSelect).empty().append($('<option>').val('').text('Outputs could not be loaded'));
                $(s.stockInOutput).empty().append($('<option>').val('').text('Outputs could not be loaded'));
                return;
            }
            const outputs = window.apiData(response);
            const reported = $(s.verifyOutputSelect).empty().append($('<option>').val('').text('Select Reported Output'));
            const verified = $(s.stockInOutput).empty().append($('<option>').val('').text('Select Verified Output'));
            outputs.forEach(function (output) {
                const dimensions = `${output.width_cm} x ${output.height_cm} cm`;
                const label = `Output ${output.output_id} · JO-${String(output.job_order_id).padStart(4, '0')} · ${output.product_name} · ${dimensions} · ${window.formatQuantity(output.reported_quantity)} pcs`;
                if (output.verification_status === 'Reported') {
                    reported.append($('<option>').val(output.output_id).text(label).attr('data-output', JSON.stringify(output)));
                }
                if (output.verification_status === 'Verified' && Number(output.remaining_quantity) > 0) {
                    verified.append($('<option>').val(output.output_id).text(`${label} · remaining ${window.formatQuantity(output.remaining_quantity)}`).attr('data-output', JSON.stringify(output)));
                }
            });
            if (reported.children().length === 1) reported.find('option').first().text('No reported output awaiting verification');
            if (verified.children().length === 1) verified.find('option').first().text('No verified output available to receive');
            handleProductionOutputChange();
        }).fail(function () {
            $(s.verifyOutputSelect).empty().append($('<option>').val('').text('Outputs could not be loaded'));
            $(s.stockInOutput).empty().append($('<option>').val('').text('Outputs could not be loaded'));
        });
    }

    function handleProductionOutputChange() {
        const selected = $(s.stockInOutput).find(':selected');
        let output = null;
        try { output = JSON.parse(selected.attr('data-output') || 'null'); } catch (_error) { output = null; }
        $(s.stockInOutputId).val(output ? output.output_id : '');
        $(s.stockInProductionInventoryId).val(output ? output.inventory_id : '');
        $('#stock-in-quantity')
            .attr('max', output ? output.remaining_quantity : '')
            .val('');
    }

    function verifyProductionOutput() {
        const outputId = $(s.verifyOutputSelect).val();
        const inventoryId = $(s.verifyInventorySelect).val();
        const button = $(s.verifyOutputButton).prop('disabled', true);
        if (!outputId || !inventoryId) {
            $(s.verifyOutputMessage).text('Select a reported output and its exact inventory item.');
            button.prop('disabled', false);
            return;
        }
        $.ajax({
            url: `${base_url}/api/Stock/verifyProductionOutput`,
            method: 'POST',
            data: { output_id: outputId, inventory_id: inventoryId },
            dataType: 'json'
        }).done(function (response) {
            $(s.verifyOutputMessage).text(response && response.message ? response.message : 'Verification completed.');
            if (window.isApiSuccess(response)) {
                $(s.verifyOutputSelect).val('');
                $(s.verifyInventorySelect).val('');
                loadProductionOutputs();
            }
        }).fail(function (xhr) {
            $(s.verifyOutputMessage).text(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Production output could not be verified.');
        }).always(function () {
            button.prop('disabled', false);
        });
    }

    function handleOrderChange() {
        const selected = $(this).find(':selected');
        const isRelease = $(s.stockOutMode).val() === 'customer_release';
        const hasSelectedOrder = isRelease && this.value !== '';
        const productType = hasSelectedOrder ? selected.attr('data-product-type') || '' : '';
        const isReadyMade = productType === 'Ready-Made';
        const isCustomMade = productType === 'Custom-Made';

        const jobOrderId = hasSelectedOrder
            ? selected.attr('data-job-order-id') || ''
            : '';

        const details = $(s.releaseDetails);

        const inventory = $(
            `${s.stockOutItem}, ${s.inventoryId}`
        ).first();

        $(s.stockOutJobOrder).val(jobOrderId);
        $(s.stockOutOutputGroup).toggleClass('d-none', !isCustomMade);
        $(s.stockOutOutputs)
            .prop('disabled', !isCustomMade)
            .prop('required', isCustomMade);
        if (isCustomMade) {
            loadReleaseOutputs(this.value, jobOrderId);
        } else {
            $(s.stockOutOutputs).empty();
            $(s.stockOutOutputMessage).text('');
        }

        $(s.releaseConfirmGroup).toggleClass(
            'd-none',
            !isRelease
        );

        $(s.confirmRelease)
            .prop('checked', false)
            .prop('required', isRelease);

        $('#stock-out-item-label').text(isRelease ? 'Actual Finished Product / Modular' : 'Inventory Item');
        $(s.manualItemGroup).removeClass('d-none');
        const linkedItemId = isReadyMade ? selected.attr('data-inventory-id') || '' : '';
        $(s.stockOutItem).prop('required', !isReadyMade && !isCustomMade)
            .prop('disabled', isReadyMade || isCustomMade)
            .attr('name', isReadyMade || isCustomMade ? '' : 'inventory_id');
        $(s.stockOutLinkedInventory)
            .attr('name', isReadyMade || isCustomMade ? 'inventory_id' : '')
            .prop('disabled', !isReadyMade && !isCustomMade)
            .prop('required', false)
            .val(linkedItemId);
        if (isReadyMade) $(s.stockOutItem).val(linkedItemId);
        if (isCustomMade) $(s.stockOutItem).val('');
        $(s.movementQuantityGroup).removeClass('d-none');
        $('#stock-out-quantity')
            .prop('required', true)
            .prop('disabled', false)
            .val(hasSelectedOrder ? selected.attr('data-order-quantity') || '' : '');

        updateReleaseDetails(
            selected,
            jobOrderId,
            hasSelectedOrder,
            details
        );

        updateInventoryOptions(
            inventory,
            isRelease
        );
    }

    function handleStockOutModeChange() {
        const isRelease = $(s.stockOutMode).val() === 'customer_release';
        const order = $(s.stockOutOrder);

        $(s.stockOutOrderGroup).toggleClass('d-none', !isRelease);
        order.prop('required', isRelease).prop('disabled', !isRelease);
        if (!isRelease) {
            order.val('');
        }
        handleOrderChange.call(order[0]);
    }

    function loadReleaseOutputs(orderId, jobOrderId) {
        const select = $(s.stockOutOutputs).empty();
        $(s.stockOutOutputMessage).text('Loading verified production batches…');
        $.ajax({
            url: `${base_url}/api/Stock/releaseOutputs?order_id=${encodeURIComponent(orderId)}&job_order_id=${encodeURIComponent(jobOrderId)}`,
            method: 'GET',
            dataType: 'json',
            cache: false
        }).done(function (response) {
            if (response && response.status === 'error') {
                $(s.stockOutOutputMessage).text(response.message || 'Production batches could not be loaded.');
                return;
            }
            const outputs = window.apiData(response);
            outputs.forEach(function (output) {
                const dimensions = `${output.width_cm} x ${output.height_cm} cm`;
                const label = `Output ${output.output_id} · Item ${output.inventory_id} — ${output.inventory_item_name} · ${dimensions} · available ${window.formatQuantity(output.available_received_quantity)} pcs`;
                select.append(
                    $('<option>').val(output.output_id).text(label)
                        .attr('data-inventory-id', output.inventory_id)
                        .attr('data-available-quantity', output.available_received_quantity)
                );
            });
            $(s.stockOutOutputMessage).text(outputs.length
                ? 'Select one or more batches for the same inventory item. The server checks the total against the Order quantity.'
                : 'No verified, received production output is available for this Order.');
            handleReleaseOutputChange();
        }).fail(function (xhr) {
            $(s.stockOutOutputMessage).text(xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Verified production batches could not be loaded.');
        });
    }

    function handleReleaseOutputChange() {
        const selected = $(s.stockOutOutputs).find('option:selected');
        const inventoryIds = [...new Set(selected.map(function () {
            return String($(this).attr('data-inventory-id') || '');
        }).get().filter(Boolean))];
        if (inventoryIds.length !== 1) {
            $(s.stockOutLinkedInventory).val('');
            $(s.stockOutOutputMessage).toggleClass('text-danger', inventoryIds.length > 1);
            if (inventoryIds.length > 1) {
                $(s.stockOutOutputMessage).text('Selected batches refer to different inventory items. Select batches for one exact item only.');
            }
            $(s.stockOutItem).val('');
            return;
        }
        $(s.stockOutLinkedInventory).val(inventoryIds[0]);
        $(s.stockOutItem).val(inventoryIds[0]);
        $(s.stockOutOutputMessage).removeClass('text-danger');
    }

    function newRequestKey() {
        if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
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
                .text(jobOrderId || (selected.attr('data-product-type') === 'Ready-Made' ? 'Not required — Ready-Made' : '—'));

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
