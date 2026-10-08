<?php
$roles = $roles ?? [];
$statuses = $statuses ?? ['Active', 'Inactive'];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">User Management</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" id="btn-add-user">
                <i class="fas fa-user-plus"></i> Add User
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php foreach (['success' => $success, 'danger' => $error] as $type => $message): ?>
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= $type ?>" role="alert">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="table-responsive">
            <table id="users-table" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="user-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" id="user-form" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="user-modal-title">Add User</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="user-id" name="user_id">
                <div class="form-group">
                    <label for="user-username">Username</label>
                    <input class="form-control" id="user-username" name="username" maxlength="50" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label for="user-full-name">Full Name</label>
                    <input class="form-control" id="user-full-name" name="full_name" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label for="user-password">Password</label>
                    <input class="form-control" type="password" id="user-password" name="password" minlength="8" maxlength="255" autocomplete="new-password">
                    <small class="form-text text-muted" id="user-password-help">Use at least 8 characters.</small>
                </div>
                <div class="form-group">
                    <label for="user-role">Role</label>
                    <select class="form-control" id="user-role" name="role_id" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role->role_id ?>">
                                <?= htmlspecialchars((string) $role->role_name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="user-status">Status</label>
                    <select class="form-control" id="user-status" name="status" required>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="user-save">Create User</button>
            </div>
        </form>
    </div>
</div>
