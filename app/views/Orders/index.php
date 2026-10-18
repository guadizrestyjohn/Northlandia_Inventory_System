<?php
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';

unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="card">

    <div class="card-header">
        <h3 class="card-title">Orders</h3>

        <div class="card-tools">
            <?php if ($canCreateOrders ?? false): ?><button type="button" class="btn btn-primary btn-sm" id="btn-add-order">
                <i class="fas fa-plus"></i>
                Add Order
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

        <div class="form-row justify-content-end mb-3">
            <div class="form-group col-sm-6 col-md-4 col-lg-3 mb-0">
                <label for="order-status-filter">Filter by Status</label>
                <select id="order-status-filter" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Processing">Processing</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
        </div>


        <div class="table-responsive">

            <table id="orders-table" class="table table-bordered table-hover" data-can-create="<?= ($canCreateOrders ?? false) ? '1' : '0' ?>" data-can-update="<?= ($canUpdateOrders ?? false) ? '1' : '0' ?>" data-can-payment="<?= ($canRecordPayments ?? false) ? '1' : '0' ?>" data-can-approve-ready-made="<?= ($canApproveReadyMadeOrders ?? false) ? '1' : '0' ?>">

                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Pieces / Quantity</th>
                        <th>Unit Price</th>
                        <th>Total Amount</th>
                        <th>Downpayment</th>
                        <th>Balance</th>
                        <th>Delivery</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody></tbody>

            </table>

        </div>

    </div>

</div>

<div class="modal fade" id="order-payments-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Payments</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong id="order-payments-title"></strong>
                </div>
                <div class="alert alert-warning d-none" id="order-total-warning">
                    This Order has no valid Order Total. Correct the total before accepting further payments or releasing it.
                </div>
                <dl class="row mb-4">
                    <dt class="col-sm-4">Order Total</dt>
                    <dd class="col-sm-8" id="order-payment-total">—</dd>
                    <dt class="col-sm-4">Amount Before Tax</dt>
                    <dd class="col-sm-8" id="order-payment-subtotal">—</dd>
                    <dt class="col-sm-4" id="order-payment-tax-label">Tax</dt>
                    <dd class="col-sm-8" id="order-payment-tax">—</dd>
                    <dt class="col-sm-4">Downpayment</dt>
                    <dd class="col-sm-8" id="order-payment-downpayment">—</dd>
                    <dt class="col-sm-4">Total Paid</dt>
                    <dd class="col-sm-8" id="order-payment-paid">—</dd>
                    <dt class="col-sm-4">Payment Remaining</dt>
                    <dd class="col-sm-8" id="order-payment-remaining">—</dd>
                    <dt class="col-sm-4">Payment Status</dt>
                    <dd class="col-sm-8" id="order-payment-status">—</dd>
                </dl>

                <div class="border rounded p-3 mb-4 d-none" id="ready-made-approval-panel">
                    <div class="mb-2"><strong>Customer Approval:</strong> <span id="ready-made-approval-status">—</span></div>
                    <?php if ($canApproveReadyMadeOrders ?? false): ?>
                    <form id="ready-made-approval-form">
                        <input type="hidden" id="ready-made-approval-order-id">
                        <div class="form-group">
                            <label for="ready-made-approval-decision">Record customer decision</label>
                            <select class="form-control" id="ready-made-approval-decision" name="customer_approval" required>
                                <option value="">Select decision</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Save Approval</button>
                    </form>
                    <?php endif; ?>
                </div>

                <h6>Receipt History</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Receipt No.</th>
                                <th>Payment Type</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Created By</th>
                            </tr>
                        </thead>
                        <tbody id="order-payment-history"></tbody>
                    </table>
                </div>

                <?php if ($canRecordPayments ?? false): ?>
                    <form id="order-payment-form" class="border-top pt-3">
                        <input type="hidden" id="order-payment-order-id">
                        <div class="form-group">
                            <label for="order-payment-amount">Payment Amount</label>
                            <input
                                class="form-control"
                                id="order-payment-amount"
                                name="amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                            >
                            <small class="form-text text-muted">
                                Payment cannot exceed the remaining balance.
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary" id="order-payment-save">
                            Save Payment
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Order Modal -->

