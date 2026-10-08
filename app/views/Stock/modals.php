<?php
$items = $items ?? [];
$orders = $orders ?? [];
?>

<?php if (\Core\Auth::can('stock.in')): ?>
<div class="modal fade" id="stock-in-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form
            class="modal-content"
            method="post"
            action="<?= BASE_URL ?>/api/Stock/stockIn"
            id="stock-in-form"
        >
            <div class="modal-header">
                <h5 class="modal-title">Stock In</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="stock-in-item">Inventory Item</label>
                    <select class="form-control" id="stock-in-item" name="inventory_id" required>
                        <option value="">Select Item</option>
                        <?php foreach ($items as $item): ?>
                            <option
                                value="<?= (int) $item->inventory_id ?>"
                                data-stock-item-option
                                data-item-name="<?= htmlspecialchars((string) $item->item_name, ENT_QUOTES, 'UTF-8') ?>"
                                data-item-quantity="<?= htmlspecialchars((string) $item->quantity, ENT_QUOTES, 'UTF-8') ?>"
                                data-item-unit="<?= htmlspecialchars((string) $item->unit, ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars((string) $item->item_name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="stock-in-quantity">Quantity</label>
                    <input
                        class="form-control"
                        id="stock-in-quantity"
                        name="quantity"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="stock-in-remarks">Remarks</label>
                    <textarea
                        class="form-control"
                        id="stock-in-remarks"
                        name="remarks"
                        rows="3"
                    ></textarea>
                </div>

                <div class="text-muted small">
                    A reference number is generated automatically after saving.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Save Stock In</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (\Core\Auth::can('stock.out')): ?>
    <div class="modal fade" id="stock-out-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form
                class="modal-content"
                method="post"
                action="<?= BASE_URL ?>/api/Stock/stockOut"
                id="stock-out-form"
            >
                <div class="modal-header">
                    <h5 class="modal-title">Stock Out</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label for="stock-out-order">Release Order (Optional)</label>
                        <select class="form-control" id="stock-out-order" name="order_id">
                            <option value="">No Order Reference</option>
                            <?php foreach ($orders as $order): ?>
                                <?php if ($order->status === 'Ready for Stock Out' && $order->job_order_status === 'Ready for Release' && !empty($order->production_completed_at)): ?>
                                    <option
                                        value="<?= (int) $order->order_id ?>"
                                        data-job-order-id="<?= (int) $order->job_order_id ?>"
                                        data-customer="<?= htmlspecialchars((string) $order->customer_name, ENT_QUOTES, 'UTF-8') ?>"
                                        data-product="<?= htmlspecialchars((string) $order->product_name, ENT_QUOTES, 'UTF-8') ?>"
                                        data-specifications="<?= htmlspecialchars((string) $order->specifications, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-quantity="<?= htmlspecialchars((string) $order->quantity, ENT_QUOTES, 'UTF-8') ?>"
                                        data-production-status="<?= htmlspecialchars((string) $order->job_order_status, ENT_QUOTES, 'UTF-8') ?>"
                                    >
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

                    <div class="form-group">
                        <input type="hidden" id="stock-out-job-order" name="job_order_id">
                        <div class="alert alert-info py-2 d-none" id="stock-out-release-details">
                            <div><strong>Customer:</strong> <span data-release-field="customer"></span></div>
                            <div><strong>Job Order No.:</strong> <span data-release-field="job-order"></span></div>
                            <div><strong>Product:</strong> <span data-release-field="product"></span></div>
                            <div><strong>Specifications:</strong> <span data-release-field="specifications"></span></div>
                            <div><strong>Order Quantity:</strong> <span data-release-field="quantity"></span></div>
                            <div><strong>Production Status:</strong> <span data-release-field="status"></span></div>
                        </div>

                        <label for="stock-out-item">Actual Finished Product / Modular</label>
                        <select class="form-control" id="stock-out-item" name="inventory_id" required>
                            <option value="">Select Item</option>
                            <?php foreach ($items as $item): ?>
                                <option
                                    value="<?= (int) $item->inventory_id ?>"
                                    data-stock-item-option
                                    data-item-name="<?= htmlspecialchars((string) $item->item_name, ENT_QUOTES, 'UTF-8') ?>"
                                    data-item-quantity="<?= htmlspecialchars((string) $item->quantity, ENT_QUOTES, 'UTF-8') ?>"
                                    data-item-unit="<?= htmlspecialchars((string) $item->unit, ENT_QUOTES, 'UTF-8') ?>"
                                    data-category="<?= htmlspecialchars((string) $item->category_name, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <?= htmlspecialchars((string) $item->item_name, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group d-none" id="stock-out-release-confirm-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="stock-out-confirm-release" name="confirm_release" value="1">
                            <label class="form-check-label" for="stock-out-confirm-release">
                                I confirmed this finished inventory item is the product made for this Job Order, including its specifications, and this is the final release.
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="stock-out-quantity">Release / Movement Quantity</label>
                        <input
                            class="form-control"
                            id="stock-out-quantity"
                            name="quantity"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="stock-out-remarks">Remarks</label>
                        <textarea
                            class="form-control"
                            id="stock-out-remarks"
                            name="remarks"
                            rows="3"
                    ></textarea>
                    </div>

                    <div class="text-muted small">
                        Without an Order reference, this records a general inventory movement. A linked release requires a production-finished Job Order, a Finished Products or Modulars item, and your confirmation. A reference number is generated automatically.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger" type="submit">Save Stock Out</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
