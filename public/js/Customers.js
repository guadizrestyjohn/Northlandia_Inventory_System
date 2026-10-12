$(function () {
    CustomersModule.init();
});

const CustomersModule = (function () {
    const s = {
        table: '#customers-table',
        form: '#customer-form',
        modal: '#customer-modal',
        addButton: '#btn-add-customer',
        saveButton: '#customer-save',
        modalTitle: '#customer-modal-title',

        id: '#customer-id',
        name: '#customer-name',
        contact: '#customer-contact',
        email: '#customer-email',
        address: '#customer-address'
    };

    let table = null;

    function init() {
        bindEvents();
        loadCustomers();
    }

    function bindEvents() {
        $(s.addButton).on('click', showAddModal);

        $(s.table).on('click', '.js-edit-customer', handleEdit);

        $(s.form).on('submit', submitForm);

        $(s.modal).on('hidden.bs.modal', resetForm);
    }

    function showAddModal(e) {
        e.preventDefault();

        resetForm();

        $(s.modalTitle).text('Add Customer');
        $(s.saveButton).text('Save Customer');

        $(s.modal).modal('show');

        $(s.name).trigger('focus');
    }

    function handleEdit(e) {
        e.preventDefault();

        const customer = table
            .row($(this).closest('tr'))
            .data();

        if (!customer) {
            return;
        }

        resetForm();

        $(s.id).val(customer.customer_id);
        $(s.name).val(customer.customer_name || '');
        $(s.contact).val(customer.contact_number || '');
        $(s.email).val(customer.email || '');
        $(s.address).val(customer.address || '');

        $(s.modalTitle).text(
            `Edit Customer No. ${customer.customer_id}`
        );

        $(s.saveButton).text('Update Customer');

        $(s.modal).modal('show');

        $(s.name).trigger('focus');
    }

    function submitForm(e) {
        e.preventDefault();

        const id = $(s.id).val();
        const editing = Boolean(id);
        const button = $(s.saveButton);

        button.prop('disabled', true);

        $.ajax({
            url: editing
                ? `${base_url}/api/Customers/edit/${encodeURIComponent(id)}`
                : `${base_url}/api/Customers/add`,
            method: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false
        })
            .done(function (response) {
                if (!window.isApiSuccess(response)) {
                    showMessage(response && response.message ? response.message : 'Unable to save customer.');
                    return;
                }
                showMessage(
                    response.message || 'Customer saved successfully.'
                );

                $(s.modal).modal('hide');

                table.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                handleAjaxError(xhr);
            })
            .always(function () {
                button.prop('disabled', false);
            });
    }

    function loadCustomers() {
        const tableElement = $(s.table);
        const canManage =
            tableElement.attr('data-can-manage') === '1';

        const columns = [
            {
                data: 'customer_id',
                render: window.renderTableText
            },
            {
                data: 'customer_name',
                render: window.renderTableText
            },
            {
                data: 'contact_number',
                defaultContent: '',
                render: window.renderTableText
            },
            {
                data: 'email',
                defaultContent: '',
                render: window.renderTableText
            },
            {
                data: 'address',
                defaultContent: '',
                render: window.renderTableText
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
                            class="btn btn-warning btn-sm js-edit-customer">
                            Edit
                        </button>
                    `;
                }
            });
        }

        table = tableElement.DataTable({
            ajax: {
                url: `${base_url}/api/Customers/all`,
                dataSrc: window.apiData,
                error: handleAjaxError
            },

            columns: columns,

            createdRow: function (row, data) {
                $(row)
                    .find('.js-edit-customer')
                    .attr('data-id', data.customer_id);
            },

            paging: false,
            info: false
        });
    }

    function resetForm() {
        const form = $(s.form);

        if (form.length) {
            form[0].reset();
        }

        $(s.id).val('');

        $(s.modalTitle).text('Add Customer');

        $(s.saveButton).text('Save Customer');
    }

    return {
        init
    };
})();