<div
    class="modal fade"
    id="order-modal"
    tabindex="-1"
    role="dialog"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg" role="document">

        <form
            class="modal-content"
            id="order-form"
            method="post"
            action="<?= BASE_URL ?>/api/Orders/add"
        >

            <div class="modal-header">

                <h5 class="modal-title" id="order-modal-title">
                    Add Order
                </h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label="Close"
                >
                    <span>&times;</span>
                </button>

            </div>


            <div class="modal-body">

                <input type="hidden" id="order-id">

                <!-- Customer -->

                <div class="form-group">

                    <label for="order-customer-search">
                        Customer
                    </label>

                    <input
                        class="form-control mb-2"
                        id="order-customer-search"
                        type="search"
                        autocomplete="off"
                        placeholder="Search by customer name or contact number"
                        aria-label="Search customers by name or contact number"
                    >

                    <select
                        class="form-control"
                        id="order-customer"
                        name="customer_id"
                        required
                        aria-label="Matching customers"
                    >
                        <option value="">
                            Search for a customer above
                        </option>
                    </select>

                </div>


                <!-- Order Date -->

                <div class="form-group">

                    <label for="order-date">
                        Order Date
                    </label>

                    <input
                        class="form-control"
                        id="order-date"
                        name="order_date"
                        type="date"
                        required
                        value="<?= date('Y-m-d') ?>"
                    >

                </div>


                <!-- Product Type -->

                <div class="form-group">

                    <label for="order-product-type">Product Type</label>
                    <select class="form-control" id="order-product-type" name="product_type" required>
                        <option value="">Select Product Type</option>
                        <option value="Ready-Made">Ready-Made</option>
                        <option value="Custom-Made">Custom-Made</option>
                    </select>
                    <small class="form-text text-muted" id="order-legacy-product-note"></small>

                </div>

                <!-- Product -->

                <div class="form-group d-none" id="order-inventory-product-group">

                    <label for="order-product">
                        Inventory Product
                    </label>

                    <select class="form-control" id="order-product" name="inventory_id">
                        <option value="">Select Product</option>
                    </select>
                    <small class="form-text text-muted">Each inventory item is listed separately with its dimensions.</small>

                </div>

                <div class="form-group d-none" id="order-custom-product-group">
                    <label for="order-custom-product">Custom Product Name</label>
                    <input
                        class="form-control"
                        id="order-custom-product"
                        name="custom_product_name"
                        type="text"
                        maxlength="100"
                        placeholder="Enter custom product name"
                    >

                </div>


                <!-- Quantity -->

                <div class="form-group">

                    <label for="order-quantity">
                        Quantity
                    </label>

                    <input
                        class="form-control"
                        id="order-quantity"
                        name="quantity"
                        type="number"
                        min="1"
                        max="99999999"
                        step="1"
                        required
                    >

                </div>


                <!-- Specifications -->

                <div class="form-group">

                    <label for="order-specifications" id="order-specifications-label">
                        Specifications
                    </label>

                    <textarea
                        class="form-control"
                        id="order-specifications"
                        name="specifications"
                        rows="3"
                    ></textarea>
                    <small class="form-text text-muted" id="order-specifications-help">
                        Optional for Ready-Made Orders. Custom-Made Orders require one clear dimension in centimeters (for example, 90x210 cm).
                    </small>

                </div>


                <!-- Unit Price -->

                <div class="form-group">

                    <label for="order-unit-price">
                        Unit Price (VAT-inclusive)
                    </label>

                    <input
                        class="form-control"
                        id="order-unit-price"
                        name="unit_price"
                        type="number"
                        min="0.01"
                        max="99999999.99"
                        step="0.01"
                        required
                    >
                    <small class="form-text text-muted" id="order-unit-price-help">
                        Agreed price for one piece, including VAT.
                    </small>

                </div>


                <!-- Order Total -->

                <div class="form-group">

                    <label for="order-total" id="order-total-label">
                        Total Amount (VAT-inclusive)
                    </label>

                    <input
                        class="form-control"
                        id="order-total"
                        name="order_total"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                        readonly
                    >
                    <small class="form-text text-muted" id="order-total-help">
                        Calculated as Unit Price × Pieces.
                    </small>

                </div>

                <div class="border rounded p-3 mb-3 d-none" id="order-tax-preview">
                    <strong>Tax Calculation Preview</strong>
                    <dl class="row mb-0 mt-2">
                        <dt class="col-sm-6">Amount Before Tax</dt>
                        <dd class="col-sm-6" id="order-preview-subtotal">—</dd>
                        <dt class="col-sm-6" id="order-preview-tax-label">Tax</dt>
                        <dd class="col-sm-6" id="order-preview-tax">—</dd>
                        <dt class="col-sm-6">Final Order Total</dt>
                        <dd class="col-sm-6 font-weight-bold" id="order-preview-total">—</dd>
                    </dl>
                </div>
                <div class="alert alert-warning d-none" id="order-tax-warning" role="alert"></div>

                <div class="form-group">
                    <label for="order-balance-preview">Balance</label>
                    <input class="form-control" id="order-balance-preview" type="text" readonly value="—">
                    <small class="form-text text-muted">Total Amount less all recorded payments.</small>
                </div>


                <!-- Downpayment -->

                <div class="form-group">

                    <label for="order-downpayment">
                        Downpayment
                    </label>

                    <input
                        class="form-control"
                        id="order-downpayment"
                        name="downpayment"
                        type="number"
                        min="0"
                        step="0.01"
                        value="0"
                    >
                    <small class="form-text text-muted">
                        An initial downpayment is recorded as a Receipt and counted once.
                    </small>

                </div>


                <!-- Delivery Method -->

                <div class="form-group">

                    <label for="order-delivery">
                        Delivery Method
                    </label>

                    <select
                        class="form-control"
                        id="order-delivery"
                        name="delivery_method"
                    >

                        <option value="">
                            Select Delivery Method
                        </option>

                        <option value="Pickup">
                            Pickup
                        </option>

                        <option value="Delivery">
                            Delivery
                        </option>

                    </select>

                </div>


                <!-- Status -->


                <input
                    type="hidden"
                    id="order-status-preserved"
                    name="status"
                    disabled
                >

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-dismiss="modal"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="order-save"
                >
                    Save Order
                </button>

            </div>

        </form>

    </div>

</div>
