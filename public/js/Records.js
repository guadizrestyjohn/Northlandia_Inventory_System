const RecordsModule = (function () {
    function init() {
        const modal = $('#record-modal');
        const form = $('#record-form');
        const tableElement = $('#records-table');
        const canManage = tableElement.attr('data-can-manage') === '1';
        const editableTypes = ['Purchase', 'Sales', 'Invoice', 'Bidding Document'];
        const creatableTypes = ['Purchase', 'Bidding Document'];
        function escapeHtml(value) {
        return $('<div>')
            .text(value == null ? '' : String(value))
            .html();
    }
        let table = null;

        function updateAddRecordButton() {
            const canAddCurrentType = canManage
                && creatableTypes.includes($('#records-type').val());
            $('#btn-add-record').toggleClass('d-none', !canAddCurrentType);
        }
    
        function textColumn(data, title, options) {
            const column = {
                data: data,
                title: title,
                defaultContent: '—'
            };

            if (options && options.render) {
                column.render = options.render;
            } else {
                column.render = data === 'status' || data === 'customer_approval'
                    ? window.renderTableStatus
                    : window.renderTableText;
            }

            return column;
        }

    
        function recordColumns(type) {
            const action = {
            data: null,
            title: 'Action',
            orderable: false,
            searchable: false,
            render: (_value, _display, row) => editableTypes.includes(row.record_type)
                && Number(row.record_id) > 0
                ? '<button type="button" class="btn btn-warning btn-sm js-edit-record">Edit</button>'
                : '<span class="text-muted">—</span>'
        };

        const detailsAction = {
            data: null,
            title: 'Action',
            orderable: false,
            searchable: false,
            render: (_value, _display, row) => {
                const view = '<button type="button" class="btn btn-info btn-sm js-view-record mr-1">View</button>';

                const edit = editableTypes.includes(row.record_type)
                    && Number(row.record_id) > 0
                    ? '<button type="button" class="btn btn-warning btn-sm js-edit-record">Edit</button>'
                    : '';

                return view + edit;
            }
        };

        const invoiceDetailsAction = {
            data: null,
            title: 'Tax Summary',
            orderable: false,
            searchable: false,
            render: (_value, _display, row) => {
                const view = '<button type="button" class="btn btn-info btn-sm js-view-record mr-1">View</button>';
                const edit = canManage && editableTypes.includes(row.record_type)
                    && Number(row.record_id) > 0
                    ? '<button type="button" class="btn btn-warning btn-sm js-edit-record">Edit</button>'
                    : '';
                return view + edit;
            }
        };

        const withAction = columns =>
            canManage
                ? [...columns, action]
                : columns;

        const orderLink = data => ({
            data,
            title: 'Order No.',
            defaultContent: '—',

            render: (value, type) => {
                if (value == null || value === '') {
                    return '—';
                }
                if (type === 'display') {
                    return `<a href="${base_url}/Orders">${escapeHtml(window.formatOrderNumber(value))}</a>`;
                }
                return type === 'filter'
                    ? window.formatOrderNumber(value)
                    : String(value);
            }
        });
    
            switch (type) {
                case 'Purchase':
                    return withAction([
                        textColumn('reference_no', 'Purchase No.'),
                        textColumn('supplier_name', 'Supplier'),
                        textColumn('record_date', 'Purchase Date'),
                        textColumn('description', 'Items / Details'),
                        textColumn('amount', 'Amount'),
                        textColumn('status', 'Status')
                    ]);
                case 'Sales':
                    return [
                        textColumn('reference_no', 'Sales No.'),
                        textColumn('customer_name', 'Customer'),
                        textColumn('product_name', 'Product'),
                        textColumn('quantity', 'Quantity', { render: window.renderTableQuantity }),
                        orderLink('order_id'),
                        textColumn('record_date', 'Sales Date'),
                        textColumn('amount', 'Amount', { render: value => value == null ? 'Not recorded' : `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` }),
                        textColumn('status', 'Status'),
                        detailsAction
                    ];
                case 'Receipt':
                    return [
                        textColumn('reference_no', 'Receipt No.'),
                        orderLink('order_id'),
                        textColumn('customer_name', 'Customer'),
                        textColumn('product_name', 'Product'),
                        textColumn('payment_type', 'Payment Type'),
                        textColumn('record_date', 'Receipt Date'),
                        textColumn('amount', 'Amount', {
                            render: value => value == null
                                ? 'Not recorded'
                                : `₱${Number(value).toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                })}`
                        }),
                        textColumn('description', 'Details'),
                        textColumn('status', 'Status'),
                        textColumn('created_by_name', 'Created By'),
                        detailsAction
                    ];
                case 'Invoice':
                    return [
                        textColumn('reference_no', 'Invoice No.'),
                        orderLink('order_id'),
                        textColumn('customer_name', 'Customer'),
                        textColumn('record_date', 'Invoice Date'),
                        textColumn('amount', 'Amount'),
                        textColumn('status', 'Status'),
                        invoiceDetailsAction
                    ];
                case 'Bidding Document':
                    return withAction([
                        textColumn('reference_no', 'Reference No.'),
                        textColumn('title', 'Bid Title'),
                        textColumn('client_name', 'Client'),
                        textColumn('record_date', 'Submission Date'),
                        textColumn('description', 'Details'),
                        textColumn('status', 'Status')
                    ]);
                case 'Order':
                    return [
                        textColumn('reference_no', 'Order No.'),
                        textColumn('customer_name', 'Customer'),
                        textColumn('record_date', 'Order Date'),
                        textColumn('product_name', 'Product'),
                        textColumn('quantity', 'Quantity', { render: window.renderTableQuantity }),
                        textColumn('downpayment', 'Downpayment'),
                        textColumn('delivery_method', 'Delivery'),
                        textColumn('status', 'Status')
                    ];
                case 'Job Order':
                    return [
                        textColumn('reference_no', 'Job Order No.'),
                        orderLink('order_id'),
                        textColumn('customer_name', 'Customer'),
                        textColumn('product_name', 'Product'),
                        textColumn('customer_approval', 'Approval'),
                        textColumn('production_assignment', 'Assignment'),
                        textColumn('production_start_date', 'Production Start'),
                        textColumn('production_end_date', 'Production End'),
                        textColumn('status', 'Status'),
                        detailsAction
                    ];
                case 'Stock In':
                case 'Stock Out':
                    return [
                        textColumn('reference_no', 'Reference No.'),
                        orderLink('order_id'),
                        textColumn('item_name', 'Item'),
                        textColumn('quantity', 'Quantity', { render: window.renderTableQuantity }),
                        textColumn('description', 'Details'),
                        textColumn('record_date', 'Transaction Date'),
                        textColumn('created_by_name', 'Created By')
                    ];
                default: {
                    const columns = [
                        textColumn('record_type', 'Type'),
                        textColumn('reference_no', 'Reference'),
                        textColumn('record_date', 'Date'),
                        textColumn('description', 'Details'),
                        {
                            data: null,
                            title: 'Customer / Order',
                            render: row => `${escapeHtml(row.customer_name || '')}`
                                + `${row.customer_name && row.order_id ? ' · ' : ''}`
                                + `${row.order_id ? `<a href="${base_url}/Orders">${escapeHtml(window.formatOrderNumber(row.order_id))}</a>` : (!row.customer_name ? '—' : '')}`
                        },
                        textColumn('amount', 'Amount / Downpayment'),
                        textColumn('status', 'Status'),
                        textColumn('created_by_name', 'Created By')
                    ];
                    return withAction(columns);
                }
            }
        }
    
        function buildTable() {
            const type = $('#records-type').val();
            const columns = recordColumns(type);
    
            if (table && $.fn.DataTable.isDataTable(tableElement)) {
                table.destroy();
            }
            const header = tableElement.find('thead tr').empty();

            columns.forEach(function (column) {
                header.append(
                    $('<th>').text(column.title || '')
                );
            });
            tableElement.find('tbody').empty();
    
            table = tableElement.DataTable({
                ajax: {
                    url: `${base_url}/api/Records/all`,
                    cache: false,
                    data: request => {
                        request.keyword = $('#records-keyword').val();
                        request.type = type;
                        request.start_date = $('#records-start-date').val();
                        request.end_date = $('#records-end-date').val();
                        request.status = $('#records-status').val();
                    },
                    dataSrc: function (json) {
                        return window.apiData(json);
                    },
                    error: handleAjaxError
                },
                columns,
                paging: false,
                info: false
            });
        }
    
        function updateConditionalFields() {
            const type = $('#record-type').val();
            const purchase = type === 'Purchase';
            const bidding = type === 'Bidding Document';
            const needsOrder = ['Sales', 'Invoice'].includes(type);
            const needsAmount = ['Purchase', 'Sales', 'Invoice'].includes(type);
    
            $('#record-supplier-group').toggle(purchase);
            $('#record-title-group, #record-client-group').toggle(bidding);
            $('#record-order-group').toggle(needsOrder);
            $('#record-amount-group').toggle(needsAmount);
            $('#record-items-group').toggle(purchase);
            $('#record-description-group').toggle(!purchase);
    
            $('#record-order').prop('required', needsOrder);
            $('#record-amount').prop('required', needsAmount);
            $('#record-items-details').prop('required', purchase);
            $('#record-title').prop('required', bidding);
    
            $('#record-order option').each(function () {
                const isPlaceholder = this.value === '';
                const isCompleted = $(this).attr('data-status') === 'Completed';
                $(this).prop('disabled', needsOrder && !isPlaceholder && !isCompleted);
            });
        }
    
        function setRecordForm(record = null) {
            const isEditing = Boolean(record && record.record_id);
            form[0].reset();
            $('#record-type option').each(function () {
                const canCreateType = creatableTypes.includes(this.value);
                $(this)
                    .prop('disabled', !isEditing && !canCreateType)
                    .toggle(isEditing || canCreateType);
            });
            $('#record-id').val(record ? record.record_id : '');
            $('#record-type').val(record ? record.record_type : 'Purchase').prop('disabled', isEditing);
            $('#record-type-locked').prop('disabled', !isEditing).val(isEditing ? record.record_type : '');
            $('#record-reference').val(record ? record.reference_no || '' : '');
            const now = new Date();
            const localDate = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
            $('#record-date').val(record ? record.record_date || '' : localDate);
            $('#record-supplier').val(record ? record.supplier_name || '' : '');
            $('#record-title').val(record ? record.title || '' : '');
            $('#record-client').val(record ? record.client_name || '' : '');
            $('#record-order').val(record && record.order_id ? String(record.order_id) : '');
            $('#record-amount').val(record && record.amount != null ? record.amount : '');
            $('#record-status-input').val(record ? record.status || 'Recorded' : 'Recorded');
            $('#record-items-details').val(record ? record.description || '' : '');
            $('#record-description').val(record ? record.description || '' : '');
            form.attr('action', isEditing
                ? `${base_url}/api/Records/edit/${encodeURIComponent(record.record_id)}`
                : `${base_url}/api/Records/add`);
            $('#record-modal-title').text(
                isEditing
                    ? `Edit ${record.record_type} No. ${record.reference_no}`
                    : 'Add Record'
            );
            $('#record-save').text(isEditing ? 'Update Record' : 'Save Record');
            updateConditionalFields();
        }
    
        function bindEvents() {
    $('#records-filter-form').on('submit', function (event) {
        event.preventDefault();
        buildTable();
    });

    $('#btn-add-record').on('click', function () {
        setRecordForm();
        modal.modal('show');
    });

    $('#records-type').on('change', function () {
        updateAddRecordButton();
        buildTable();
    });

    $('#record-type').on('change', updateConditionalFields);

    tableElement.on('click', '.js-edit-record', function () {
        const record = table.row($(this).closest('tr')).data();

        if (!record || !editableTypes.includes(record.record_type)) {
            return;
        }

        setRecordForm(record);
        modal.modal('show');
    });

    tableElement.on('click', '.js-view-record', function () {
        const record = table.row($(this).closest('tr')).data();

        if (!record) {
            return;
        }

        const rows = record.record_type === 'Invoice'
            ? (() => {
                const descriptionParts = String(record.description || '').split(' | ');
                const taxRows = descriptionParts.length > 1
                    ? descriptionParts.slice(1).map(part => {
                        const separator = part.lastIndexOf(': PHP ');
                        return separator < 0
                            ? null
                            : [part.slice(0, separator), part.slice(separator + 2)];
                    })
                    : [];
                const hasTaxSummary = taxRows.length > 0 && taxRows.every(Boolean);
                const invoiceRows = [
                    ['Invoice No.', record.reference_no],
                    ['Order No.', record.order_id ? window.formatOrderNumber(record.order_id) : '—'],
                    ['Customer', record.customer_name],
                    ['Product', record.product_name],
                    ['Invoice Date', record.record_date],
                    ['Amount', record.amount == null ? 'Not recorded' : `₱${Number(record.amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`]
                ];

                if (hasTaxSummary) {
                    invoiceRows.push(['Invoice Details', descriptionParts[0]], ...taxRows);
                } else if (record.description) {
                    invoiceRows.push(['Tax Details', record.description]);
                }

                invoiceRows.push(
                    ['Status', record.status],
                    ['Created By', record.created_by_name],
                    ['Created At', record.created_at]
                );
                return invoiceRows;
            })()
            : record.record_type === 'Receipt'
            ? [
                ['Receipt No.', record.reference_no],
                ['Order No.', record.order_id ? window.formatOrderNumber(record.order_id) : '—'],
                ['Customer', record.customer_name],
                ['Product', record.product_name],
                ['Payment Type', record.payment_type],
                ['Description', record.description],
                ['Receipt Date', record.record_date],
                ['Amount', record.amount == null ? 'Not recorded' : `₱${Number(record.amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`],
                ['Status', record.status],
                ['Created By', record.created_by_name],
                ['Created At', record.created_at]
            ]
            : record.record_type === 'Sales'
            ? [
                ['Sales No.', record.reference_no],
                ['Customer', record.customer_name],
                ['Product', record.product_name],
                ['Quantity', window.formatQuantity(record.quantity)],
                ['Order No.', record.order_id ? window.formatOrderNumber(record.order_id) : '—'],
                ['Specifications / Customization', record.specifications || 'None provided'],
                ['Sales Date', record.record_date],
                ['Amount', record.amount == null ? 'Not recorded' : `₱${Number(record.amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`],
                ['Status', record.status],
                ['Created At', record.created_at]
            ]
            : record.record_type === 'Job Order'
                ? [
                    ['Job Order No.', record.reference_no],
                    ['Order No.', record.order_id ? window.formatOrderNumber(record.order_id) : '—'],
                    ['Customer', record.customer_name],
                    ['Product', record.product_name],
                    ['Quantity', window.formatQuantity(record.quantity)],
                    ['Specifications / Customization', record.specifications || 'None provided'],
                    ['Customer Approval', record.customer_approval],
                    ['Production Assignment', record.production_assignment],
                    ['Production Start', record.production_start_date],
                    ['Production End', record.production_end_date],
                    ['Production Status', record.status],
                    ['Production Finished At', record.production_completed_at || 'Not recorded'],
                    ['Released At', record.released_at || 'Not released'],
                    ['Created At', record.created_at]
                ]
                : [
                    ['Record', record.reference_no],
                    ['Details', record.description],
                    ['Date', record.record_date],
                    ['Status', record.status]
                ];

        const body = $('#record-details-body').empty();

        rows.forEach(function (row) {
            const finalTotal = record.record_type === 'Invoice'
                && /^Final total$/i.test(String(row[0] || ''));
            const $row = $('<tr>');
            const $label = $('<th scope="row">').text(row[0] || 'Invoice');
            const $value = $('<td>').text(
                row[1] == null || row[1] === ''
                    ? '—'
                    : String(row[1])
            );

            if (record.record_type === 'Invoice') {
                $label.addClass('records-invoice-label');
            }
            if (record.record_type === 'Invoice' && row[1] && /^PHP\s/.test(String(row[1]))) {
                $label.addClass('records-invoice-amount-label');
                $value.addClass('records-invoice-amount');
            }
            if (finalTotal) {
                $row.addClass('records-invoice-final-total');
            }

            $row.append($label, $value).appendTo(body);
        });

        $('#record-details-title').text(
            `${record.record_type} Details`
        );

        $('#record-details-modal').modal('show');
    });

    form.on('submit', function (event) {
        event.preventDefault();

        const id = $('#record-id').val();

        const endpoint = id
            ? `${base_url}/api/Records/edit/${encodeURIComponent(id)}`
            : `${base_url}/api/Records/add`;

        $.ajax({
            url: endpoint,
            method: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false
        })
            .done(function (response) {
                if (!window.isApiSuccess(response)) {
                    showMessage(response && response.message ? response.message : 'Unable to save record.');
                    return;
                }
                showMessage(
                    response.message ||
                    'Record saved successfully.'
                );

                modal.modal('hide');
                buildTable();
            })
            .fail(function (xhr) {
                handleAjaxError(xhr);
            });
    });
}

buildTable();
bindEvents();
updateAddRecordButton();
updateConditionalFields();
    }

    return { init };
})();

$(function () {
    RecordsModule.init();
});
