<?php
$orders = $orders ?? [];
$filters = $filters ?? [];
$canManageRecords = $canManageRecords ?? false;
$canAddRecord = $canManageRecords && in_array(
    $filters['type'] ?? '',
    ['Purchase', 'Bidding Document'],
    true
);
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? ($recordsLoadError ?? '');
unset($_SESSION['success'], $_SESSION['error']);
$documentTypes = ['Purchase', 'Sales', 'Invoice', 'Bidding Document'];
$recordTypes = [
    'Purchase' => 'Purchase Records',
    'Sales' => 'Sales Records',
    'Order' => 'Order Records',
    'Receipt' => 'Receipt Records',
    'Invoice' => 'Invoice Records',
    'Bidding Document' => 'Bidding Documents',
    'Stock In' => 'Stock In History',
    'Stock Out' => 'Stock Out History',
    'Job Order' => 'Job Order History',
    'Photo Documentation' => 'Photo Documentation',
];
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Records Management</h3>
        <?php if ($canManageRecords): ?>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm <?= $canAddRecord ? '' : 'd-none' ?>" id="btn-add-record">
                    <i class="fas fa-plus"></i> Add Record
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <p class="text-muted">
            Search purchase, sales, order, receipt/invoice, bidding, stock, and Job Order history.
            Orders, sales, stock movements, and Job Orders are retrieved from their existing records.
        </p>

        <?php foreach (['success' => $success, 'danger' => $error] as $type => $message): ?>
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= $type ?>" role="alert">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <form method="get" action="<?= BASE_URL ?>/Records" class="mb-4" id="records-filter-form">
            <div class="form-row align-items-end">
                <div class="form-group col-lg-3 col-md-6">
                    <label for="records-keyword">Keyword or reference</label>
                    <input
                        class="form-control"
                        id="records-keyword"
                        name="keyword"
                        value="<?= htmlspecialchars((string) ($filters['keyword'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Reference, customer, product..."
                    >
                </div>

                <div class="form-group col-lg-2 col-md-6">
                    <label for="records-type">Record type</label>
                    <select class="form-control" id="records-type" name="type">
                        <option value="">All records / history</option>
                        <?php foreach ($recordTypes as $type => $label): ?>
                            <option
                                value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-lg-2 col-md-6">
                    <label for="records-start-date">From date</label>
                    <input
                        class="form-control"
                        id="records-start-date"
                        name="start_date"
                        type="date"
                        value="<?= htmlspecialchars((string) ($filters['start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="form-group col-lg-2 col-md-6">
                    <label for="records-end-date">To date</label>
                    <input
                        class="form-control"
                        id="records-end-date"
                        name="end_date"
                        type="date"
                        value="<?= htmlspecialchars((string) ($filters['end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="form-group col-lg-2 col-md-6">
                    <label for="records-status">Status</label>
                    <select class="form-control" id="records-status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ([
                            'Recorded', 'Pending', 'Approved', 'Rejected', 'Processing',
                            'Completed', 'Cancelled', 'Ready for Stock Out', 'Ready for Release', 'Released', 'Stock In', 'Stock Out',
                        ] as $status): ?>
                            <option
                                value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-lg-1 col-md-6">
                    <button class="btn btn-secondary btn-block" type="submit">Search</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table
                id="records-table"
                class="table table-bordered table-hover"
                data-can-manage="<?= $canManageRecords ? '1' : '0' ?>"
                data-can-delete-bidding="<?= ($canDeleteBiddingDocuments ?? false) ? '1' : '0' ?>"
                data-can-delete-purchase="<?= ($canDeletePurchaseRecords ?? false) ? '1' : '0' ?>"
            >
                <thead><tr></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="record-details-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="record-details-title">Record Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <tbody id="record-details-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canManageRecords): ?>
    <div class="modal fade" id="record-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form
                class="modal-content"
                id="record-form"
                method="post"
                enctype="multipart/form-data"
                action="<?= BASE_URL ?>/api/Records/add"
            >
                <div class="modal-header">
                    <h5 class="modal-title" id="record-modal-title">Add Record</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="record-id" name="record_id">

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="record-type">Record Type</label>
                            <select class="form-control" id="record-type" name="record_type" required>
                                <?php foreach ($documentTypes as $type): ?>
                                    <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" id="record-type-locked" name="record_type" disabled>
                        </div>

                        <div class="form-group col-md-6" id="record-reference-group">
                            <label for="record-reference">Reference Number</label>
                            <input
                                class="form-control"
                                id="record-reference"
                                name="reference_no"
                                maxlength="50"
                                placeholder="Generated automatically when saved"
                                readonly
                                required
                            >
                        </div>

                        <div class="form-group col-md-6" id="record-date-group">
                            <label for="record-date" id="record-date-label">Record Date</label>
                            <input class="form-control" id="record-date" name="record_date" type="date" required>
                        </div>

                        <div class="form-group col-md-6" id="record-supplier-group">
                            <label for="record-supplier">Supplier</label>
                            <input class="form-control" id="record-supplier" name="supplier_name" maxlength="150">
                        </div>

                        <div class="form-group col-md-6" id="record-title-group">
                            <label for="record-title">Bid Title</label>
                            <input class="form-control" id="record-title" name="title" maxlength="150">
                        </div>

                        <div class="form-group col-md-6" id="record-document-type-group">
                            <label for="record-document-type">Document Type</label>
                            <input class="form-control" id="record-document-type" name="document_type" maxlength="80">
                        </div>

                        <div class="form-group col-md-6" id="record-client-group">
                            <label for="record-client">Client</label>
                            <input class="form-control" id="record-client" name="client_name" maxlength="150">
                        </div>

                        <div class="form-group col-md-6" id="record-order-group">
                            <label for="record-order">Completed Order</label>
                            <select class="form-control" id="record-order" name="order_id">
                                <option value="">Select an order</option>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $orderId = (string) $order->order_id;
                                    $orderNumber = ctype_digit($orderId)
                                        ? 'ORD-' . str_pad($orderId, 4, '0', STR_PAD_LEFT)
                                        : '';
                                    ?>
                                    <option
                                        value="<?= (int) $order->order_id ?>"
                                        data-status="<?= htmlspecialchars((string) $order->status, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?> —
                                        <?= htmlspecialchars(
                                            (string) $order->customer_name .
                                            ' · ' .
                                            (string) $order->product_name .
                                            ' · ' .
                                            (string) $order->status,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group col-md-6" id="record-amount-group">
                            <label for="record-amount">Amount</label>
                            <input
                                class="form-control"
                                id="record-amount"
                                name="amount"
                                type="number"
                                min="0"
                                step="0.01"
                            >
                        </div>

                        <div class="form-group col-md-6" id="record-quantity-group">
                            <label for="record-quantity">Purchase Quantity</label>
                            <input class="form-control" id="record-quantity" name="quantity" type="number" min="1" step="1">
                        </div>

                        <div class="form-group col-md-6" id="record-status-group">
                            <label for="record-status-input">Status</label>
                            <select class="form-control" id="record-status-input" name="status" required>
                                <option value="Recorded">Recorded</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="record-items-group">
                        <label for="record-items-details">Items / Purchase Details</label>
                        <textarea
                            class="form-control"
                            id="record-items-details"
                            name="items_details"
                            rows="4"
                            maxlength="10000"
                        ></textarea>
                    </div>

                    <div class="form-group" id="record-description-group">
                        <label for="record-description">Description / Details</label>
                        <textarea class="form-control" id="record-description" name="description" rows="4" maxlength="10000"></textarea>
                    </div>

                    <div class="form-group" id="record-remarks-group">
                        <label for="record-remarks">Remarks</label>
                        <textarea class="form-control" id="record-remarks" name="remarks" rows="3" maxlength="10000"></textarea>
                    </div>

                    <div class="form-group" id="record-attachment-group">
                        <label for="record-attachment">Bidding Document Attachment</label>
                        <input
                            class="form-control-file"
                            id="record-attachment"
                            name="attachment"
                            type="file"
                            accept=".pdf,.doc,.docx,.xls,.xlsx"
                        >
                        <small class="form-text text-muted" id="record-attachment-current">
                            PDF, DOC, DOCX, XLS, or XLSX; maximum 10 MB. Leave empty while editing to keep the current attachment.
                        </small>
                    </div>

                    <small class="form-text text-muted">
                        Sales and invoice records must be linked to a completed Order. Receipt Records are created by recording payments from Orders and cannot be edited here.
                    </small>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="record-save">Save Record</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
