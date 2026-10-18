$(function () {
    ReportsModule.init();
});

const ReportsModule = (function () {
    const s = {
        form: '#reports-filter-form',
        type: '#reports-type',
        status: '#reports-status',
        statusLabel: '#reports-status-label',
        table: '#reports-table',
        run: '#reports-run',
        reset: '#reports-reset'
    };

    const reportColumns = {
        inventory: [
            { data: 'inventory_id', title: 'Item Number', render: (id, type) => window.renderTableText(`Item ${id || ''}`, type) },
            { data: 'item_name', title: 'Item Name' },
            { data: 'item_description', title: 'Item Description' },
            { data: null, title: 'Size', render: (_value, type, row) => window.renderTableText(row.width_cm && row.height_cm ? `${window.formatQuantity(row.width_cm)}x${window.formatQuantity(row.height_cm)} cm` : (row.size || '—'), type) },
            { data: 'category_name', title: 'Category' },
            { data: 'total_stocks', title: 'Total Stocks', render: window.renderTableQuantity },
            { data: 'quantity', title: 'Stocks', render: window.renderTableQuantity },
            { data: 'unit', title: 'Unit' },
            { data: 'status', title: 'Status', render: window.renderTableStatus }
        ],
        movements: [
            { data: 'transaction_id', title: 'Transaction No.', render: window.renderTableTransactionNumber },
            { data: 'item_name', title: 'Item' },
            { data: 'transaction_type', title: 'Movement Type', render: window.renderTableStatus },
            { data: 'quantity', title: 'Quantity', render: window.renderTableQuantity },
            { data: 'reference_no', title: 'Reference' },
            { data: 'order_id', title: 'Order No.', render: window.renderTableOrderNumber },
            { data: 'job_order_id', title: 'Job Order No.', render: window.renderTableJobOrderNumber },
            { data: null, title: 'Production Output / Release', render: (_value, type, row) => window.renderTableText(row.production_output_id ? `Output ${row.production_output_id} · Release ${row.release_id || '—'}` : '—', type) },
            {
                data: null,
                title: 'User',
                render: (_value, type, row) => window.renderTableText(row.full_name || row.username || '', type)
            },
            { data: 'transaction_date', title: 'Date' },
            { data: 'remarks', title: 'Remarks' }
        ],
        orders: [
            { data: 'order_id', title: 'Order No.', render: window.renderTableOrderNumber },
            { data: 'customer_name', title: 'Customer' },
            { data: 'order_date', title: 'Order Date' },
            { data: 'product_name', title: 'Product' },
            { data: 'quantity', title: 'Quantity', render: window.renderTableQuantity },
            { data: 'order_total', title: 'Order Total', render: formatMoney },
            { data: 'downpayment', title: 'Downpayment', render: formatMoney },
            { data: 'total_paid', title: 'Total Paid', render: formatMoney },
            { data: 'payment_remaining', title: 'Payment Remaining', render: formatMoney },
            { data: 'delivery_method', title: 'Delivery' },
            { data: 'order_status', title: 'Order Status', render: window.renderTableStatus },
            { data: 'job_order_id', title: 'Job Order No.', render: window.renderTableJobOrderNumber },
            { data: 'production_assignment', title: 'Production Assignment' },
            { data: 'production_start_date', title: 'Production Start' },
            { data: 'production_end_date', title: 'Production End' },
            { data: 'customer_approval', title: 'Customer Approval', render: window.renderTableStatus },
            { data: 'job_order_status', title: 'Job Order Status', render: window.renderTableStatus }
        ]
    };

    let table = null;
    let activeType = null;

    function init() {
        $(s.form).on('submit', function (event) {
            event.preventDefault();
            loadReport();
        });
        $(s.type).on('change', function () {
            updateFilters();
            loadReport();
        });
        $(s.reset).on('click', function () {
            window.setTimeout(function () {
                updateFilters();
                loadReport();
            }, 0);
        });

        updateFilters();
        loadReport();
    }

    function updateFilters() {
        const type = $(s.type).val();
        const visibleFilters = {
            inventory: ['keyword', 'category', 'status'],
            movements: ['keyword', 'status', 'start-date', 'end-date'],
            orders: ['keyword', 'customer', 'status', 'start-date', 'end-date']
        }[type];

        $('[data-report-filter]').each(function () {
            const filter = $(this).attr('data-report-filter');
            $(this).toggle(visibleFilters.includes(filter));
        });

        const statusOptions = {
            inventory: {
                label: 'Inventory Status',
                values: ['Available', 'Low Stock', 'Out of Stock']
            },
            movements: {
                label: 'Movement Type',
                values: ['Stock In', 'Stock Out']
            },
            orders: {
                label: 'Order or Job Order Status',
                values: [
                    'Pending', 'Processing', 'Ready for Release', 'Ready for Stock Out',
                    'Completed', 'Released', 'Cancelled'
                ]
            }
        }[type];

        $(s.statusLabel).text(statusOptions.label);
        const status = $(s.status);
        status.empty().append($('<option>').val('').text('All Statuses'));
        statusOptions.values.forEach(function (value) {
            status.append($('<option>').val(value).text(value));
        });
    }

    function loadReport() {
        const type = $(s.type).val();
        const columns = reportColumns[type];

        if (table && activeType === type) {
            table.ajax.reload();
            return;
        }

        if (table && $.fn.DataTable.isDataTable(s.table)) {
            table.destroy();
            $(s.table).find('thead tr').empty();
            $(s.table).find('tbody').empty();
        }

        table = $(s.table).DataTable({
            ajax: {
                url: `${base_url}/api/Reports/all`,
                type: 'GET',
                data: function (request) {
                    $(s.form).serializeArray().forEach(function (field) {
                        request[field.name] = field.value;
                    });
                },
                dataSrc: function (response) {
                    return window.apiData(response);
                },
                error: window.handleAjaxError
            },
            columns: columns.map(function (column) {
                return Object.assign({
                    defaultContent: '—',
                    render: column.render || window.renderTableText
                }, column);
            }),
                           autoWidth: false,
                responsive: false,
                scrollX: true,
                paging: false,
                info: false,
                order: [],
                language: {
                emptyTable: 'No report results match the selected filters.',
                zeroRecords: 'No report results match the selected filters.'
            }
        });
        activeType = type;
    }

    function formatMoney(value, type) {
        if (type !== 'display') {
            return value == null ? '' : String(value);
        }
        const amount = Number(value);
        if (!Number.isFinite(amount)) {
            return window.renderTableText(value, type);
        }
        return `₱${amount.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;
    }

    return { init };
})();
