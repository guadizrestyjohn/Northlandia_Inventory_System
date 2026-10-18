<?php

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

?>

            <div class="card">

                <div class="card-header">
                    <h3 class="card-title">Customer List</h3>
                    <div class="card-tools">
                        <?php if ($canManageCustomers ?? false): ?><button type="button" class="btn btn-primary btn-sm" id="btn-add-customer">
                            <i class="fas fa-plus"></i> Add Customer
                        </button><?php endif; ?>
                    </div>
                </div>

                <div class="card-body">

                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success" role="alert">
                            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table id="customers-table" class="table table-bordered table-hover" data-can-manage="<?= ($canManageCustomers ?? false) ? '1' : '0' ?>">

                            <thead>
                                <tr>
                                    <th>Customer No.</th>
                                    <th>Customer Name</th>
                                    <th>Contact Number</th>
                                    <th>Email</th>
                                    <th>Address</th>
                                    <?php if ($canManageCustomers ?? false): ?><th>Action</th><?php endif; ?>
                                </tr>
                            </thead>

                            <tbody></tbody>

                        </table>
                    </div>

                </div>

            </div>

<?php if ($canManageCustomers ?? false): ?><div class="modal fade" id="customer-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form
            class="modal-content"
            id="customer-form"
            method="post"
            action="<?= BASE_URL ?>/api/Customers/add"
        >
            <div class="modal-header">
                <h5 class="modal-title" id="customer-modal-title">Add Customer</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="customer-id">
                <div class="form-group">
                    <label for="customer-name">Customer Name</label>
                    <input class="form-control" id="customer-name" name="customer_name" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label for="customer-contact">Contact Number</label>
                    <input type="number" class="form-control" id="customer-contact" name="contact_number">
                </div>
                <div class="form-group">
                    <label for="customer-email">Email</label>
                    <input class="form-control" type="email" id="customer-email" name="email">
                </div>
                <div class="form-group">
                    <label for="customer-address">Address</label>
                    <textarea class="form-control" id="customer-address" name="address" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="customer-save">Save Customer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
