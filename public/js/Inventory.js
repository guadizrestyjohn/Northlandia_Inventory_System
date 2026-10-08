$(function () {
    InventoryModule.init();
});

const InventoryModule = (function () {
    const s = {
        table: '#inventory-table',
        form: '#inventory-form',
        modal: '#inventory-modal',

        addButton: '#btn-add-inventory',
        saveButton: '#inventory-save',
        modalTitle: '#inventory-modal-title',

        id: '#inventory-id',
        itemName: '#inventory-item-name',
        size: '#inventory-size',
        category: '#inventory-category',
        quantity: '#inventory-quantity',
        unit: '#inventory-unit',

        quantityGroup: '#inventory-quantity-group',
        quantityHelp: '#inventory-quantity-help'
    };

    let table = null;

    function init() {
        bindEvents();
        loadInventory();
    }

    function bindEvents() {
        $(s.addButton).on('click', showAddModal);

        $(s.table).on(
            'click',
            '.js-edit-inventory',
            handleEdit
        );

        $(s.form).on('submit', submitForm);

        $(s.modal).on(
            'hidden.bs.modal',
            resetForm
        );

        $(document).on(
            'northlandia:stock-transaction-saved',
            function () {
                reloadInventory();
            }
        );
    }

    function showAddModal(e) {
        e.preventDefault();

        resetForm();

        $(s.modalTitle).text('Add Inventory');
        $(s.saveButton).text('Save Inventory');

        $(s.quantityGroup).addClass('d-none');
        $(s.quantity)
            .val('')
            .prop('readonly', false);

        $(s.quantityHelp).text(
            'New items start at zero stock. Record received stock through Stock In.'
        );

        $(s.modal).modal('show');

        $(s.itemName).trigger('focus');
    }

    function handleEdit(e) {
        e.preventDefault();

        const item = table
            .row($(this).closest('tr'))
            .data();

        if (!item) {
            return;
        }

        resetForm();

        fillItem(item);

        $(s.id).val(item.inventory_id);

        $(s.modalTitle).text(
            `Edit Inventory No. ${item.inventory_id}`
        );

        $(s.saveButton).text('Update Inventory');

        $(s.quantity)
            .prop('readonly', true)
            .val(window.formatQuantity(item.quantity));

        $(s.quantityGroup).removeClass('d-none');

        $(s.quantityHelp).text(
            'Use Stock In or Stock Out to change inventory quantity.'
        );

        $(s.modal).modal('show');

        $(s.itemName).trigger('focus');
    }

    function fillItem(item) {
        $(s.itemName).val(
            item.item_name || ''
        );

        $(s.size).val(
            item.size || ''
        );

        $(s.category).val(
            String(item.category_id || '')
        );

        $(s.quantity).val(
            window.formatQuantity(item.quantity)
        );

        $(s.unit).val(
            item.unit || ''
        );
    }

    function submitForm(e) {
        e.preventDefault();

        const id = $(s.id).val();
        const button = $(s.saveButton);

        button.prop('disabled', true);

        $.ajax({
            url: id
                ? `${base_url}/api/Inventory/edit/${encodeURIComponent(id)}`
                : `${base_url}/api/Inventory/add`,

            method: 'POST',

            data: new FormData(this),

            processData: false,
            contentType: false
        })
            .done(function (response) {
                showMessage(
                    response.message ||
                    'Inventory saved successfully.'
                );

                $(s.modal).modal('hide');

                reloadInventory();
            })
            .fail(function (xhr) {
                handleAjaxError(xhr);
            })
            .always(function () {
                button.prop('disabled', false);
            });
    }

    function loadInventory() {
        const tableElement = $(s.table);

        if (!tableElement.length) {
            return;
        }

        const canManage =
            tableElement.attr('data-can-manage') === '1';

        const columns = [
            {
                data: 'inventory_id',
                defaultContent: '',
                render: window.renderTableText
            },
            {
                data: 'item_name',
                defaultContent: '',
                render: window.renderTableText
            },
            {
                data: 'size',
                defaultContent: '',
                render: window.renderTableText
            },
            {
                data: 'category_name',
                defaultContent: '',
                render: window.renderTableText
            },
            {
                data: 'quantity',
                defaultContent: '',
                render: window.renderTableQuantity
            },
            {
                data: 'unit',
                defaultContent: '',
                render: window.renderTableText
            },
            {
                data: 'status',
                defaultContent: '',
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
                            class="btn btn-warning btn-sm js-edit-inventory">
                            Edit
                        </button>
                    `;
                }
            });
        } else {
            $(s.modal).remove();
        }

        table = tableElement.DataTable({
            ajax: {
                url: `${base_url}/api/Inventory/all`,
                dataSrc: 'data',
                cache: false,
                error: handleAjaxError
            },

            columns: columns,

            paging: false,
            info: false
        });
    }

    function reloadInventory() {
        if (!table) {
            return;
        }

        table.ajax.reload(null, false);
    }

    function resetForm() {
        const form = $(s.form);

        if (form.length) {
            form[0].reset();
        }

        $(s.id).val('');

        $(s.modalTitle).text('Add Inventory');
        $(s.saveButton).text('Save Inventory');

        $(s.quantity)
            .val('')
            .prop('readonly', false);

        $(s.quantityGroup).addClass('d-none');

        $(s.quantityHelp).text(
            'New items start at zero stock. Record received stock through Stock In.'
        );
    }

    return {
        init
    };
})();