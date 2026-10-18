$(function () {
    OrdersModule.init();
});

const OrdersModule = (function () {
    const s = {
        table: '#orders-table',
        form: '#order-form',
        modal: '#order-modal',
        paymentsModal: '#order-payments-modal',
        paymentForm: '#order-payment-form',
        readyMadeApprovalForm: '#ready-made-approval-form',
        newBtn: '#btn-add-order',
        saveBtn: '#order-save',
        modalTitle: '#order-modal-title',

        id: '#order-id',
        customer: '#order-customer',
        customerSearch: '#order-customer-search',
        date: '#order-date',
        productType: '#order-product-type',
        product: '#order-product',
        inventoryProductGroup: '#order-inventory-product-group',
        customProductGroup: '#order-custom-product-group',
        customProduct: '#order-custom-product',
        legacyProductNote: '#order-legacy-product-note',
        quantity: '#order-quantity',
        unitPrice: '#order-unit-price',
        unitPriceHelp: '#order-unit-price-help',
        specifications: '#order-specifications',
        orderTotal: '#order-total',
        balancePreview: '#order-balance-preview',
        downpayment: '#order-downpayment',
        delivery: '#order-delivery',
        status: '#order-status',
        statusPreserved: '#order-status-preserved',
        statusHelp: '#order-status-help',
        totalLabel: '#order-total-label',
        totalHelp: '#order-total-help',
        taxPreview: '#order-tax-preview',
        taxWarning: '#order-tax-warning',
        paymentOrderId: '#order-payment-order-id',
        paymentAmount: '#order-payment-amount',
        paymentSave: '#order-payment-save'
    };

    let customerSearchRequest = null;
    let customerSearchTimer = null;
    let currentTaxConfiguration = null;
    let ordersTable = null;
    let orderStatusFilter = '';
    let unitPriceMode = true;
    let existingTotalPaidCents = 0;

    function init() {
        bindEvents();
        loadOrders();
    }

    function bindEvents() {
        const canCreate = $(s.table).attr('data-can-create') === '1';
        const canUpdate = $(s.table).attr('data-can-update') === '1';

        if (!canCreate) {
            $(s.modal).remove();
        }

        $(s.newBtn).on('click', showAddModal);
        $(s.table).on('click', '.js-edit-order', handleEdit);
        $(s.table).on('click', '.js-order-payments', handlePayments);
        $(s.form).on('submit', submitForm);
        $(s.paymentForm).on('submit', submitPayment);
        $(s.readyMadeApprovalForm).on('submit', submitReadyMadeApproval);
        $('#order-status-filter').on('change', function () {
            orderStatusFilter = String(this.value || '');
            applyOrderStatusFilter();
        });
        $(s.modal).on('hidden.bs.modal', resetForm);
        $(s.customerSearch).on('input', searchCustomers);
        $(s.productType).on('change', updateProductFields);
        $(s.orderTotal).on('input', updateTaxPreview);
        $(s.unitPrice).on('input', updateTaxPreview);
        $(s.quantity).on('input', updateTaxPreview);
        $(s.downpayment).on('input', updateTaxPreview);
        $(s.customer).on('change', function () {
            const option = this.options[this.selectedIndex];
            const selectedCustomer = $(this).val();
            $(s.customerSearch).val(selectedCustomer ? option.text : '');
            $(s.customerSearch)
                .attr('data-selected-label', selectedCustomer ? option.text : '');
        });

        if (!canUpdate) {
            $(s.table).off('click', '.js-edit-order');
        }
    }

    function showAddModal(e) {
        e.preventDefault();

        resetForm();

        $(s.id).val('');
        $(s.modalTitle).text('Add Order');
        $(s.saveBtn).text('Save Order');

        $(s.status)
            .val('Pending')
            .prop('disabled', true);

        $(s.statusPreserved)
            .val('')
            .prop('disabled', true);

        $(s.statusHelp).text('New orders start as Pending.');

        unitPriceMode = true;
        existingTotalPaidCents = 0;
        $(s.unitPrice).prop('disabled', false).prop('required', true).prop('readonly', false);
        $(s.orderTotal).prop('readonly', true);
        $(s.unitPriceHelp).text('Agreed price for one piece, including VAT.');

        loadDefaultCustomers();
        loadProducts();
        loadTaxConfiguration();
        $(s.modal).modal('show');
    }

    function handleEdit(e) {
        e.preventDefault();

        const id = $(this).data('id');

        $.ajax({
            url: `${base_url}/api/Orders/edit/${id}`,
            type: 'GET',
            dataType: 'json'
        })
            .done((res) => {
                if (!res || res.status === 'error' || !res.order_id) {
                    if (res && res.status === 'error') {
                        showMessage(res.message || 'Unable to load order.');
                    }
                    return;
                }

                loadProducts(function () {
                    fillOrder(res);
                    $(s.modalTitle).text('Edit Order');
                    $(s.saveBtn).text('Update Order');
                    $(s.modal).modal('show');
                });
            })
            .fail((xhr) => {
                handleAjaxError(xhr);
            });
    }

    function fillOrder(order) {
        $(s.id).val(order.order_id);
        setCustomerOption(order.customer_id, order.customer_name);
        $(s.date).val(order.order_date || '');
        $(s.productType).empty()
            .append($('<option>').val('').text('Select Product Type'))
            .append($('<option>').val('Ready-Made').text('Ready-Made'))
            .append($('<option>').val('Custom-Made').text('Custom-Made'));
        if (!order.product_type && !order.inventory_id) {
            $(s.productType).append($('<option>').val('legacy').text('Legacy Order — unclassified'));
            $(s.productType).val('legacy');
        } else {
            $(s.productType).val(order.product_type || '');
        }
        if (order.product_type === 'Ready-Made') {
            $(s.product).val(String(order.inventory_id || ''));
            if (!$(s.product).val() && order.inventory_id) {
                const dimensions = order.inventory_width_cm && order.inventory_height_cm
                    ? `${order.inventory_width_cm}x${order.inventory_height_cm} cm`
                    : (order.inventory_size || 'Dimensions not specified');
                $(s.product).append(
                    $('<option>').val(order.inventory_id).text(
                        `Item ${order.inventory_id} — ${order.inventory_item_name || order.product_name} · ${dimensions} (current linked item)`
                    )
                ).val(String(order.inventory_id));
            }
        } else if (order.product_type === 'Custom-Made') {
            $(s.customProduct).val(order.product_name || '');
        } else {
            $(s.customProduct).val('');
        }
        updateProductFields();
        if (!order.product_type && !order.inventory_id) {
            $(s.legacyProductNote).text(
                `This existing Order is unclassified. The product “${order.product_name || ''}” will be preserved until you explicitly select a new type.`
            );
        }
        $(s.quantity).val(window.formatQuantity(order.quantity));
        $(s.specifications).val(order.specifications || '');
        existingTotalPaidCents = toCents(order.total_paid || order.downpayment || '0');
        unitPriceMode = order.unit_price !== null && order.unit_price !== undefined && order.unit_price !== '';
        $(s.unitPrice).val(unitPriceMode ? order.unit_price : '')
            .prop('disabled', !unitPriceMode)
            .prop('required', unitPriceMode)
            .prop('readonly', false);
        $(s.unitPriceHelp).text(unitPriceMode
            ? 'Agreed price for one piece, including VAT.'
            : 'No Unit Price was recorded for this historical Order; it remains unchanged.');
        $(s.orderTotal).prop('readonly', unitPriceMode);
        const hasTaxSnapshot = order.tax_name_snapshot != null
            && order.tax_rate_snapshot != null
            && order.tax_amount_snapshot != null
            && order.subtotal_before_tax_snapshot != null
            && order.tax_calculation_mode_snapshot != null;
        if (hasTaxSnapshot) {
            configureTaxPreview({
                tax_name: order.tax_name_snapshot,
                tax_rate: String(order.tax_rate_snapshot),
                calculation_mode: unitPriceMode ? 'Inclusive' : order.tax_calculation_mode_snapshot
            });
            $(s.orderTotal).val(!unitPriceMode && order.tax_calculation_mode_snapshot === 'Exclusive'
                ? order.subtotal_before_tax_snapshot
                : order.order_total);
            updateTaxPreview();
        } else {
            currentTaxConfiguration = null;
            $(s.taxPreview).addClass('d-none');
            $(s.orderTotal).val(order.order_total || 0);
            $(s.totalLabel).text('Order Total');
            $(s.totalHelp).text('Legacy Order total. Historical Orders are not recalculated using current Tax Settings.');
        }
        if (unitPriceMode) updateTaxPreview();
        else updateBalancePreview();
        $(s.downpayment).val(order.downpayment || 0);
        $(s.downpayment).prop('readonly', true);
        updateTaxPreview();
        $(s.delivery).val(order.delivery_method || '');

        $(s.status)
            .val(order.status || 'Pending')
            .prop('disabled', false);

        if (!['Pending', 'Cancelled'].includes(order.status)) {
            $(s.status).prop('disabled', true);

            $(s.statusPreserved)
                .val(order.status)
                .prop('disabled', false);

            $(s.statusHelp).text(
                'The current status is preserved while the order is being edited.'
            );
        } else {
            $(s.statusPreserved)
                .val('')
                .prop('disabled', true);

            $(s.statusHelp).text(
                'Status can be changed between Pending and Cancelled.'
            );
        }

        $(s.saveBtn).prop('disabled', false);

        $(s.product).trigger('focus');
    }

    function submitForm(e) {
        e.preventDefault();

        const id = $(s.id).val();
        if (!id && !currentTaxConfiguration) {
            e.preventDefault();
            showMessage('An active tax configuration is required before creating an Order.');
            return;
        }
        const fd = new FormData(e.target);

        if ($(s.statusPreserved).val()) {
            fd.set('status', $(s.statusPreserved).val());
        }

        const url = id
            ? `${base_url}/api/Orders/edit/${id}`
            : `${base_url}/api/Orders/add`;

        $.ajax({
            url: url,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
            .done((res) => {
                showMessage(
                    res.message || 'Order saved successfully.'
                );

                if (!window.isApiSuccess(res)) return;
                loadOrders();
                $(s.modal).modal('hide');
                resetForm();
            })
            .fail((xhr) => {
                handleAjaxError(xhr);
            });
    }

    function loadOrders() {
        const canUpdate = $(s.table).attr('data-can-update') === '1';

        ordersTable = $(s.table).DataTable({
            responsive: true,
            autoWidth: true,
            destroy: true,
            paging: false,
            info: false,

            ajax: {
                url: `${base_url}/api/Orders/all`,
                type: 'GET',
                dataSrc: window.apiData,
                error: handleAjaxError
            },

            columns: [
                {
                    data: 'order_id',
                    render: window.renderTableOrderNumber
                },
                {
                    data: 'customer_name'
                },
                {
                    data: 'order_date'
                },
                {
                    data: 'product_name'
                },
                {
                    data: 'quantity',
                    render: window.renderTableQuantity
                },
                {
                    data: null,
                    render: (_value, type, order) => {
                        const unitPrice = order.unit_price == null || order.unit_price === ''
                            ? null
                            : Number(order.unit_price);
                        return type === 'display' || type === 'filter'
                            ? (unitPrice === null ? '—' : formatMoney(unitPrice))
                            : (unitPrice === null ? '' : unitPrice);
                    }
                },
                {
                    data: 'order_total',
                    render: formatMoney
                },
                {
                    data: 'downpayment',
                    render: formatMoney
                },
                {
                    data: 'balance',
                    render: formatMoney
                },
                {
                    data: 'delivery_method'
                },
                {
                    data: 'status',
                    render: window.renderTableStatus
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: (data) => `
                        <div class="text-center">
                            <button type="button"
                               class="btn btn-sm btn-info js-order-payments mb-1"
                               data-id="${data.order_id}"
                               title="View payment information and history">
                                <i class="fas fa-receipt"></i> Payments
                            </button>
                            ${canUpdate ? `
                            <a href="#"
                               class="text-warning js-edit-order"
                               data-id="${data.order_id}"
                               title="Edit Order">
                                <i class="fa fa-pen"></i>
                            </a>
                            ` : ''}
                        </div>
                    `
                }
            ],
            initComplete: applyOrderStatusFilter
        });
    }

    function applyOrderStatusFilter() {
        if (!ordersTable) return;

        const search = orderStatusFilter
            ? `^${$.fn.dataTable.util.escapeRegex(orderStatusFilter)}$`
            : '';
        ordersTable.column(11).search(search, true, false).draw();
    }

    function handlePayments(event) {
        event.preventDefault();
        const orderId = $(this).data('id');
        loadPaymentDetails(orderId);
    }

    function loadPaymentDetails(orderId) {
        $.ajax({
            url: `${base_url}/api/Orders/payment/${orderId}`,
            method: 'GET',
            dataType: 'json'
        })
            .done((response) => {
                if (!window.isApiSuccess(response) || !response.data) {
                    showMessage(response && response.message ? response.message : 'Unable to load payment details.');
                    return;
                }
                renderPaymentDetails(response.data);
                $(s.paymentsModal).modal('show');
            })
            .fail((xhr) => {
                handleAjaxError(xhr);
            });
    }

    function renderPaymentDetails(response) {
        const order = response.order || {};
        const summary = response.summary || {};
        const orderTotal = Number(summary.order_total || 0);
        const remaining = Number(summary.payment_remaining || 0);

        $('#order-payments-title').text(
            `${window.formatOrderNumber(order.order_id)} — ${order.product_name || ''}`
        );
        $('#order-payment-total').text(formatMoney(summary.order_total));
        $('#order-payment-downpayment').text(formatMoney(summary.downpayment));
        const taxName = order.tax_name_snapshot;
        if (taxName != null) {
            $('#order-payment-subtotal').text(formatMoney(order.subtotal_before_tax_snapshot));
            $('#order-payment-tax-label').text(
                `${taxName} (${Number(order.tax_rate_snapshot).toFixed(2)}%)`
            );
            $('#order-payment-tax').text(formatMoney(order.tax_amount_snapshot));
        } else {
            $('#order-payment-subtotal').text('Not recorded for this Order');
            $('#order-payment-tax-label').text('Tax');
            $('#order-payment-tax').text('Not recorded');
        }
        $('#order-payment-paid').text(formatMoney(summary.total_paid));
        $('#order-payment-remaining').text(
            orderTotal > 0 ? formatMoney(Math.max(remaining, 0)) : 'Order Total required'
        );
        $('#order-payment-status').text(summary.payment_status || 'Unpaid');
        $('#order-total-warning').toggleClass('d-none', orderTotal > 0);

        const approvalPanel = $('#ready-made-approval-panel');
        const approvalForm = $(s.readyMadeApprovalForm);
        const isReadyMade = order.product_type === 'Ready-Made';
        const approval = order.customer_approval || 'Pending';
        approvalPanel.toggleClass('d-none', !isReadyMade);
        $('#ready-made-approval-status').text(approval);
        $('#ready-made-approval-order-id').val(order.order_id || '');
        $('#ready-made-approval-decision').val('');
        approvalForm.toggleClass(
            'd-none',
            !response.can_approve_ready_made || !isReadyMade
                || order.status !== 'Pending' || !['Pending', ''].includes(order.customer_approval || '')
        );

        const history = $('#order-payment-history').empty();
        (response.payments || []).forEach((payment) => {
            const row = $('<tr>');
            [
                payment.reference_no,
                payment.payment_type,
                payment.receipt_date,
                formatMoney(payment.amount),
                payment.created_by_name || '—'
            ].forEach((value) => $('<td>').text(value == null ? '—' : String(value)).appendTo(row));
            row.appendTo(history);
        });
        if (!history.children().length) {
            history.append('<tr><td colspan="5" class="text-center text-muted">No payment receipts recorded.</td></tr>');
        }

        $(s.paymentOrderId).val(order.order_id || '');
        $(s.paymentAmount)
            .val('')
            .attr('max', Math.max(remaining, 0).toFixed(2));
        $(s.paymentForm).toggleClass(
            'd-none',
            !response.can_add_payment || orderTotal <= 0 || remaining <= 0 || order.status === 'Cancelled'
        );
    }

    function submitPayment(event) {
        event.preventDefault();
        const orderId = $(s.paymentOrderId).val();
        const formData = new FormData(this);
        $(s.paymentSave).prop('disabled', true).text('Saving...');

        $.ajax({
            url: `${base_url}/api/Orders/payment/${encodeURIComponent(orderId)}`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
            .done((response) => {
                if (!window.isApiSuccess(response)) {
                    showMessage(response.message || 'Unable to record payment.');
                    return;
                }
                showMessage(response.message || 'Payment recorded.');
                loadOrders();
                loadPaymentDetails(orderId);
            })
            .fail((xhr) => {
                handleAjaxError(xhr);
            })
            .always(() => {
                $(s.paymentSave).prop('disabled', false).text('Save Payment');
            });
    }

    function submitReadyMadeApproval(event) {
        event.preventDefault();
        const orderId = $('#ready-made-approval-order-id').val();
        const formData = new FormData(this);
        const button = $(this).find('[type="submit"]').prop('disabled', true);
        $.ajax({
            url: `${base_url}/api/Orders/readyMadeApproval/${encodeURIComponent(orderId)}`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done((response) => {
            showMessage(response.message || 'Customer approval updated.');
            if (!window.isApiSuccess(response)) return;
            loadOrders();
            loadPaymentDetails(orderId);
        }).fail((xhr) => {
            handleAjaxError(xhr);
        }).always(() => {
            button.prop('disabled', false);
        });
    }

    function formatMoney(value) {
        const amount = Number(value || 0);
        return `₱${amount.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;
    }

    function resetForm(e) {
        if (e) {
            e.preventDefault();
        }

        $(s.form).trigger('reset');
        $(s.id).val('');
        unitPriceMode = true;
        existingTotalPaidCents = 0;
        resetCustomerOptions();
        $(s.productType).empty()
            .append($('<option>').val('').text('Select Product Type'))
            .append($('<option>').val('Ready-Made').text('Ready-Made'))
            .append($('<option>').val('Custom-Made').text('Custom-Made'))
            .val('');
        $(s.product).val('');
        $(s.customProduct).val('');
        updateProductFields();

        $(s.orderTotal).val('');
        $(s.orderTotal).prop('readonly', true);
        $(s.unitPrice).val('').prop('disabled', false).prop('required', true).prop('readonly', false);
        $(s.unitPriceHelp).text('Agreed price for one piece, including VAT.');
        $(s.balancePreview).val('—');
        $(s.downpayment).val(0);
        $(s.downpayment).prop('readonly', false);

        $(s.status)
            .val('Pending')
            .prop('disabled', false);

        $(s.statusPreserved)
            .val('')
            .prop('disabled', true);

        $(s.statusHelp).text('New orders start as Pending.');

        $(s.saveBtn).text('Save Order');
        $(s.modalTitle).text('Add Order');
        $(s.saveBtn).prop('disabled', false);
        $(s.taxWarning).addClass('d-none').text('');
        $(s.taxPreview).addClass('d-none');
        currentTaxConfiguration = null;
    }

    function loadProducts(onComplete = function () {}) {
        const select = $(s.product);
        select.empty().append($('<option>').val('').text('Loading products...'));

        $.ajax({
            url: `${base_url}/api/Orders/products`,
            method: 'GET',
            dataType: 'json'
        }).done(function (response) {
            const products = window.apiData(response);
            select.empty().append($('<option>').val('').text('Select Product'));
            if (response && response.status !== 'error' && Array.isArray(products)) {
                products.forEach(function (product) {
                    const width = product.width_cm || '';
                    const height = product.height_cm || '';
                    const dimensions = width && height
                        ? `${width}x${height} cm`
                        : (product.size || 'Dimensions not specified');
                    select.append(
                        $('<option>')
                            .val(product.inventory_id)
                            .text(`Item ${product.inventory_id} — ${product.item_name} · ${dimensions}`)
                    );
                });
            }
            updateProductFields();
        }).fail(function () {
            select.empty().append($('<option>').val('').text('Product list unavailable'));
            updateProductFields();
        }).always(onComplete);
    }

    function updateProductFields() {
        const type = $(s.productType).val();
        const readyMade = type === 'Ready-Made';
        const customMade = type === 'Custom-Made';
        const legacy = type === 'legacy';
        $(s.inventoryProductGroup).toggleClass('d-none', !readyMade);
        $(s.product).prop('disabled', !readyMade).prop('required', readyMade);
        $(s.customProductGroup).toggleClass('d-none', !customMade);
        $(s.customProduct).prop('disabled', !customMade).prop('required', customMade);
        $(s.specifications).prop('required', customMade);
        $('#order-specifications-help').text(customMade
            ? 'Required for Custom-Made Orders. Enter one clear dimension in centimeters (for example, 90x210 cm).'
            : 'Optional for Ready-Made Orders. The selected Inventory item supplies its product name and dimensions.');
        $(s.legacyProductNote).text(legacy
            ? 'This existing Order has no saved product type or Inventory link. Its product remains unchanged unless explicitly reclassified.'
            : '');
        if (!customMade) {
            $(s.customProduct).val('');
        }
        if (!readyMade) $(s.product).val('');
    }

    function loadTaxConfiguration() {
        currentTaxConfiguration = null;
        $(s.saveBtn).prop('disabled', true);
        $(s.taxWarning).addClass('d-none').text('');
        $.ajax({
            url: `${base_url}/api/Orders/taxConfiguration`,
            method: 'GET',
            dataType: 'json'
        })
            .done(function (response) {
                if (!window.isApiSuccess(response) || !response.data) {
                    const message = response && response.message
                        ? response.message
                        : 'No valid active tax configuration is available.';
                    $(s.taxWarning).removeClass('d-none').text(message);
                    showMessage(message);
                    return;
                }
                configureTaxPreview(response.data);
                $(s.saveBtn).prop('disabled', false);
            })
            .fail(function (xhr) {
                const response = xhr && xhr.responseJSON;
                const message = response && response.message
                    ? response.message
                    : 'Tax Settings could not be loaded. An active tax configuration is required to create an Order.';
                $(s.taxWarning).removeClass('d-none').text(message);
                showMessage(message);
            });
    }

    function configureTaxPreview(configuration) {
        currentTaxConfiguration = { ...configuration };
        if (unitPriceMode) currentTaxConfiguration.calculation_mode = 'Inclusive';
        configuration = currentTaxConfiguration;
        const mode = configuration.calculation_mode;
        $(s.totalLabel).text(unitPriceMode
            ? 'Total Amount (VAT-inclusive)'
            : (mode === 'Exclusive'
                ? `Agreed Subtotal (before ${configuration.tax_name})`
                : `Agreed Order Total (${configuration.tax_name} inclusive)`));
        $(s.totalHelp).text(unitPriceMode
            ? 'Calculated as Unit Price × Pieces. VAT is included in the Unit Price.'
            : (mode === 'Exclusive'
                ? `Enter the agreed subtotal before ${configuration.tax_name}. Tax will be added to calculate the final payable Order Total.`
                : `Enter the final agreed Order Total, including ${configuration.tax_name}. The tax portion will be shown below.`));
        updateTaxPreview();
        $(s.taxPreview).removeClass('d-none');
    }

    function updateTaxPreview() {
        if (unitPriceMode) {
            const unitPriceCents = toCents($(s.unitPrice).val());
            const quantityText = String($(s.quantity).val() || '').trim();
            if (unitPriceCents === null || unitPriceCents <= 0 || !/^[1-9]\d{0,7}$/.test(quantityText)) {
                $(s.orderTotal).val('');
                $('#order-preview-subtotal, #order-preview-tax, #order-preview-total').text('Enter a valid Unit Price and whole-number quantity');
                updateBalancePreview(null);
                return;
            }
            const quantity = Number(quantityText);
            const totalCents = unitPriceCents * quantity;
            if (!Number.isSafeInteger(totalCents) || totalCents > 9999999999) {
                $(s.orderTotal).val('');
                $('#order-preview-subtotal, #order-preview-tax, #order-preview-total').text('Total exceeds the supported amount');
                updateBalancePreview(null);
                return;
            }
            $(s.orderTotal).val((totalCents / 100).toFixed(2));
            updateBalancePreview(totalCents);
            if (!currentTaxConfiguration) return;
            renderInclusiveTaxPreview(totalCents);
            return;
        }

        if (!currentTaxConfiguration) {
            updateBalancePreview(toCents($(s.orderTotal).val()));
            return;
        }
        const amount = String($(s.orderTotal).val() || '').trim();
        const match = amount.match(/^(?:0|[1-9]\d{0,7})(?:\.(\d{1,2}))?$/);
        const rateMatch = String(currentTaxConfiguration.tax_rate).match(/^(\d{1,3})(?:\.(\d{1,2}))?$/);
        if (!match || !rateMatch) {
            $('#order-preview-subtotal, #order-preview-tax, #order-preview-total').text('Enter a valid amount');
            updateBalancePreview(null);
            return;
        }
        const amountParts = amount.split('.');
        const amountCents = Number(amountParts[0]) * 100 + Number((amountParts[1] || '').padEnd(2, '0'));
        const rateParts = String(currentTaxConfiguration.tax_rate).split('.');
        const rateBps = Number(rateParts[0]) * 100 + Number((rateParts[1] || '').padEnd(2, '0'));
        if (amountCents <= 0) {
            $('#order-preview-subtotal, #order-preview-tax, #order-preview-total').text('Enter a valid amount');
            updateBalancePreview(null);
            return;
        }
        let subtotalCents;
        let taxCents;
        let totalCents;
        if (currentTaxConfiguration.calculation_mode === 'Exclusive') {
            subtotalCents = amountCents;
            taxCents = Math.floor((subtotalCents * rateBps + 5000) / 10000);
            totalCents = subtotalCents + taxCents;
        } else {
            totalCents = amountCents;
            taxCents = Math.floor((totalCents * rateBps + Math.floor((10000 + rateBps) / 2)) / (10000 + rateBps));
            subtotalCents = totalCents - taxCents;
        }
        const money = cents => formatMoney((cents / 100).toFixed(2));
        const taxLabel = `${currentTaxConfiguration.tax_name} (${Number(currentTaxConfiguration.tax_rate).toFixed(2)}%)`;
        $('#order-preview-subtotal').text(money(subtotalCents));
        $('#order-preview-tax-label').text(taxLabel);
        $('#order-preview-tax').text(money(taxCents));
        $('#order-preview-total').text(money(totalCents));
        updateBalancePreview(totalCents);
    }

    function renderInclusiveTaxPreview(grossCents) {
        const rate = String(currentTaxConfiguration.tax_rate || '');
        const rateMatch = rate.match(/^(\d{1,3})(?:\.(\d{1,2}))?$/);
        if (!rateMatch || !currentTaxConfiguration.tax_name) {
            $('#order-preview-subtotal, #order-preview-tax, #order-preview-total').text('Tax configuration is invalid');
            return;
        }
        const rateParts = rate.split('.');
        const rateBps = Number(rateParts[0]) * 100 + Number((rateParts[1] || '').padEnd(2, '0'));
        const denominator = 10000 + rateBps;
        const taxCents = Math.floor((grossCents * rateBps + Math.floor(denominator / 2)) / denominator);
        const subtotalCents = grossCents - taxCents;
        const money = cents => formatMoney((cents / 100).toFixed(2));
        $('#order-preview-subtotal').text(money(subtotalCents));
        $('#order-preview-tax-label').text(`${currentTaxConfiguration.tax_name} (${Number(rate).toFixed(2)}%)`);
        $('#order-preview-tax').text(money(taxCents));
        $('#order-preview-total').text(money(grossCents));
    }

    function toCents(value) {
        const amount = String(value == null ? '' : value).trim();
        if (!/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/.test(amount)) return null;
        const [whole, fraction = ''] = amount.split('.');
        return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    }

    function updateBalancePreview(totalCents = toCents($(s.orderTotal).val())) {
        if (totalCents === null) {
            $(s.balancePreview).val('—');
            return;
        }
        const paidCents = $(s.id).val()
            ? existingTotalPaidCents
            : (toCents($(s.downpayment).val()) || 0);
        $(s.balancePreview).val(formatMoney(((totalCents - paidCents) / 100).toFixed(2)));
    }

    function searchCustomers() {
        const query = $(s.customerSearch).val().trim();

        if (query === '' || query !== $(s.customerSearch).attr('data-selected-label')) {
            $(s.customer).val('');
            $(s.customerSearch).removeAttr('data-selected-label');
        }

        if (customerSearchTimer) {
            clearTimeout(customerSearchTimer);
        }
        if (customerSearchRequest) {
            customerSearchRequest.abort();
            customerSearchRequest = null;
        }

        if (query.length < 2) {
            if (query === '') {
                loadDefaultCustomers();
            } else {
                resetCustomerOptions();
            }
            return;
        }

        $(s.customer)
            .empty()
            .append($('<option>').val('').text('Searching customers...'));

        customerSearchTimer = setTimeout(function () {
            loadCustomers(query);
        }, 250);
    }

    function loadDefaultCustomers() {
        $(s.customer)
            .empty()
            .append($('<option>').val('').text('Loading customers...'));
        loadCustomers('');
    }

    function loadCustomers(query) {
        if (customerSearchRequest) {
            customerSearchRequest.abort();
            customerSearchRequest = null;
        }

        const orderId = $(s.id).val();
        const requestData = { q: query };
        if (orderId) {
            requestData.order_id = orderId;
        }

        const request = $.ajax({
            url: `${base_url}/api/Orders/customers`,
            method: 'GET',
            dataType: 'json',
            data: requestData
        });
        customerSearchRequest = request;

        request
            .done(function (response) {
                const customers = window.apiData(response);
                if (!response || response.status === 'error' || !Array.isArray(response.data)) {
                    return;
                }
                if ($(s.customerSearch).val().trim() !== query) {
                    return;
                }

                const select = $(s.customer);
                const emptyMessage = customers.length
                    ? 'Select Customer'
                    : query === ''
                        ? 'No eligible customers'
                        : 'No matching customers';
                select.empty().append(
                    $('<option>').val('').text(emptyMessage)
                );
                customers.forEach(function (customer) {
                    const label = customer.contact_number
                        ? `${customer.customer_name} — ${customer.contact_number}`
                        : customer.customer_name;
                    select.append(
                        $('<option>').val(customer.customer_id).text(label)
                    );
                });
            })
            .fail(function (xhr, status) {
                if (status !== 'abort') {
                    $(s.customer)
                        .empty()
                        .append($('<option>').val('').text('Customer search failed'));
                    handleAjaxError(xhr);
                }
            })
            .always(function () {
                if (customerSearchRequest === request) {
                    customerSearchRequest = null;
                }
            });
    }

    function setCustomerOption(customerId, customerName) {
        const select = $(s.customer);
        const id = String(customerId || '');
        const name = String(customerName || '');
        select.empty().append(
            $('<option>').val('').text('Select Customer')
        );
        if (id !== '') {
            select.append(
                $('<option>').val(id).text(name).prop('selected', true)
            );
        }
        $(s.customerSearch)
            .val(name)
            .attr('data-selected-label', name);
    }

    function resetCustomerOptions() {
        if (customerSearchTimer) {
            clearTimeout(customerSearchTimer);
            customerSearchTimer = null;
        }
        if (customerSearchRequest) {
            customerSearchRequest.abort();
            customerSearchRequest = null;
        }
        $(s.customer)
            .empty()
            .append($('<option>').val('').text('Search for a customer above'));
        $(s.customerSearch).removeAttr('data-selected-label');
    }

    return {
        init
    };
})();
