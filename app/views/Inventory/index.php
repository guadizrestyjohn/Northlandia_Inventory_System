<?php
$items = $items ?? [];
$categories = $categories ?? [];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Inventory Items</h3>
        <div class="card-tools">
            <?php if ($canManageInventory ?? false): ?><button type="button" class="btn btn-primary btn-sm" id="btn-add-inventory">
                <i class="fas fa-plus"></i> Add Inventory
            </button><?php endif; ?>
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

        <div class="mb-3">
            <?php if (\Core\Auth::can('stock.in')): ?>
            <button
                type="button"
                class="btn btn-outline-success btn-sm"
                data-toggle="modal"
                data-target="#stock-in-modal"
            >
                Stock In
            </button>
            <?php endif; ?>
            <?php if (\Core\Auth::canStockOut()): ?>
                <button
                    type="button"
                    class="btn btn-outline-warning btn-sm"
                    data-toggle="modal"
                    data-target="#stock-out-modal"
                >
                    Stock Out
                </button>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/Stock">
                Transaction History
            </a>
        </div>

        <div class="table-responsive">
            <table id="inventory-table" class="table table-bordered table-hover" data-can-manage="<?= ($canManageInventory ?? false) ? '1' : '0' ?>">
                <thead>
                    <tr>
                        <th>Inventory No.</th>
                        <th>Item</th>
                        <th>Size</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <?php if ($canManageInventory ?? false): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canManageInventory ?? false): ?><div class="modal fade" id="inventory-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form
            class="modal-content"
            id="inventory-form"
            method="post"
            action="<?= BASE_URL ?>/api/Inventory/add"
        >
            <div class="modal-header">
                <h5 class="modal-title" id="inventory-modal-title">Add Inventory</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="inventory-id">

                <div class="form-group">
                    <label for="inventory-item-name">Item Name</label>
                    <input class="form-control" id="inventory-item-name" name="item_name" required>
                </div>

                <div class="form-group">
                    <label for="inventory-size">Size</label>
                    <input class="form-control" id="inventory-size" name="size">
                </div>

                <div class="form-group">
                    <label for="inventory-category">Category</label>
                    <select class="form-control" id="inventory-category" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category->category_id ?>">
                                <?= htmlspecialchars((string) $category->category_name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group d-none" id="inventory-quantity-group">
                    <label for="inventory-quantity">Current Quantity</label>
                    <input class="form-control" id="inventory-quantity" type="number" readonly>
                </div>

                <div class="form-group">
                    <label for="inventory-unit">Unit</label>
                    <input class="form-control" id="inventory-unit" name="unit" required>
                </div>

                <small class="form-text text-muted" id="inventory-quantity-help">
                    New items start at zero stock. Record received stock through Stock In.
                </small>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="inventory-save">
                    Save Inventory
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/Stock/modals.php'; ?>
