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
        newBtn: '#btn-add-order',
        saveBtn: '#order-save',
        modalTitle: '#order-modal-title',

        id: '#order-id',
        customer: '#order-customer',
        customerSearch: '#order-customer-search',
        date: '#order-date',
        product: '#order-product',
        quantity: '#order-quantity',
        specifications: '#order-specifications',
        orderTotal: '#order-total',
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
        $(s.modal).on('hidden.bs.modal', resetForm);
        $(s.customerSearch).on('input', searchCustomers);
        $(s.orderTotal).on('input', updateTaxPreview);
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

        loadDefaultCustomers();
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

                fillOrder(res);

                $(s.modalTitle).text('Edit Order');
                $(s.saveBtn).text('Update Order');
                $(s.modal).modal('show');
            })
            .fail((xhr) => {
                handleAjaxError(xhr);
            });
    }

    function fillOrder(order) {
        $(s.id).val(order.order_id);
        setCustomerOption(order.customer_id, order.customer_name);
        $(s.date).val(order.order_date || '');
        $(s.product).val(order.product_name || '');
        $(s.quantity).val(window.formatQuantity(order.quantity));
        $(s.specifications).val(order.specifications || '');
        const hasTaxSnapshot = order.tax_name_snapshot != null
            && order.tax_rate_snapshot != null
            && order.tax_amount_snapshot != null
            && order.subtotal_before_tax_snapshot != null
            && order.tax_calculation_mode_snapshot != null;
        if (hasTaxSnapshot) {
            configureTaxPreview({
                tax_name: order.tax_name_snapshot,
                tax_rate: String(order.tax_rate_snapshot),
                calculation_mode: order.tax_calculation_mode_snapshot
            });
            $(s.orderTotal).val(order.tax_calculation_mode_snapshot === 'Exclusive'
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
        $(s.downpayment).val(order.downpayment || 0);
        $(s.downpayment).prop('readonly', true);
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

        $(s.table).DataTable({
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
                    data: 'specifications'
                },
                {
                    data: 'order_total',
                    render: formatMoney
                },
                {
                    data: null,
                    render: (_value, type, order) => window.renderTableText(
                        order.tax_name_snapshot == null
                            ? '—'
                            : `${order.tax_name_snapshot} ${Number(order.tax_rate_snapshot).toFixed(2)}%: ${formatMoney(order.tax_amount_snapshot)}`,
                        type
                    )
                },
                {
                    data: 'downpayment',
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
            ]
        });
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
        resetCustomerOptions();

        $(s.orderTotal).val('');
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
        currentTaxConfiguration = configuration;
        const mode = configuration.calculation_mode;
        $(s.totalLabel).text(mode === 'Exclusive'
            ? `Agreed Subtotal (before ${configuration.tax_name})`
            : `Agreed Order Total (${configuration.tax_name} inclusive)`);
        $(s.totalHelp).text(mode === 'Exclusive'
            ? `Enter the agreed subtotal before ${configuration.tax_name}. Tax will be added to calculate the final payable Order Total.`
            : `Enter the final agreed Order Total, including ${configuration.tax_name}. The tax portion will be shown below.`);
        updateTaxPreview();
        $(s.taxPreview).removeClass('d-none');
    }

    function updateTaxPreview() {
        if (!currentTaxConfiguration) return;
        const amount = String($(s.orderTotal).val() || '').trim();
        const match = amount.match(/^(?:0|[1-9]\d{0,7})(?:\.(\d{1,2}))?$/);
        const rateMatch = String(currentTaxConfiguration.tax_rate).match(/^(\d{1,3})(?:\.(\d{1,2}))?$/);
        if (!match || !rateMatch) {
            $('#order-preview-subtotal, #order-preview-tax, #order-preview-total').text('Enter a valid amount');
            return;
        }
        const amountParts = amount.split('.');
        const amountCents = Number(amountParts[0]) * 100 + Number((amountParts[1] || '').padEnd(2, '0'));
        const rateParts = String(currentTaxConfiguration.tax_rate).split('.');
        const rateBps = Number(rateParts[0]) * 100 + Number((rateParts[1] || '').padEnd(2, '0'));
        if (amountCents <= 0) {
            $('#order-preview-subtotal, #order-preview-tax, #order-preview-total').text('Enter a valid amount');
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
