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
        assignedTeam: 'select[name="assigned_team"]',

        timeframeForm: '#job-order-timeframe-form',
        startDate: '#job-order-start-date',
        endDate: '#job-order-end-date',

        completeButton: '#job-order-complete'
    };

    let table = null;
    let eligibleOrders = [];

    function init() {
        bindEvents();
        loadJobOrders();
    }

    function bindEvents() {
        $(s.addButton).on('click', showAddModal);

        $(s.addForm).on('submit', submitAddForm);
        $('#job-order-order-search').on('input', renderEligibleOrders);
        $('#job-order-status-filter').on('change', function () {
            if (!table) return;

            const status = String(this.value || '');
            const search = status ? `^${$.fn.dataTable.util.escapeRegex(status)}$` : '';
            table.column(8).search(search, true, false).draw();
        });

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
        eligibleOrders = [];
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

                eligibleOrders = window.apiData(response);
                renderEligibleOrders();
                select.prop('disabled', false);
                submitButton.prop('disabled', eligibleOrders.length === 0);
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
        loadProductionOutputs(job.job_order_id);
    }

    function fillManageModal(job) {
        $(s.manageId).val(job.job_order_id);
        $('#production-output-form [name="submission_key"]').val(newRequestKey());

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
        addDetail('Responsible Worker / Trade / Contractor', job.production_assignment || 'Not assigned');
        addDetail('Production Team', job.assigned_team || 'Production Staff');
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

        const approvalSelect = $(s.manageModal)
            .find(s.approval)
            .val(job.customer_approval);
        const approvalLocked = job.status !== 'Pending' || job.customer_approval !== 'Pending';
        approvalSelect.prop('disabled', approvalLocked);
        approvalSelect.closest('form').find('[type="submit"]').prop('disabled', approvalLocked);

        $(s.manageModal)
            .find(s.productionAssignment)
            .val(job.production_assignment || '');

        $(s.manageModal)
            .find(s.assignedTeam)
            .val(job.assigned_team || 'Production Staff');

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

                if (endpoint === 'reportOutput') {
                    const reportForm = $(form);
                    const jobOrderId = reportForm.find('[name="job_order_id"]').val();
                    reportForm[0].reset();
                    reportForm.find('[name="job_order_id"]').val(jobOrderId);
                    reportForm.find('[name="submission_key"]').val(newRequestKey());
                    loadProductionOutputs(reportForm.find('[name="job_order_id"]').val());
                }

                table.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                handleAjaxError(xhr);
            })
            .always(function () {
                button.prop('disabled', false);
            });
    }

    function loadProductionOutputs(jobOrderId) {
        const target = $('#job-order-production-outputs');
        if (!target.length) return;

        target.text('Loading production output…');
        $.ajax({
            url: `${base_url}/api/JobOrders/outputs/${encodeURIComponent(jobOrderId)}`,
            method: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response && response.status === 'error') {
                target.text(response.message || 'Production output could not be loaded.');
                return;
            }
            const outputs = window.apiData(response);
            if (!outputs.length) {
                target.text('No production output has been reported for this Job Order.');
                return;
            }

            const tableElement = $('<table class="table table-sm table-bordered mb-0">');
            tableElement.append('<thead><tr><th>Actual Product</th><th>Dimensions</th><th>Reported</th><th>Received</th><th>Remaining</th><th>Verification</th></tr></thead>');
            const body = $('<tbody>');
            outputs.forEach(function (output) {
                const row = $('<tr>');
                const values = [
                    output.product_name || '',
                    `${output.width_cm} x ${output.height_cm} cm`,
                    window.formatQuantity(output.reported_quantity),
                    window.formatQuantity(output.received_quantity),
                    window.formatQuantity(output.remaining_quantity),
                    output.verification_status || ''
                ];
                values.forEach(value => $('<td>').text(value).appendTo(row));
                body.append(row);
            });
            tableElement.append(body);
            target.empty().append($('<div class="table-responsive">').append(tableElement));
        }).fail(function (xhr) {
            target.text(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Production output could not be loaded.');
        });
    }

    function renderEligibleOrders() {
        const select = $('#job-order-order-id');
        if (!select.length) return;

        const query = String($('#job-order-order-search').val() || '').trim().toLocaleLowerCase();
        const filteredOrders = eligibleOrders.filter(function (order) {
            const searchableText = [
                window.formatOrderNumber(order.order_id),
                order.customer_name || '',
                order.product_name || ''
            ].join(' ').toLocaleLowerCase();
            return searchableText.includes(query);
        });

        select.empty().append($('<option>').val('').text('Select Order'));
        filteredOrders.forEach(function (order) {
            const label = [
                window.formatOrderNumber(order.order_id),
                order.customer_name || '',
                order.product_name || ''
            ].join(' — ');
            select.append($('<option>').val(order.order_id).text(label));
        });
        if (eligibleOrders.length === 0) {
            select.find('option').first().text('No eligible Pending Orders');
        } else if (filteredOrders.length === 0) {
            select.find('option').first().text('No matching Orders');
        }
    }

    function newRequestKey() {
        if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
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
                data: null,
                render: function (job, type, row) {
                    const worker = row.production_assignment || 'Not assigned';
                    const team = row.assigned_team || 'Production Staff';
                    return window.renderTableText(`${worker} — ${team}`, type);
                }
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
