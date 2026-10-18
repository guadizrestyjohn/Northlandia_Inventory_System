$(function () {
    UsersModule.init();
});

const UsersModule = (function () {
    const s = {
        table: '#users-table',
        modal: '#user-modal',
        form: '#user-form',

        addButton: '#btn-add-user',
        saveButton: '#user-save',
        modalTitle: '#user-modal-title',

        id: '#user-id',
        username: '#user-username',
        fullName: '#user-full-name',
        password: '#user-password',
        passwordHelp: '#user-password-help',
        role: '#user-role',
        productionTeam: '#user-production-team',
        productionTeamGroup: '#user-production-team-group',
        status: '#user-status'
    };

    let table = null;

    function init() {
        bindEvents();
        loadUsers();
    }

    function bindEvents() {
        $(s.addButton).on(
            'click',
            showAddModal
        );

        $(s.table).on(
            'click',
            '.js-edit-user',
            handleEdit
        );

        $(s.table).on(
            'click',
            '.js-toggle-user-status',
            handleStatus
        );

        $(s.form).on(
            'submit',
            submitForm
        );

        $(s.role).on('change', toggleProductionTeam);
    }

    function showAddModal(e) {
        e.preventDefault();

        setForm();

        $(s.modal).modal('show');
    }

    function handleEdit(e) {
        e.preventDefault();

        const user = table
            .row($(this).closest('tr'))
            .data();

        if (!user) {
            return;
        }

        setForm(user);

        $(s.modal).modal('show');
    }

    function handleStatus(e) {
        e.preventDefault();

        const user = table
            .row($(this).closest('tr'))
            .data();

        if (!user) {
            return;
        }

        const nextStatus =
            user.status === 'Active'
                ? 'Inactive'
                : 'Active';

        const action =
            nextStatus === 'Inactive'
                ? 'deactivate'
                : 'activate';

        if (
            !window.confirm(
                `Are you sure you want to ${action} ${user.username}?`
            )
        ) {
            return;
        }

        $.ajax({
            url: `${base_url}/api/Users/status/${encodeURIComponent(user.user_id)}`,
            method: 'POST',
            data: {
                status: nextStatus
            }
        })
            .done(function (response) {
                if (!window.isApiSuccess(response)) {
                    showMessage(response && response.message ? response.message : 'Unable to update user status.');
                    return;
                }
                showMessage(
                    response.message ||
                    'User status updated.'
                );

                if (response.signed_out) {
                    window.location.href =
                        `${base_url}/Auth`;

                    return;
                }

                table.ajax.reload(
                    null,
                    false
                );
            })
            .fail(function (xhr) {
                handleAjaxError(xhr);
            });
    }

    function setForm(user) {
        const editing = Boolean(user);

        $(s.form)[0].reset();

        $(s.id).val(
            editing
                ? user.user_id
                : ''
        );

        $(s.username).val(
            editing
                ? user.username
                : ''
        );

        $(s.fullName).val(
            editing
                ? user.full_name
                : ''
        );

        $(s.password)
            .val('')
            .prop(
                'required',
                !editing
            );

        if (editing) {
            $(s.passwordHelp).text(
                'Leave blank to keep the current password.'
            );
        } else {
            $(s.passwordHelp).text(
                'Use at least 8 characters.'
            );
        }

        $(s.role).val(
            editing
                ? String(user.role_id)
                : ''
        );

        $(s.productionTeam).val(editing ? (user.production_team || '') : '');
        toggleProductionTeam();

        $(s.status).val(
            editing
                ? user.status
                : 'Active'
        );

        if (editing) {
            $(s.modalTitle).text(
                `Edit User #${user.user_id}`
            );

            $(s.saveButton).text(
                'Save Changes'
            );
        } else {
            $(s.modalTitle).text(
                'Add User'
            );

            $(s.saveButton).text(
                'Create User'
            );
        }
    }

    function toggleProductionTeam() {
        const isProductionStaff = $(s.role).find('option:selected').text().trim() === 'Production Staff';
        $(s.productionTeamGroup).prop('hidden', !isProductionStaff);
        $(s.productionTeam).prop('disabled', !isProductionStaff);
        if (!isProductionStaff) $(s.productionTeam).val('');
    }

    function submitForm(e) {
        e.preventDefault();

        const id = $(s.id).val();
        const button = $(s.saveButton);

        button.prop(
            'disabled',
            true
        );

        let url =
            `${base_url}/api/Users/add`;

        if (id) {
            url =
                `${base_url}/api/Users/edit/${encodeURIComponent(id)}`;
        }

        $.ajax({
            url: url,
            method: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false
        })
            .done(function (response) {
                if (!window.isApiSuccess(response)) {
                    showMessage(response && response.message ? response.message : 'Unable to save user.');
                    return;
                }
                showMessage(
                    response.message ||
                    'User saved successfully.'
                );

                $(s.modal).modal('hide');

                if (response.signed_out) {
                    window.location.href =
                        `${base_url}/Auth`;

                    return;
                }

                table.ajax.reload(
                    null,
                    false
                );
            })
            .fail(function (xhr) {
                handleAjaxError(xhr);
            })
            .always(function () {
                button.prop(
                    'disabled',
                    false
                );
            });
    }

    function loadUsers() {
        table = $(s.table).DataTable({
            ajax: {
                url: `${base_url}/api/Users/all`,
                dataSrc: window.apiData,
                cache: false,
                error: handleAjaxError
            },

            columns: [
                {
                    data: 'user_id',
                    render: window.renderTableText
                },
                {
                    data: 'username',
                    render: window.renderTableText
                },
                {
                    data: 'full_name',
                    render: window.renderTableText
                },
                {
                    data: 'role_name',
                    render: window.renderTableText
                },
                {
                    data: 'production_team',
                    defaultContent: '—',
                    render: function (value, type, user) {
                        const hasTeam = value !== null && value !== undefined && String(value).trim() !== '';
                        const displayValue = hasTeam
                            ? value
                            : (user.role_name === 'Production Staff' ? 'Production Staff' : '—');
                        return window.renderTableText(displayValue, type);
                    }
                },
                {
                    data: 'status',
                    render: window.renderTableText
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,

                    render: function (
                        value,
                        type,
                        user
                    ) {
                        const canManageAllUserRoles = $(s.table).attr('data-can-manage-all-user-roles') === '1';
                        const protectedRoles = ['Administrator', 'Manager'];
                        if (!canManageAllUserRoles && protectedRoles.includes(user.role_name)) {
                            return '—';
                        }

                        let statusAction =
                            'Activate';

                        let style =
                            'success';

                        if (
                            user.status === 'Active'
                        ) {
                            statusAction =
                                'Deactivate';

                            style =
                                'danger';
                        }

                        return `
                            <button
                                type="button"
                                class="btn btn-warning btn-sm js-edit-user mr-1">
                                Edit
                            </button>

                            <button
                                type="button"
                                class="btn btn-${style} btn-sm js-toggle-user-status">
                                ${statusAction}
                            </button>
                        `;
                    }
                }
            ],

            paging: false,
            info: false
        });
    }

    return {
        init
    };
})();
