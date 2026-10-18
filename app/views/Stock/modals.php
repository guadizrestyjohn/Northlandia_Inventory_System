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
                    <input type="hidden" name="idempotency_key" value="">
                    <input type="hidden" name="production_output_id" value="" id="stock-in-production-output-id">
                    <input type="hidden" name="inventory_id" value="" id="stock-in-production-inventory-id" disabled>
                <div class="form-group">
                    <label for="stock-in-mode">Stock In Workflow</label>
                    <select class="form-control" id="stock-in-mode" name="stock_in_mode" required>
                        <option value="manual">Ordinary Stock In</option>
                        <option value="production_receipt">Production Receipt</option>
                    </select>
                </div>
                <div class="form-group" id="stock-in-item-group">
                    <label for="stock-in-item">Inventory Item</label>
                    <select class="form-control" id="stock-in-item" name="inventory_id" required>
                        <option value="">Select Item</option>
                        <?php foreach ($items as $item): ?>
                            <option
                                value="<?= (int) $item->inventory_id ?>"
                                data-stock-item-option
                                data-item-name="<?= htmlspecialchars((string) $item->item_name, ENT_QUOTES, 'UTF-8') ?>"
                                data-item-description="<?= htmlspecialchars((string) ($item->item_description ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-item-size="<?= htmlspecialchars((string) ($item->size ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-item-width="<?= htmlspecialchars((string) ($item->width_cm ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-item-height="<?= htmlspecialchars((string) ($item->height_cm ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-item-quantity="<?= htmlspecialchars((string) $item->quantity, ENT_QUOTES, 'UTF-8') ?>"
                                data-item-unit="<?= htmlspecialchars((string) $item->unit, ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars((string) $item->item_name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group d-none" id="stock-in-output-group">
                    <label for="stock-in-output-id-select">Verified Production Output</label>
                    <select class="form-control" id="stock-in-output-id-select" disabled>
                        <option value="">Select Verified Output</option>
                    </select>
                    <small class="form-text text-muted">The item is fixed to the inventory SKU verified for this output.</small>
                </div>

                <div class="border rounded p-3 mb-3 d-none" id="stock-in-output-verification-section">
                    <button type="button" class="btn btn-outline-secondary btn-sm mb-2" id="toggle-output-verification">
                        Verify Reported Production Output
                    </button>
                    <div class="d-none" id="production-output-verification-panel">
                        <div class="form-group">
                            <label for="production-output-to-verify">Reported Output</label>
                            <select class="form-control" id="production-output-to-verify">
                                <option value="">Select Reported Output</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="production-output-verify-item">Matching Inventory Item</label>
                            <select class="form-control" id="production-output-verify-item">
                                <option value="">Select Exact Item and Dimensions</option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= (int) $item->inventory_id ?>" data-stock-item-option
                                        data-item-name="<?= htmlspecialchars((string) $item->item_name, ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-description="<?= htmlspecialchars((string) ($item->item_description ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-size="<?= htmlspecialchars((string) ($item->size ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-width="<?= htmlspecialchars((string) ($item->width_cm ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-height="<?= htmlspecialchars((string) ($item->height_cm ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-quantity="<?= htmlspecialchars((string) $item->quantity, ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-unit="<?= htmlspecialchars((string) $item->unit, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $item->item_name, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-info btn-sm" id="verify-production-output">Verify Output</button>
                        <div class="small mt-2" id="production-output-verification-message" role="status"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="stock-in-quantity">Quantity</label>
                    <input
                        class="form-control"
                        id="stock-in-quantity"
                        name="quantity"
                        type="number"
                        min="1"
                        step="1"
                        required
                    >
                    <small class="form-text text-muted">Enter whole units only.</small>
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
                    <input type="hidden" name="idempotency_key" value="">
                    <div class="form-group">
                        <label for="stock-out-mode">Stock Out Workflow</label>
                        <select class="form-control" id="stock-out-mode" name="stock_out_mode" required>
                            <option value="ordinary">Ordinary Stock Out</option>
                            <option value="customer_release">Customer Release</option>
                        </select>
                        <small class="form-text text-muted">Choose whether this movement is an ordinary inventory removal or the final release for a customer Order.</small>
                    </div>

                    <div class="form-group d-none" id="stock-out-order-group">
                        <label for="stock-out-order">Customer Order</label>
                        <select class="form-control" id="stock-out-order" name="order_id" disabled>
                            <option value="">Select Order</option>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $readyMadeEligible = ($order->product_type ?? null) === 'Ready-Made'
                                    && ($order->order_customer_approval ?? null) === 'Approved'
                                    && $order->status === 'Ready for Stock Out'
                                    && empty($order->job_order_id);
                                $customMadeEligible = ($order->product_type ?? null) === 'Custom-Made'
                                    && $order->status === 'Ready for Stock Out'
                                    && $order->job_order_status === 'Ready for Release'
                                    && !empty($order->production_completed_at);
                                ?>
                                <?php if ($readyMadeEligible || $customMadeEligible): ?>
                                    <?php
                                    $orderId = (string) $order->order_id;
                                    $orderNumber = ctype_digit($orderId)
                                        ? 'ORD-' . str_pad($orderId, 4, '0', STR_PAD_LEFT)
                                        : '';
                                    ?>
                                    <option
                                        value="<?= (int) $order->order_id ?>"
                                        data-product-type="<?= htmlspecialchars((string) $order->product_type, ENT_QUOTES, 'UTF-8') ?>"
                                        data-inventory-id="<?= (int) ($order->inventory_id ?? 0) ?>"
                                        data-job-order-id="<?= $customMadeEligible ? (int) $order->job_order_id : '' ?>"
                                        data-customer="<?= htmlspecialchars((string) $order->customer_name, ENT_QUOTES, 'UTF-8') ?>"
                                        data-product="<?= htmlspecialchars((string) $order->product_name, ENT_QUOTES, 'UTF-8') ?>"
                                        data-specifications="<?= htmlspecialchars((string) $order->specifications, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-quantity="<?= htmlspecialchars((string) $order->quantity, ENT_QUOTES, 'UTF-8') ?>"
                                        data-production-status="<?= htmlspecialchars((string) ($order->job_order_status ?? $order->status), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?> —
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

                    <div class="form-group d-none" id="stock-out-production-output-group">
                        <label for="stock-out-production-outputs">Verified, received production batches</label>
                        <select class="form-control" id="stock-out-production-outputs" name="production_output_ids[]" multiple size="5" disabled></select>
                        <small class="form-text text-muted">Select enough verified batches for the full Order quantity. Selected batches must refer to the same exact inventory item.</small>
                        <div class="small mt-1" id="stock-out-production-output-message" role="status"></div>
                    </div>

                    <div class="form-group" id="stock-out-release-details-group">
                        <input type="hidden" id="stock-out-job-order" name="job_order_id">
                        <input type="hidden" id="stock-out-linked-inventory-id" disabled>
                        <div class="alert alert-info py-2 d-none" id="stock-out-release-details">
                            <div><strong>Customer:</strong> <span data-release-field="customer"></span></div>
                            <div><strong>Job Order No.:</strong> <span data-release-field="job-order"></span></div>
                            <div><strong>Product:</strong> <span data-release-field="product"></span></div>
                            <div><strong>Specifications:</strong> <span data-release-field="specifications"></span></div>
                            <div><strong>Order Quantity:</strong> <span data-release-field="quantity"></span></div>
                            <div><strong>Production Status:</strong> <span data-release-field="status"></span></div>
                        </div>
                    </div>

                    <div class="form-group" id="stock-out-manual-item-group">
                        <label for="stock-out-item" id="stock-out-item-label">Inventory Item</label>
                        <select class="form-control" id="stock-out-item" name="inventory_id" required>
                            <option value="">Select Item</option>
                            <?php foreach ($items as $item): ?>
                                <option
                                    value="<?= (int) $item->inventory_id ?>"
                                    data-stock-item-option
                                    data-item-name="<?= htmlspecialchars((string) $item->item_name, ENT_QUOTES, 'UTF-8') ?>"
                                    data-item-description="<?= htmlspecialchars((string) ($item->item_description ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-item-size="<?= htmlspecialchars((string) ($item->size ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-item-width="<?= htmlspecialchars((string) ($item->width_cm ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-item-height="<?= htmlspecialchars((string) ($item->height_cm ?? ''), ENT_QUOTES, 'UTF-8') ?>"
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
                                I verified the selected inventory item and dimensions against the actual finished product for this Job Order. This is the final release.
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="stock-out-quantity-group">
                        <label for="stock-out-quantity">Quantity</label>
                        <input
                            class="form-control"
                            id="stock-out-quantity"
                            name="quantity"
                            type="number"
                            min="1"
                            step="1"
                            required
                        >
                        <small class="form-text text-muted">Enter whole units only.</small>
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
                        For a linked release, the item name must match the Order product. If the Order has a clear dimension, the selected item dimensions must match it. If dimensions are missing or unclear, select the actual finished item and verify its name and dimensions before confirming. The release also requires a production-finished Job Order, a Finished Products or Modulars item, full payment, and your confirmation. A reference number is generated automatically.
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
