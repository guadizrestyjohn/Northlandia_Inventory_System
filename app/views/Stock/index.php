<?php
$transactions = $transactions ?? [];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Stock Transactions</h3>
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
            <?php if (\Core\Auth::can('stock.in')): ?><button
                type="button"
                class="btn btn-success btn-sm"
                data-toggle="modal"
                data-target="#stock-in-modal"
            >
                Stock In
            </button><?php endif; ?>
            <?php if (\Core\Auth::canStockOut()): ?>
                <button
                    type="button"
                    class="btn btn-warning btn-sm"
                    data-toggle="modal"
                    data-target="#stock-out-modal"
                >
                    Stock Out
                </button>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table id="stock-table" class="stock-table table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Transaction No.</th>
                        <th>Reference</th>
                        <th>Order No.</th>
                        <th>Job Order No.</th>
                        <th>Production Output / Release</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Remarks</th>
                        <th>Date</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/modals.php'; ?>
