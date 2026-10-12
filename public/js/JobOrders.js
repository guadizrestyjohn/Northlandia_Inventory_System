$(function () {
    JobOrdersModule.init();
});

const JobOrdersModule = (function () {
    const s = {
        table: '#job-orders-table',

        addButton: '#btn-add-job-order',
        addModal: '#add-job-order-modal',
        addForm: '#add-job-order-modal form',

        manageModal: '#manage-job-order-modal',
        manageForm: '#manage-job-order-modal form',
        manageDetails: '#manage-job-order-details',
        manageNumber: '#manage-job-order-number',
        manageId: '.manage-job-order-id',

        approval: 'select[name="customer_approval"]',
        productionAssignment: 'input[name="production_assignment"]',

        timeframeForm: '#job-order-timeframe-form',
        startDate: '#job-order-start-date',
        endDate: '#job-order-end-date',

        completeButton: '#job-order-complete'
    };

    let table = null;

    function init() {
        bindEvents();
        loadJobOrders();
    }

    function bindEvents() {
        $(s.addButton).on('click', showAddModal);

        $(s.addForm).on('submit', submitAddForm);

        $(s.table).on(
            'click',
            '.js-manage-job-order',
            handleManage
        );

        $(s.timeframeForm).on(
            'submit',
            validateTimeframe
        );

        $(s.manageForm).on(
            'submit',
            submitManageForm
        );
    }

    function showAddModal(e) {
        e.preventDefault();

        resetAddForm();
        $(s.addModal).modal('show');
        loadEligibleOrders();
    }

    function resetAddForm() {
        const form = $(s.addForm);

        if (form.length) {
            form[0].reset();
        }
    }

    function loadEligibleOrders() {
        const select = $('#job-order-order-id');
        const submitButton = $(s.addForm).find('[type="submit"]');
        if (!select.length) return;

        select.prop('disabled', true).empty()
            .append($('<option>').val('').text('Loading Orders…'));
        submitButton.prop('disabled', true);

        $.ajax({
            url: `${base_url}/api/JobOrders/eligibleOrders`,
            method: 'GET',
            dataType: 'json'
        })
            .done(function (response) {
                if (response && response.status === 'error') {
                    window.apiData(response);
                    select.empty().append($('<option>').val('').text('Orders could not be loaded'));
                    return;
                }

                const orders = window.apiData(response);
                select.empty().append($('<option>').val('').text('Select Order'));
                orders.forEach(function (order) {
                    const label = [
                        window.formatOrderNumber(order.order_id),
                        order.customer_name || '',
                        order.product_name || ''
                    ].join(' — ');
                    select.append($('<option>').val(order.order_id).text(label));
                });
                if (orders.length === 0) {
                    select.find('option').first().text('No eligible Pending Orders');
                }
                select.prop('disabled', false);
                submitButton.prop('disabled', orders.length === 0);
            })
            .fail(function (xhr) {
                handleAjaxError(xhr);
                select.empty().append($('<option>').val('').text('Orders could not be loaded'));
            })
            .always(function () {
                if (select.find('option').length && select.find('option').first().text() === 'Orders could not be loaded') {
                    select.prop('disabled', true);
                }
                if (select.prop('disabled')) submitButton.prop('disabled', true);
            });
    }

    function submitAddForm(e) {
        e.preventDefault();

        const form = this;
        const selectedOrderId = String($(form).find('[name="order_id"]').val() || '');
        if (!selectedOrderId) {
            showMessage('Please select a valid order.');
            return;
        }
        const submitButton = $(form).find('[type="submit"]').prop('disabled', true);

        $.ajax({
            url: `${base_url}/api/JobOrders/add`,
            method: 'POST',
            data: new FormData(form),
            processData: false,
            contentType: false
        })
            .done(function (response) {
                if (!window.isApiSuccess(response)) {
                    showMessage(response && response.message ? response.message : 'Unable to create Job Order.');
                    return;
                }
                $('#job-order-order-id option').filter(function () {
                    return String(this.value) === selectedOrderId;
                }).remove();
                showMessage(
                    response.message || 'Job Order created.'
                );

                $(s.addModal).modal('hide');
                table.ajax.reload(null, false);
                loadEligibleOrders();
            })
            .fail(function (xhr) {
                handleAjaxError(xhr);
            })
            .always(function (response) {
                if (!window.isApiSuccess(response)) {
                    submitButton.prop('disabled', false);
                }
            });
    }

    function handleManage(e) {
        e.preventDefault();

        const job = table
            .row($(this).closest('tr'))
            .data();

        if (!job) {
            return;
        }

        fillManageModal(job);

        $(s.manageModal).modal('show');
    }

    function fillManageModal(job) {
        $(s.manageId).val(job.job_order_id);

        $(s.manageNumber).text(window.formatJobOrderNumber(job.job_order_id));

        $(s.manageDetails).html('');

        addDetail('Order No.', window.formatOrderNumber(job.order_id));
        addDetail('Customer', job.customer_name);
        addDetail('Product', job.product_name);
        addDetail('Quantity', window.formatQuantity(job.quantity));
        addDetail(
            'Specifications',
            job.specifications || 'No specifications provided'
        );
        addDetail(
            'Production Timeframe',
            `${job.production_start_date || 'Not set'} to ${job.production_end_date || 'Not set'}`
        );
        addDetail('Approval', job.customer_approval);
        addDetail('Status', job.status);
        addDetail(
            'Production Finished',
            job.production_completed_at || 'Not yet finished'
        );
        addDetail(
            'Finished By',
            job.production_completed_by_name || '—'
        );
        addDetail(
            'Released At',
            job.released_at || 'Not yet released'
        );
        addDetail(
            'Created At',
            job.created_at || '—'
        );

        $(s.manageModal)
            .find(s.approval)
            .val(job.customer_approval);

        $(s.manageModal)
            .find(s.productionAssignment)
            .val(job.production_assignment || '');

        $(s.startDate)
            .val(job.production_start_date || 'Automatic');

        $(s.endDate)
            .val(job.production_end_date || '')
            .attr(
                'min',
                job.production_start_date || ''
            );

        $(s.completeButton).prop(
            'disabled',
            job.status !== 'Processing' ||
            !job.production_end_date
        );
    }

    function addDetail(label, value) {
        const detail = $('<div class="col-md-6 mb-2">');

        $('<strong>')
            .text(`${label}: `)
            .appendTo(detail);

        detail.append(
            document.createTextNode(value || '')
        );

        detail.appendTo(s.manageDetails);
    }

    function validateTimeframe(e) {
        const startDate = $(s.startDate).val();
        const endDate = $(s.endDate).val();

        if (
            startDate &&
            startDate !== 'Automatic' &&
            endDate < startDate
        ) {
            e.preventDefault();

            showMessage(
                'Production end date cannot be earlier than the start date.'
            );
        }
    }

    function submitManageForm(e) {
        if (e.isDefaultPrevented()) {
            return;
        }

        e.preventDefault();

        const form = this;
        const action = $(form).attr('action');
        const endpoint = action.split('/').pop();
        const button = $(form)
            .find('[type="submit"]')
            .prop('disabled', true);

        $.ajax({
            url: `${base_url}/api/JobOrders/${endpoint}`,
            method: 'POST',
            data: new FormData(form),
            processData: false,
            contentType: false
        })
            .done(function (response) {
                if (!window.isApiSuccess(response)) {
                    showMessage(response && response.message ? response.message : 'Unable to update Job Order.');
                    return;
                }
                showMessage(
                    response.message || 'Job Order updated.'
                );

                table.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                handleAjaxError(xhr);
            })
            .always(function () {
                button.prop('disabled', false);
            });
    }

    function loadJobOrders() {
        const tableElement = $(s.table);

        const canManage =
            tableElement.attr('data-can-approval') === '1' ||
            tableElement.attr('data-can-endorse') === '1' ||
            tableElement.attr('data-can-timeframe') === '1' ||
            tableElement.attr('data-can-complete') === '1';

        const columns = [
            {
                data: 'job_order_id',
                render: window.renderTableJobOrderNumber
            },
            {
                data: 'order_id',
                render: window.renderTableOrderNumber
            },
            {
                data: 'customer_name',
                render: window.renderTableText
            },
            {
                data: 'product_name',
                render: window.renderTableText
            },
            {
                data: 'customer_approval',
                render: window.renderTableStatus
            },
            {
                data: 'production_assignment',
                defaultContent: 'Not assigned',
                render: window.renderTableText
            },
            {
                data: 'production_start_date',
                defaultContent: 'Not set',
                render: window.renderTableText
            },
            {
                data: 'production_end_date',
                defaultContent: 'Not set',
                render: window.renderTableText
            },
            {
                data: 'status',
                render: window.renderTableStatus
            }
        ];

        if (canManage) {
            columns.push({
                data: null,
                orderable: false,
                searchable: false,

                render: function () {
                    return `
                        <button
                            type="button"
                            class="btn btn-primary btn-sm js-manage-job-order">
                            <i class="fas fa-tasks"></i>
                            Manage
                        </button>
                    `;
                }
            });
        }

        table = tableElement.DataTable({
            ajax: {
                url: `${base_url}/api/JobOrders/all`,
                dataSrc: window.apiData,
                error: handleAjaxError
            },

            columns: columns,

            paging: false,
            info: false
        });
    }

    return {
        init
    };
})();
