const RecordsModule = (function () {
    function init() {
        const modal = $('#record-modal');
        const form = $('#record-form');
        const tableElement = $('#records-table');
        const canManage = tableElement.attr('data-can-manage') === '1';
        const canDeleteBidding = tableElement.attr('data-can-delete-bidding') === '1';
        const canDeletePurchase = tableElement.attr('data-can-delete-purchase') === '1';
        const editableTypes = ['Purchase', 'Bidding Document'];
        const creatableTypes = ['Purchase', 'Bidding Document'];
        const generalRecordStatuses = [
            'Recorded', 'Pending', 'Approved', 'Rejected', 'Processing',
            'Completed', 'Cancelled', 'Ready for Stock Out', 'Ready for Release', 'Released'
        ];
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

        const manualRecordAction = {
            data: null,
            title: 'Actions',
            orderable: false,
            searchable: false,
            render: (_value, _display, row) => {
                const view = '<button type="button" class="btn btn-info btn-sm js-view-record mr-1">View</button>';
                if (!canManage || !['Purchase', 'Bidding Document'].includes(row.record_type)) {
                    return view;
                }
                const canDelete = row.record_type === 'Purchase'
                    ? canDeletePurchase
                    : canDeleteBidding;
                return view
                    + '<button type="button" class="btn btn-warning btn-sm js-edit-record mr-1">Edit</button>'
                    + (canDelete
                        ? '<button type="button" class="btn btn-danger btn-sm js-delete-record">Delete</button>'
                        : '');
            }
        };

        const detailsAction = {
            data: null,
            title: 'Action',
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
                    return [
                        textColumn('reference_no', 'Purchase No.'),
                        textColumn('supplier_name', 'Supplier'),
                        textColumn('record_date', 'Purchase Date'),
                        textColumn('description', 'Items / Details'),
                        textColumn('quantity', 'Quantity', { render: window.renderTableQuantity }),
                        textColumn('amount', 'Amount'),
                        textColumn('record_remarks', 'Remarks'),
                        textColumn('status', 'Status'),
                        manualRecordAction
                    ];
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
                    return [
                        textColumn('reference_no', 'Reference No.'),
                        textColumn('title', 'Document Title'),
                        textColumn('document_type', 'Document Type'),
                        textColumn('client_name', 'Client'),
                        textColumn('record_date', 'Document Date'),
                        textColumn('description', 'Details'),
                        textColumn('record_remarks', 'Remarks'),
                        {
                            data: null,
                            title: 'Attachment',
                            orderable: false,
                            searchable: false,
                            render: (_value, _display, row) => row.attachment_name
                                ? `<a href="${base_url}/api/Records/download/${encodeURIComponent(row.record_id)}" class="btn btn-outline-primary btn-sm">Download</a>`
                                : 'No attachment'
                        },
                        textColumn('status', 'Status'),
                        manualRecordAction
                    ];
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
                case 'Photo Documentation':
                    return [
                        textColumn('reference_no', 'Photo No.'),
                        textColumn('record_date', 'Date'),
                        textColumn('attachment_name', 'File Name'),
                        textColumn('created_by_name', 'Uploaded By'),
                        {
                            data: null,
                            title: 'Photo',
                            orderable: false,
                            searchable: false,
                            render: (_value, _display, row) =>
                                `<button type="button" class="btn btn-outline-primary btn-sm js-view-photo" data-photo-id="${encodeURIComponent(row.record_id)}">View Photo</button>`
                        }
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
                paging: true,
                pageLength: 10,
                lengthChange: false,
                info: false
            });
        }
    
        function updateConditionalFields() {
            const type = $('#record-type').val();
            const purchase = type === 'Purchase';
            const bidding = type === 'Bidding Document';
            const needsOrder = ['Sales', 'Invoice'].includes(type);
            const needsAmount = ['Purchase', 'Sales', 'Invoice'].includes(type);
            const statusSelect = $('#record-status-input');
            const currentStatus = statusSelect.attr('data-stored-status')
                || statusSelect.val()
                || 'Recorded';
            const statusOptions = bidding ? [currentStatus] : generalRecordStatuses;

            $('#record-reference-group').toggle(!bidding);
            $('#record-reference').prop('disabled', bidding).prop('required', !bidding);

            statusSelect.empty();
            statusOptions.forEach(function (status) {
                statusSelect.append($('<option>').val(status).text(status));
            });
            statusSelect.val(statusOptions.includes(currentStatus) ? currentStatus : 'Recorded');
            statusSelect.prop('disabled', purchase || bidding).prop('required', !purchase && !bidding);

            $('#record-date-label').text(purchase ? 'Purchase Date' : (bidding ? 'Document Date' : 'Record Date'));
    
            $('#record-supplier-group').toggle(purchase);
            $('#record-title-group, #record-client-group, #record-document-type-group, #record-attachment-group').toggle(bidding);
            $('#record-order-group').toggle(needsOrder);
            $('#record-amount-group').toggle(needsAmount);
            $('#record-quantity-group').toggle(purchase);
            $('#record-remarks-group').toggle(purchase || bidding);
            $('#record-items-group').toggle(purchase);
            $('#record-description-group').toggle(!purchase);
            $('#record-status-group').toggle(!purchase && !bidding);
            $('#record-purchase-status-group').toggle(purchase);
            $('#record-supplier').prop('required', purchase);
            $('#record-order').prop('required', needsOrder);
            $('#record-amount').prop('required', needsAmount);
            $('#record-items-details').prop('required', purchase);
            $('#record-quantity').prop('required', purchase);
            $('#record-title').prop('required', bidding);
            $('#record-document-type').prop('required', bidding);
    
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
            const selectedType = record
                ? record.record_type
                : (creatableTypes.includes($('#records-type').val()) ? $('#records-type').val() : 'Purchase');
            $('#record-type').val(selectedType).prop('disabled', isEditing);
            $('#record-type-locked').prop('disabled', !isEditing).val(isEditing ? record.record_type : '');
            $('#record-reference').val(record ? record.reference_no || '' : '');
            const now = new Date();
            const localDate = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
            $('#record-date').val(record ? record.record_date || '' : localDate);
            $('#record-supplier').val(record ? record.supplier_name || '' : '');
            $('#record-title').val(record ? record.title || '' : '');
            $('#record-title-group label').text(selectedType === 'Bidding Document' ? 'Document Title' : 'Bid Title');
            $('#record-document-type').val(record ? record.document_type || '' : '');
            $('#record-client').val(record ? record.client_name || '' : '');
            $('#record-order').val(record && record.order_id ? String(record.order_id) : '');
            $('#record-amount').val(record && record.amount != null ? record.amount : '');
            $('#record-status-input')
                .attr('data-stored-status', record ? record.status || 'Recorded' : 'Recorded')
                .val(record ? record.status || 'Recorded' : 'Recorded');
            $('#record-purchase-status').val(record ? record.status || 'Recorded' : 'Recorded');
            $('#record-items-details').val(record ? record.description || '' : '');
            $('#record-description').val(record ? record.description || '' : '');
            $('#record-quantity').val(record && record.quantity != null ? record.quantity : '');
            $('#record-remarks').val(record ? record.record_remarks || '' : '');
            $('#record-attachment').val('');
            const attachmentHelp = $('#record-attachment-current').empty();
            if (record && record.record_type === 'Bidding Document' && record.attachment_name) {
                const downloadUrl = `${base_url}/api/Records/download/${encodeURIComponent(record.record_id)}`;
                const viewUrl = `${downloadUrl}?view=1`;
                attachmentHelp.append(document.createTextNode(`${record.attachment_name} — `));
                if (/\.pdf$/i.test(record.attachment_name)) {
                    attachmentHelp.append($('<a target="_blank" rel="noopener">').attr('href', viewUrl).text('View PDF'));
                    attachmentHelp.append(document.createTextNode(' · '));
                }
                attachmentHelp.append($('<a>').attr('href', downloadUrl).text('Download current file'));
                attachmentHelp.append(document.createTextNode('. Select a new file to replace it.'));
            } else {
                attachmentHelp.text('Optional. PDF, DOC, DOCX, XLS, or XLSX; maximum 10 MB. Leave empty while editing to keep the current attachment.');
            }
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

        function refreshReferencePreview(type) {
            if (type !== 'Purchase' || $('#record-id').val()) {
                return;
            }

            const referenceInput = $('#record-reference');
            referenceInput.val('').attr('placeholder', 'Generating reference number...');
            $.ajax({
                url: `${base_url}/api/Records/nextReference/${encodeURIComponent(type)}`,
                method: 'GET'
            }).done(function (response) {
                if (window.isApiSuccess(response) && $('#record-type').val() === type && !$('#record-id').val()) {
                    referenceInput.val(response.reference_no || '');
                    referenceInput.attr('placeholder', 'Generated automatically when saved');
                }
            }).fail(function () {
                referenceInput.attr('placeholder', 'Generated automatically when saved');
            });
        }
    
        function bindEvents() {
    $('#records-filter-form').on('submit', function (event) {
        event.preventDefault();
        buildTable();
    });

    $('#btn-add-record').on('click', function () {
        setRecordForm();
        modal.modal('show');
        refreshReferencePreview($('#record-type').val());
    });

    $('#records-type').on('change', function () {
        updateAddRecordButton();
        buildTable();
    });

    $('#record-type').on('change', function () {
        updateConditionalFields();
        refreshReferencePreview($(this).val());
    });

    tableElement.on('click', '.js-edit-record', function () {
        const record = table.row($(this).closest('tr')).data();

        if (!record || !editableTypes.includes(record.record_type)) {
            return;
        }

        setRecordForm(record);
        modal.modal('show');
    });

    tableElement.on('click', '.js-delete-record', function () {
        const record = table.row($(this).closest('tr')).data();
        const allowed = record && (
            (record.record_type === 'Purchase' && canDeletePurchase)
            || (record.record_type === 'Bidding Document' && canDeleteBidding)
        );
        if (!allowed) {
            return;
        }
        if (!window.confirm(`Delete ${record.record_type} ${record.reference_no}? This cannot be undone.`)) {
            return;
        }

        $.ajax({
            url: `${base_url}/api/Records/delete/${encodeURIComponent(record.record_id)}`,
            method: 'POST',
            data: { record_type: record.record_type }
        })
            .done(function (response) {
                if (!window.isApiSuccess(response)) {
                    showMessage(response && response.message ? response.message : 'Unable to delete record.');
                    return;
                }
                showMessage(response.message || 'Record deleted successfully.');
                buildTable();
            })
            .fail(handleAjaxError);
    });

    tableElement.on('click', '.js-view-photo', async function (event) {
        event.preventDefault();

        const photoId = $(this).data('photoId');
        const record = table.row($(this).closest('tr')).data() || {};
        const remarks = String(record.record_remarks || '').trim();
        const photoUrl = `${base_url}/api/Records/photo/${encodeURIComponent(photoId)}`;
        const $button = $(this).prop('disabled', true).text('Loading...');
        const $modal = $('#record-details-modal');
        const $body = $('#record-details-body').empty();

        $('#record-details-title').text(`Photo Documentation ${photoId}`);
        $body.append($('<tr>').append(
            $('<td colspan="2" class="text-center text-muted">').text('Loading photo...')
        ));
        $modal.modal('show');

        try {
            const response = await fetch(photoUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'image/*' }
            });
            const contentType = response.headers.get('content-type') || '';

            if (!response.ok || !contentType.toLowerCase().startsWith('image/')) {
                const responseText = await response.text();
                let message = 'The photo could not be loaded. Refresh the page and try again.';
                try {
                    const data = JSON.parse(responseText);
                    if (data.message) message = data.message;
                } catch (_error) {
                    // Keep the readable fallback for non-JSON server errors.
                }
                throw new Error(message);
            }

            const photoBlob = await response.blob();
            const image = document.createElement('img');
            image.alt = 'Photo documentation';
            image.style.maxWidth = '100%';
            image.style.maxHeight = '70vh';
            image.style.objectFit = 'contain';

            const reader = new FileReader();
            const photoDataUrl = await new Promise((resolve, reject) => {
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject(new Error('The photo data could not be read.'));
                reader.readAsDataURL(photoBlob);
            });
            image.src = String(photoDataUrl);
            await image.decode();

            $body.empty()
                .append($('<tr>').append(
                    $('<td colspan="2" class="text-center">').append(image)
                ))
                .append($('<tr>').append(
                    $('<td colspan="2" class="pt-3">').append(
                        $('<strong>').text('Remarks: '),
                        $('<span>').text(remarks || 'No remarks provided.')
                    )
                ));
        } catch (error) {
            $body.empty().append($('<tr>').append(
                $('<td colspan="2">').append(
                    $('<div class="alert alert-danger mb-0" role="alert">')
                        .text(error.message || 'The photo could not be loaded.')
                )
            ));
        } finally {
            $button.prop('disabled', false).text('View Photo');
        }
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
            : record.record_type === 'Purchase'
            ? [
                ['Purchase No.', record.reference_no],
                ['Supplier', record.supplier_name],
                ['Purchase Date', record.record_date],
                ['Items / Purchase Details', record.description],
                ['Quantity', window.formatQuantity(record.quantity)],
                ['Amount', record.amount == null ? 'Not recorded' : `₱${Number(record.amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`],
                ['Status', record.status],
                ['Remarks', record.record_remarks],
                ['Created By', record.created_by_name],
                ['Created At', record.created_at]
            ]
            : record.record_type === 'Bidding Document'
            ? [
                ['Reference No.', record.reference_no],
                ['Document Title', record.title],
                ['Document Type', record.document_type],
                ['Client', record.client_name],
                ['Document Date', record.record_date],
                ['Description', record.description],
                ['Remarks', record.record_remarks],
                ['Attachment', record.attachment_name || 'No attachment'],
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
                    ['Production Team', record.assigned_team],
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

            if (record.record_type === 'Bidding Document' && row[0] === 'Attachment' && record.attachment_name) {
                const downloadUrl = `${base_url}/api/Records/download/${encodeURIComponent(record.record_id)}`;
                $value.empty();
                if (/\.pdf$/i.test(record.attachment_name)) {
                    $value.append($('<a target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mr-1">')
                        .attr('href', `${downloadUrl}?view=1`).text('View PDF'));
                }
                $value.append($('<a class="btn btn-primary btn-sm">').attr('href', downloadUrl).text('Download'));
            }

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
                    `${response.message || 'Record saved successfully.'}${response.reference_no ? ` Reference No.: ${response.reference_no}` : ''}`
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
