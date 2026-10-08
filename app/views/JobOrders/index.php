<?php
$jobOrders = $jobOrders ?? [];
$orders = $orders ?? [];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
$canCreateJobOrders = $canCreateJobOrders ?? false;
$canApproveJobOrders = $canApproveJobOrders ?? false;
$canEndorseJobOrders = $canEndorseJobOrders ?? false;
$canManageProduction = $canManageProduction ?? false;
$canSetProductionTimeframe = $canSetProductionTimeframe ?? false;
$canCompleteProduction = $canCompleteProduction ?? false;
$canManageJobOrders = $canApproveJobOrders || $canEndorseJobOrders || $canManageProduction;
$linkedOrderIds = array_map(
    static fn($job) => (string) $job->order_id,
    $jobOrders
);
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Job Order Workflow</h3>
        <div class="card-tools">
            <?php if ($canCreateJobOrders): ?><button type="button" class="btn btn-primary btn-sm" id="btn-add-job-order">
                <i class="fas fa-plus"></i> Create Job Order
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

        <div class="table-responsive">
            <table id="job-orders-table" class="table table-bordered table-hover" data-can-create="<?= $canCreateJobOrders ? '1' : '0' ?>" data-can-approval="<?= $canApproveJobOrders ? '1' : '0' ?>" data-can-endorse="<?= $canEndorseJobOrders ? '1' : '0' ?>" data-can-timeframe="<?= $canSetProductionTimeframe ? '1' : '0' ?>" data-can-complete="<?= $canCompleteProduction ? '1' : '0' ?>">
                <thead>
                    <tr>
                        <th>Job Order No.</th>
                        <th>Order No.</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Approval</th>
                        <th>Assignment</th>
                        <th>Production Start</th>
                        <th>Production End</th>
                        <th>Status</th>
                        <?php if ($canManageJobOrders): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canCreateJobOrders): ?><div class="modal fade" id="add-job-order-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" method="post" action="<?= BASE_URL ?>/api/JobOrders/add">
            <div class="modal-header">
                <h5 class="modal-title">Create Job Order</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="job-order-order-id">Pending Order</label>
                    <select
                        class="form-control"
                        id="job-order-order-id"
                        name="order_id"
                        required
                    >
                        <option value="">Select Order</option>
                        <?php foreach ($orders as $order): ?>
                            <?php if ($order->status === 'Pending' && !in_array((string) $order->order_id, $linkedOrderIds, true)): ?>
                                <option value="<?= (int) $order->order_id ?>">
                                    <?= htmlspecialchars(\Core\Display::orderNumber($order->order_id), ENT_QUOTES, 'UTF-8') ?> —
                                    <?= htmlspecialchars(
                                        (string) $order->customer_name . ' — ' . (string) $order->product_name,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="alert alert-info mb-0">
                    A Job Order must be linked to an existing customer order.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Job Order</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canManageJobOrders): ?>
<div class="modal fade" id="manage-job-order-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Manage Job Order <span id="manage-job-order-number"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row mb-3" id="manage-job-order-details"></div>
                <hr>

                <?php if ($canApproveJobOrders): ?><form
                    class="form-row align-items-end mb-4"
                    method="post"
                    action="<?= BASE_URL ?>/api/JobOrders/approval"
                >
                    <input type="hidden" name="job_order_id" class="manage-job-order-id">
                    <div class="form-group col-md-8">
                        <label>Customer Approval</label>
                        <select class="form-control" name="customer_approval" required>
                            <option>Pending</option>
                            <option>Approved</option>
                            <option>Rejected</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <button class="btn btn-primary btn-block" type="submit">Save Approval</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if ($canEndorseJobOrders): ?><form
                    class="form-row align-items-end mb-4"
                    method="post"
                    action="<?= BASE_URL ?>/api/JobOrders/endorse"
                >
                    <input type="hidden" name="job_order_id" class="manage-job-order-id">
                    <div class="form-group col-md-8">
                        <label>Production Assignment</label>
                        <input class="form-control" name="production_assignment" placeholder="Production team" required>
                    </div>
                    <div class="form-group col-md-4">
                        <button class="btn btn-info btn-block" type="submit">Endorse to Production</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if ($canSetProductionTimeframe): ?><form
                    class="form-row align-items-end"
                    method="post"
                    action="<?= BASE_URL ?>/api/JobOrders/timeframe"
                    id="job-order-timeframe-form"
                >
                    <input type="hidden" name="job_order_id" class="manage-job-order-id">
                    <div class="form-group col-md-5">
                        <label>Production End Date</label>
                        <input
                            class="form-control"
                            type="date"
                            name="production_end_date"
                            id="job-order-end-date"
                            required
                        >
                    </div>
                    <div class="form-group col-md-3">
                        <button class="btn btn-secondary btn-block" type="submit">Save Timeframe</button>
                    </div>
                </form>
                <?php endif; ?>

<?php if ($canCompleteProduction): ?><form class="mt-3" method="post" action="<?= BASE_URL ?>/api/JobOrders/complete" id="job-order-complete-form">
                    <input type="hidden" name="job_order_id" class="manage-job-order-id">
                    <button class="btn btn-success" type="submit" id="job-order-complete">
                        Mark Production Finished
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
