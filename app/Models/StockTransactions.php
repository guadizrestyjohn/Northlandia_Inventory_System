<?php

namespace Models;

use Core\Model;
use Core\Auth;

class StockTransactions extends Model
{
    public function process(string $type, array $data, ?int $userId): array
    {
        if (!in_array($type, ['Stock In', 'Stock Out'], true)) {
            return ['success' => false, 'message' => 'Invalid transaction type.', 'status' => 422];
        }
        if ($type === 'Stock Out' && !Auth::canStockOut()) {
            return ['success' => false, 'message' => 'You are not authorized to perform Stock Out.', 'status' => 403];
        }
        $idempotencyKey = $data['idempotency_key'] ?? '';
        if (!is_scalar($idempotencyKey) || !self::validUuid((string) $idempotencyKey)) {
            return ['success' => false, 'message' => 'A valid transaction request key is required. Refresh the form and try again.', 'status' => 422];
        }
        $inventoryInput = $data['inventory_id'] ?? '';
        if (!is_scalar($inventoryInput)) {
            return ['success' => false, 'message' => 'Select a valid inventory item.', 'status' => 422];
        }
        $inventoryId = trim((string) $inventoryInput);
        $quantity = $data['quantity'] ?? '';
        if (!ctype_digit($inventoryId)) {
            return ['success' => false, 'message' => 'Select a valid inventory item.', 'status' => 422];
        }
        if (!is_scalar($quantity) || !preg_match('/^\d+(?:\.\d{1,2})?$/', (string) $quantity)) {
            return ['success' => false, 'message' => 'Quantity must be a valid number greater than 0.', 'status' => 422];
        }
        if (!preg_match('/^\d+(?:\.0{1,2})?$/', trim((string) $quantity))) {
            return ['success' => false, 'message' => $type . ' quantity must be a whole number greater than 0.', 'status' => 422];
        }
        $quantity = self::normalizeDecimal((string) $quantity);
        if ($quantity === null || $quantity === '0.00') {
            return ['success' => false, 'message' => 'Quantity must be a valid number greater than 0.', 'status' => 422];
        }

        $productionOutputId = null;
        if ($type === 'Stock In') {
            $stockInMode = $data['stock_in_mode'] ?? 'manual';
            if (!is_scalar($stockInMode) || !in_array((string) $stockInMode, ['manual', 'production_receipt'], true)) {
                return ['success' => false, 'message' => 'Select a valid Stock In workflow.', 'status' => 422];
            }
            $outputValue = $data['production_output_id'] ?? '';
            if (!is_scalar($outputValue)) {
                return ['success' => false, 'message' => 'Select a valid production output.', 'status' => 422];
            }
            $outputInput = trim((string) $outputValue);
            if ($stockInMode === 'production_receipt') {
                if (!ctype_digit($outputInput) || (int) $outputInput < 1) {
                    return ['success' => false, 'message' => 'Select a verified production output to receive.', 'status' => 422];
                }
                $productionOutputId = (int) $outputInput;
            } elseif ($outputInput !== '') {
                return ['success' => false, 'message' => 'A production output can only be linked in Production Receipt mode.', 'status' => 422];
            }
        }

        $orderId = null;
        $jobOrderId = null;
        $confirmRelease = false;
        $stockOutMode = 'ordinary';
        $releaseOutputIds = [];
        if ($type === 'Stock Out') {
            $stockOutMode = $data['stock_out_mode'] ?? '';
            if (!is_scalar($stockOutMode)
                || !in_array((string) $stockOutMode, ['ordinary', 'customer_release'], true)) {
                return ['success' => false, 'message' => 'Select a valid Stock Out workflow.', 'status' => 422];
            }

            $orderValue = $data['order_id'] ?? '';
            if (!is_scalar($orderValue)) {
                return ['success' => false, 'message' => 'Select a valid order reference or leave it blank.', 'status' => 422];
            }
            $orderInput = trim((string) $orderValue);
            if ($orderInput !== '') {
                if (!ctype_digit($orderInput)) {
                    return ['success' => false, 'message' => 'Select a valid order reference or leave it blank.', 'status' => 422];
                }
                $orderId = (int) $orderInput;
            }
            $jobOrderValue = $data['job_order_id'] ?? '';
            if (!is_scalar($jobOrderValue)) {
                return ['success' => false, 'message' => 'Select a valid Job Order reference or leave it blank.', 'status' => 422];
            }
            $jobOrderInput = trim((string) $jobOrderValue);
            if ($jobOrderInput !== '') {
                if (!ctype_digit($jobOrderInput)) {
                    return ['success' => false, 'message' => 'Select a valid Job Order reference or leave it blank.', 'status' => 422];
                }
                $jobOrderId = (int) $jobOrderInput;
            }
            $outputInput = $data['production_output_ids'] ?? [];
            if (!is_array($outputInput)) {
                return ['success' => false, 'message' => 'Select valid verified production output batches.', 'status' => 422];
            }
            foreach ($outputInput as $outputValue) {
                if (!is_scalar($outputValue) || !ctype_digit(trim((string) $outputValue)) || (int) $outputValue < 1) {
                    return ['success' => false, 'message' => 'Select valid verified production output batches.', 'status' => 422];
                }
                $releaseOutputIds[] = (int) $outputValue;
            }
            if (count($releaseOutputIds) !== count(array_unique($releaseOutputIds))) {
                return ['success' => false, 'message' => 'A production output batch can only be selected once.', 'status' => 422];
            }
            if ($stockOutMode === 'ordinary'
                && ($orderId !== null || $jobOrderId !== null || $releaseOutputIds !== [])) {
                return ['success' => false, 'message' => 'Ordinary Stock Out cannot include a Customer Order, Job Order, or production output.', 'status' => 422];
            }
            if ($stockOutMode === 'customer_release' && $orderId === null) {
                return ['success' => false, 'message' => 'Select a Customer Order for release.', 'status' => 422];
            }
            $confirmValue = $data['confirm_release'] ?? '';
            $confirmRelease = is_scalar($confirmValue) && (string) $confirmValue === '1';
            if ($stockOutMode === 'customer_release' && !$confirmRelease) {
                return ['success' => false, 'message' => 'Confirm that the selected inventory item is authorized for this Order.', 'status' => 422];
            }
        }

        $remarks = $data['remarks'] ?? '';
        if (!is_scalar($remarks)) {
            return ['success' => false, 'message' => 'Remarks must be plain text.', 'status' => 422];
        }

        return $this->create(
            (int) $inventoryId,
            $type,
            $quantity,
            trim((string) $remarks),
            $userId,
            $orderId,
            $jobOrderId,
            $confirmRelease,
            (string) $idempotencyKey,
            $productionOutputId,
            $stockOutMode,
            $releaseOutputIds
        );
    }

    public function find()
    {
        $stmt = $this->db->query(
            'SELECT st.transaction_id, st.inventory_id, st.order_id, st.job_order_id,
                    st.production_output_id, st.release_id, i.item_name,
                    st.transaction_type, st.quantity, st.reference_no, st.remarks,
                    st.transaction_date, st.user_id, u.username, u.full_name
             FROM stock_transactions st
             INNER JOIN inventory_items i ON i.inventory_id = st.inventory_id
             LEFT JOIN users u ON u.user_id = st.user_id
             ORDER BY st.transaction_id DESC'
        );

        return $stmt->fetchAll();
    }

    public function findProductionOutputs(): array
    {
        $receivedSql = "SELECT COALESCE(SUM(st.quantity), 0) FROM stock_transactions st WHERE st.production_output_id = o.output_id AND st.transaction_type = 'Stock In'";
        $stmt = $this->db->query(
            "SELECT o.output_id, o.job_order_id, o.product_name, o.item_description,
                    o.width_cm, o.height_cm, o.reported_quantity, o.verification_status,
                    o.reported_at, reporter.full_name AS reported_by_name,
                    o.verified_at, verifier.full_name AS verified_by_name,
                    o.inventory_id, i.item_name AS inventory_item_name,
                    ($receivedSql) AS received_quantity,
                    GREATEST(o.reported_quantity - ($receivedSql), 0) AS remaining_quantity
             FROM job_order_outputs o
             LEFT JOIN users reporter ON reporter.user_id = o.reported_by
             LEFT JOIN users verifier ON verifier.user_id = o.verified_by
             LEFT JOIN inventory_items i ON i.inventory_id = o.inventory_id
             WHERE o.verification_status IN ('Reported', 'Verified')
             ORDER BY o.output_id DESC"
        );

        return $stmt->fetchAll();
    }

    public function findReleaseOutputs(int $orderId, int $jobOrderId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.output_id, o.job_order_id, o.product_name, o.width_cm, o.height_cm,
                    o.reported_quantity, o.inventory_id, i.item_name AS inventory_item_name,
                    GREATEST(
                        (SELECT COALESCE(SUM(si.quantity), 0) FROM stock_transactions si
                         WHERE si.production_output_id = o.output_id AND si.transaction_type = 'Stock In')
                        -
                        (SELECT COALESCE(SUM(so.quantity), 0) FROM stock_transactions so
                         WHERE so.production_output_id = o.output_id AND so.transaction_type = 'Stock Out'),
                        0
                    ) AS available_received_quantity
             FROM job_order_outputs o
             INNER JOIN job_orders j ON j.job_order_id = o.job_order_id
             INNER JOIN orders ord ON ord.order_id = j.order_id
             INNER JOIN inventory_items i ON i.inventory_id = o.inventory_id
             WHERE o.job_order_id = ? AND j.order_id = ?
               AND ord.product_type = 'Custom-Made'
               AND ord.status = 'Ready for Stock Out'
               AND j.status = 'Ready for Release'
               AND o.verification_status = 'Verified'
             HAVING available_received_quantity > 0
             ORDER BY o.output_id ASC"
        );
        $stmt->execute([$jobOrderId, $orderId]);
        return $stmt->fetchAll();
    }

    public function verifyProductionOutput(int $outputId, int $inventoryId, int $userId): array
    {
        if ($outputId < 1 || $inventoryId < 1 || $userId < 1) {
            return ['success' => false, 'status' => 422, 'message' => 'Select a valid production output and inventory item.'];
        }

        try {
            $this->db->beginTransaction();
            $outputStmt = $this->db->prepare(
                "SELECT o.output_id, o.job_order_id, o.product_name, o.width_cm, o.height_cm,
                        o.verification_status, o.inventory_id, ord.product_type,
                        ord.quantity AS order_quantity
                 FROM job_order_outputs o
                 INNER JOIN job_orders j ON j.job_order_id = o.job_order_id
                 INNER JOIN orders ord ON ord.order_id = j.order_id
                 WHERE o.output_id = ? FOR UPDATE"
            );
            $outputStmt->execute([$outputId]);
            $output = $outputStmt->fetch();
            if (!$output) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 404, 'message' => 'Production output not found.'];
            }
            if ($output->product_type !== 'Custom-Made') {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'Production output can only be verified for an explicitly classified Custom-Made Order.'];
            }
            $reportedLimit = $this->db->prepare(
                'SELECT COALESCE(SUM(reported_quantity), 0) > CAST(? AS DECIMAL(10,2))
                 FROM job_order_outputs WHERE job_order_id = ?'
            );
            $reportedLimit->execute([(string) $output->order_quantity, (int) $output->job_order_id]);
            if ((int) $reportedLimit->fetchColumn() === 1) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'Production output for this Job Order exceeds the Order quantity and cannot be verified.'];
            }
            if ($output->verification_status === 'Verified') {
                $sameItem = (int) ($output->inventory_id ?? 0) === $inventoryId;
                $this->db->commit();
                return ['success' => $sameItem, 'status' => $sameItem ? 200 : 409,
                    'message' => $sameItem ? 'This production output is already verified for that inventory item.' : 'This production output was already verified for a different inventory item.'];
            }
            if ($output->verification_status !== 'Reported') {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'Only reported production output can be verified.'];
            }

            $itemStmt = $this->db->prepare('SELECT item_name, size, width_cm, height_cm FROM inventory_items WHERE inventory_id = ? FOR UPDATE');
            $itemStmt->execute([$inventoryId]);
            $item = $itemStmt->fetch();
            if (!$item) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'Inventory item not found.'];
            }
            $matchError = self::productionOutputItemMatchError($output, $item);
            if ($matchError !== null) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => $matchError];
            }

            $update = $this->db->prepare(
                "UPDATE job_order_outputs
                 SET inventory_id = ?, verification_status = 'Verified', verified_by = ?, verified_at = NOW()
                 WHERE output_id = ? AND verification_status = 'Reported'"
            );
            $update->execute([$inventoryId, $userId, $outputId]);
            if ($update->rowCount() !== 1) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'The production output changed before verification. Refresh and try again.'];
            }
            $this->db->commit();
            return ['success' => true, 'status' => 200, 'message' => 'Production output verified for Stock In.'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Production output verification failed.');
            return ['success' => false, 'status' => 500, 'message' => 'Production output could not be verified.'];
        }
    }

    public function create($inventoryId, $type, $quantity, $remarks, $userId, $orderId = null, $jobOrderId = null, bool $confirmRelease = false, ?string $idempotencyKey = null, ?int $productionOutputId = null, string $stockOutMode = 'ordinary', array $releaseOutputIds = [])
    {
        if ($idempotencyKey === null || !self::validUuid($idempotencyKey)) {
            return ['success' => false, 'status' => 422, 'message' => 'A valid transaction request key is required.'];
        }
        $referenceLockAcquired = false;
        $transactionCommitted = false;
        $releaseOutputIds = array_values(array_unique(array_map('intval', $releaseOutputIds)));
        sort($releaseOutputIds, SORT_NUMERIC);
        $requestHash = hash('sha256', json_encode([
            'inventory_id' => (int) $inventoryId,
            'order_id' => $orderId,
            'job_order_id' => $jobOrderId,
            'production_output_id' => $productionOutputId,
            'production_output_ids' => $releaseOutputIds,
            'stock_out_mode' => $stockOutMode,
            'type' => $type,
            'quantity' => (string) $quantity,
            'remarks' => (string) $remarks,
        ], JSON_UNESCAPED_SLASHES));
        try {
            $this->db->beginTransaction();

            if ($idempotencyKey !== null && !($type === 'Stock Out' && $orderId !== null)) {
                $prior = $this->db->prepare('SELECT transaction_id, inventory_id, order_id, job_order_id, production_output_id, transaction_type, quantity, remarks FROM stock_transactions WHERE idempotency_key = ? FOR UPDATE');
                $prior->execute([$idempotencyKey]);
                $priorRow = $prior->fetch();
                if ($priorRow) {
                    $same = (int) $priorRow->inventory_id === (int) $inventoryId
                        && $priorRow->transaction_type === $type
                        && (float) $priorRow->quantity === (float) $quantity
                        && (int) ($priorRow->order_id ?? 0) === (int) ($orderId ?? 0)
                        && (int) ($priorRow->production_output_id ?? 0) === (int) ($productionOutputId ?? 0)
                        && ($productionOutputId !== null || (int) ($priorRow->job_order_id ?? 0) === (int) ($jobOrderId ?? 0))
                        && (string) ($priorRow->remarks ?? '') === (string) $remarks;
                    $this->db->commit();
                    return ['success' => $same, 'status' => $same ? 200 : 409,
                        'message' => $same ? 'This stock transaction was already recorded.' : 'The request key was already used for different transaction data.'];
                }
            }

            $stmt = $this->db->prepare(
                'SELECT i.item_name, i.size, i.width_cm, i.height_cm,
                        i.quantity, i.total_stocks, c.category_name
                 FROM inventory_items i
                 INNER JOIN inventory_categories c ON c.category_id = i.category_id
                 WHERE i.inventory_id = ? FOR UPDATE'
            );
            $stmt->execute([$inventoryId]);
            $item = $stmt->fetch();

            if (!$item) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Inventory item not found.', 'status' => 422];
            }

            $current = self::normalizeDecimal((string) $item->quantity);
            $quantity = self::normalizeDecimal((string) $quantity);
            if ($current === null || $quantity === null || $quantity === '0.00'
                || !preg_match('/^\d+\.00$/', $quantity)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => $type . ' quantity must be a whole number greater than 0.', 'status' => 422];
            }
            if ($type === 'Stock In' && $productionOutputId !== null) {
                $outputStmt = $this->db->prepare(
                    "SELECT o.output_id, o.job_order_id, o.product_name, o.width_cm, o.height_cm,
                            o.reported_quantity, o.inventory_id, o.verification_status, ord.product_type,
                            ord.quantity AS order_quantity
                     FROM job_order_outputs o
                     INNER JOIN job_orders j ON j.job_order_id = o.job_order_id
                     INNER JOIN orders ord ON ord.order_id = j.order_id
                     WHERE o.output_id = ? FOR UPDATE"
                );
                $outputStmt->execute([$productionOutputId]);
                $output = $outputStmt->fetch();
                if (!$output || $output->product_type !== 'Custom-Made'
                    || $output->verification_status !== 'Verified' || (int) ($output->inventory_id ?? 0) < 1) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Only a verified production output linked to an inventory item can be received.', 'status' => 422];
                }
                if ((int) $output->inventory_id !== (int) $inventoryId) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'The selected inventory item does not match the verified production output.', 'status' => 422];
                }
                $reportedLimit = $this->db->prepare(
                    'SELECT COALESCE(SUM(reported_quantity), 0) > CAST(? AS DECIMAL(10,2))
                     FROM job_order_outputs WHERE job_order_id = ?'
                );
                $reportedLimit->execute([(string) $output->order_quantity, (int) $output->job_order_id]);
                if ((int) $reportedLimit->fetchColumn() === 1) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Production output for this Job Order exceeds the Order quantity and cannot be received.', 'status' => 422];
                }
                $matchError = self::productionOutputItemMatchError($output, $item);
                if ($matchError !== null) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Verified inventory details changed. ' . $matchError, 'status' => 409];
                }
                $jobOrderId = (int) $output->job_order_id;
                $receivedStmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(quantity), 0)
                     FROM stock_transactions
                     WHERE production_output_id = ? AND transaction_type = 'Stock In'"
                );
                $receivedStmt->execute([$productionOutputId]);
                $received = self::normalizeDecimal((string) $receivedStmt->fetchColumn());
                $reported = self::normalizeDecimal((string) $output->reported_quantity);
                if ($received === null || $reported === null
                    || self::compareDecimals($received, $reported) >= 0
                    || self::compareDecimals($quantity, self::decimalDifference($reported, $received)) > 0) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'This receipt exceeds the remaining verified production output quantity.', 'status' => 422];
                }
            }
            $order = null;
            $releaseAllocations = [];
            if ($type === 'Stock Out') {
                if ($orderId !== null && $stockOutMode !== 'customer_release') {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Customer Orders can only be released using Customer Release mode.', 'status' => 422];
                }
                if ($orderId !== null) {
                    $orderStmt = $this->db->prepare(
                        'SELECT o.order_id, o.order_date, o.status, o.product_name, o.product_type,
                                o.inventory_id, o.customer_approval AS order_customer_approval,
                                o.specifications, o.quantity, o.order_total, o.downpayment,
                                o.delivery_method, j.job_order_id,
                                j.status AS job_order_status, j.customer_approval AS job_order_customer_approval,
                                j.production_assignment, j.production_start_date,
                                j.production_end_date, j.production_completed_at
                         FROM orders o
                         LEFT JOIN job_orders j ON j.order_id = o.order_id
                         WHERE o.order_id = ? FOR UPDATE'
                    );
                    $orderStmt->execute([$orderId]);
                    $order = $orderStmt->fetch();

                    if (!$order) {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'Order not found.', 'status' => 404];
                    }

                    $releaseHeaderSql = $jobOrderId === null
                        ? 'SELECT release_id, order_id, request_key, request_hash
                           FROM job_order_releases WHERE order_id = ? OR request_key = ? LIMIT 1 FOR UPDATE'
                        : 'SELECT release_id, order_id, request_key, request_hash
                           FROM job_order_releases WHERE order_id = ? OR request_key = ? OR job_order_id = ? LIMIT 1 FOR UPDATE';
                    $releaseHeaderCheck = $this->db->prepare($releaseHeaderSql);
                    $releaseHeaderCheck->execute($jobOrderId === null
                        ? [$orderId, $idempotencyKey]
                        : [$orderId, $idempotencyKey, $jobOrderId]);
                    $existingRelease = $releaseHeaderCheck->fetch();
                    if ($existingRelease) {
                        $sameRequest = (int) $existingRelease->order_id === (int) $orderId
                            && (string) ($existingRelease->request_key ?? '') === $idempotencyKey
                            && (string) ($existingRelease->request_hash ?? '') === $requestHash;
                        if ($sameRequest) {
                            $this->db->commit();
                            return ['success' => true, 'status' => 200, 'message' => 'This Customer Release was already recorded.'];
                        }
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'This Order, Job Order, or request has already been released. Duplicate Stock Out is blocked.', 'status' => 409];
                    }

                    $releaseCheck = $this->db->prepare(
                        "SELECT transaction_id FROM stock_transactions
                         WHERE order_id = ? AND transaction_type = 'Stock Out'
                         LIMIT 1 FOR UPDATE"
                    );
                    $releaseCheck->execute([$orderId]);
                    if ($releaseCheck->fetch()) {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'This Order has already been released. Duplicate Stock Out is blocked.', 'status' => 409];
                    }

                    if ($order->status !== 'Ready for Stock Out' || !$confirmRelease) {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'This Order is not ready for Customer Release or the item confirmation is missing.', 'status' => 422];
                    }

                    if ($order->product_type === 'Ready-Made') {
                        if ($jobOrderId !== null || !empty($order->job_order_id) || $releaseOutputIds !== []) {
                            $this->db->rollBack();
                            return ['success' => false, 'message' => 'Ready-Made Customer Release must not include a Job Order or production output.', 'status' => 422];
                        }
                        if ($order->order_customer_approval !== 'Approved') {
                            $this->db->rollBack();
                            return ['success' => false, 'message' => 'Customer approval is required before Ready-Made release.'];
                        }
                        if ((int) ($order->inventory_id ?? 0) !== (int) $inventoryId) {
                            $this->db->rollBack();
                            return ['success' => false, 'message' => 'Ready-Made release must use the exact inventory item saved on the Order.', 'status' => 422];
                        }
                        $matchError = self::releaseItemMatchError($order, $item);
                        if ($matchError !== null) {
                            $this->db->rollBack();
                            return ['success' => false, 'message' => $matchError, 'status' => 422];
                        }
                    } elseif ($order->product_type === 'Custom-Made') {
                        if ($jobOrderId === null || (int) ($order->job_order_id ?? 0) !== $jobOrderId
                            || $order->job_order_status !== 'Ready for Release'
                            || $order->job_order_customer_approval !== 'Approved'
                            || trim((string) $order->production_assignment) === ''
                            || !$order->production_start_date || !$order->production_end_date
                            || !$order->production_completed_at) {
                            $this->db->rollBack();
                            return ['success' => false, 'message' => 'Custom-Made release requires the approved, production-complete Job Order linked to this Order.', 'status' => 422];
                        }
                        if ($releaseOutputIds === [] || count($releaseOutputIds) > 50) {
                            $this->db->rollBack();
                            return ['success' => false, 'message' => 'Select verified, received production output batches for this Custom-Made Order.', 'status' => 422];
                        }
                        $matchError = self::releaseDimensionsMatchError($order, $item);
                        if ($matchError !== null) {
                            $this->db->rollBack();
                            return ['success' => false, 'message' => $matchError, 'status' => 422];
                        }

                        $placeholders = implode(', ', array_fill(0, count($releaseOutputIds), '?'));
                        $outputStmt = $this->db->prepare(
                            "SELECT output_id, job_order_id, product_name, width_cm, height_cm,
                                    reported_quantity, inventory_id, verification_status
                             FROM job_order_outputs
                             WHERE output_id IN ($placeholders) AND job_order_id = ?
                               AND inventory_id = ? AND verification_status = 'Verified'
                             ORDER BY output_id ASC FOR UPDATE"
                        );
                        $outputStmt->execute(array_merge($releaseOutputIds, [$jobOrderId, $inventoryId]));
                        $outputs = $outputStmt->fetchAll();
                        if (count($outputs) !== count($releaseOutputIds)) {
                            $this->db->rollBack();
                            return ['success' => false, 'message' => 'Every selected output must be verified, linked to this Job Order, and assigned to the selected inventory item.', 'status' => 422];
                        }

                        $remainingRelease = (int) ((float) $quantity);
                        foreach ($outputs as $output) {
                            $outputMatchError = self::productionOutputItemMatchError($output, $item);
                            if ($outputMatchError !== null) {
                                $this->db->rollBack();
                                return ['success' => false, 'message' => 'Verified production output no longer matches the selected inventory item. ' . $outputMatchError, 'status' => 409];
                            }
                            $receivedStmt = $this->db->prepare(
                                "SELECT COALESCE(SUM(quantity), 0) FROM stock_transactions
                                 WHERE production_output_id = ? AND transaction_type = 'Stock In'"
                            );
                            $receivedStmt->execute([$output->output_id]);
                            $releasedStmt = $this->db->prepare(
                                "SELECT COALESCE(SUM(quantity), 0) FROM stock_transactions
                                 WHERE production_output_id = ? AND transaction_type = 'Stock Out'"
                            );
                            $releasedStmt->execute([$output->output_id]);
                            $received = self::normalizeDecimal((string) $receivedStmt->fetchColumn());
                            $released = self::normalizeDecimal((string) $releasedStmt->fetchColumn());
                            if ($received === null || $released === null
                                || self::compareDecimals($received, $released) <= 0) {
                                continue;
                            }
                            $available = self::decimalDifference($received, $released);
                            $availableUnits = (int) floor((float) $available);
                            $take = min($remainingRelease, $availableUnits);
                            if ($take > 0) {
                                $releaseAllocations[] = [
                                    'production_output_id' => (int) $output->output_id,
                                    'quantity' => number_format($take, 2, '.', ''),
                                ];
                                $remainingRelease -= $take;
                            }
                        }
                        if ($remainingRelease > 0) {
                            $this->db->rollBack();
                            return ['success' => false, 'message' => 'Selected verified and received output batches do not cover the full Order quantity.', 'status' => 422];
                        }
                    } else {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'This legacy or unclassified Order cannot be released until its product type is explicitly confirmed.', 'status' => 422];
                    }

                    $orderTotalCents = self::moneyToCents((string) $order->order_total);
                    $paidStmt = $this->db->prepare(
                        "SELECT COALESCE(SUM(amount), 0)
                         FROM receipt_records
                         WHERE order_id = ? AND payment_type = 'Additional'
                           AND status = 'Recorded'"
                    );
                    $paidStmt->execute([$orderId]);
                    $additionalPaidCents = self::moneyToCents((string) $paidStmt->fetchColumn());
                    $downpaymentCents = self::moneyToCents((string) $order->downpayment);
                    $totalPaidCents = ($additionalPaidCents ?? 0) + ($downpaymentCents ?? 0);

                    if ($orderTotalCents === null || $orderTotalCents <= 0) {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'Order cannot be released. A valid Order Total must be entered first.', 'status' => 422];
                    }
                    if ($totalPaidCents < $orderTotalCents) {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'Order cannot be released. The order is not fully paid.', 'status' => 422];
                    }

                    if (!in_array($item->category_name, ['Finished Products', 'Modulars'], true)) {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'A linked customer release must use a Finished Products or Modulars inventory item.', 'status' => 422];
                    }
                    if (self::compareDecimals($quantity, (string) $order->quantity) !== 0) {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'Stock Out quantity must match the full Order quantity.', 'status' => 422];
                    }
                }
            }
            if ($type === 'Stock In') {
                $operator = '+';
            } elseif ($type === 'Stock Out') {
                if (self::compareDecimals($quantity, $current) > 0) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Not enough inventory stock.', 'status' => 422];
                }
                $operator = '-';
            } else {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Invalid transaction type.', 'status' => 422];
            }

            $calculation = $this->db->prepare(
                'SELECT CAST(CAST(? AS DECIMAL(10,2)) ' . $operator
                    . ' CAST(? AS DECIMAL(10,2)) AS DECIMAL(11,2))'
            );
            $calculation->execute([$current, $quantity]);
            $updated = (string) $calculation->fetchColumn();
            if (self::compareDecimals($updated, '99999999.99') > 0) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'The resulting quantity exceeds the supported inventory limit.', 'status' => 422];
            }
            $status = self::compareDecimals($updated, '0.00') > 0 ? 'Available' : 'Out of Stock';
            $prefix = $type === 'Stock In' ? 'SI' : 'SO';
            $referenceLockAcquired = (int) $this->db
                ->query("SELECT GET_LOCK('northlandia_stock_reference_write', 10)")
                ->fetchColumn() === 1;
            if (!$referenceLockAcquired) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Another stock transaction is being recorded. Try again shortly.', 'status' => 409];
            }

            $stmt = $this->db->prepare(
                'SELECT reference_no FROM stock_transactions
                 WHERE transaction_type = ? AND YEAR(transaction_date) = ?
                 ORDER BY transaction_id DESC LIMIT 1'
            );
            $stmt->execute([$type, date('Y')]);
            $last = $stmt->fetch();
            $lastSequence = 0;
            if ($last && preg_match('/-(\d+)$/', (string) $last->reference_no, $matches)) {
                $lastSequence = (int) $matches[1];
            }
            $next = $lastSequence + 1;

            $releaseId = null;
            if ($type === 'Stock Out' && $orderId !== null) {
                $release = $this->db->prepare(
                    'INSERT INTO job_order_releases
                        (job_order_id, order_id, request_key, request_hash, released_by, released_at)
                     VALUES (?, ?, ?, ?, ?, NOW())'
                );
                $release->execute([$jobOrderId, $orderId, $idempotencyKey, $requestHash, $userId]);
                $releaseId = (int) $this->db->lastInsertId();
            }

            $transactionLines = ($type === 'Stock Out' && $orderId !== null && $releaseAllocations !== [])
                ? $releaseAllocations
                : [[
                    'production_output_id' => $productionOutputId,
                    'quantity' => $quantity,
                ]];
            $stmt = $this->db->prepare(
                'INSERT INTO stock_transactions
                    (inventory_id, order_id, job_order_id, production_output_id, release_id, transaction_type, quantity,
                     reference_no, remarks, user_id, idempotency_key)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($transactionLines as $lineIndex => $line) {
                $reference = $prefix . '-' . date('Y') . '-' . str_pad((string) $next++, 3, '0', STR_PAD_LEFT);
                $lineRequestKey = $lineIndex === 0 ? $idempotencyKey : self::newUuid();
                $stmt->execute([
                    $inventoryId,
                    $orderId,
                    $jobOrderId,
                    $line['production_output_id'] ?? null,
                    $releaseId,
                    $type,
                    $line['quantity'],
                    $reference,
                    $remarks,
                    $userId,
                    $lineRequestKey,
                ]);
            }

            if ($type === 'Stock In') {
                $total = self::normalizeDecimal((string) $item->total_stocks);
                $newTotal = $total === null ? null : number_format((float) $total + (float) $quantity, 2, '.', '');
                if ($newTotal === null || self::compareDecimals($newTotal, '99999999.99') > 0) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'The resulting Total Stocks exceeds the supported inventory limit.', 'status' => 422];
                }
                $stmt = $this->db->prepare(
                    'UPDATE inventory_items
                     SET quantity = CAST(? AS DECIMAL(10,2)),
                         total_stocks = CAST(total_stocks + CAST(? AS DECIMAL(10,2)) AS DECIMAL(10,2)), status = ?
                     WHERE inventory_id = ?'
                );
                $stmt->execute([$updated, $quantity, $status, $inventoryId]);
            } else {
                $stmt = $this->db->prepare('UPDATE inventory_items SET quantity = CAST(? AS DECIMAL(10,2)), status = ? WHERE inventory_id = ?');
                $stmt->execute([$updated, $status, $inventoryId]);
            }

            if ($type === 'Stock Out' && $orderId !== null) {
                $jobUpdated = true;
                if ($order->product_type === 'Custom-Made') {
                    $jobUpdate = $this->db->prepare("UPDATE job_orders SET status = 'Released' WHERE job_order_id = ? AND order_id = ? AND status = 'Ready for Release'");
                    $jobUpdate->execute([$jobOrderId, $orderId]);
                    $jobUpdated = $jobUpdate->rowCount() === 1;
                }
                $orderUpdate = $this->db->prepare("UPDATE orders SET status = 'Completed' WHERE order_id = ? AND status = 'Ready for Stock Out'");
                $orderUpdate->execute([$orderId]);
                if (!$jobUpdated || $orderUpdate->rowCount() !== 1) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'The release state changed before it could be completed. No stock movement was saved.', 'status' => 409];
                }

                $invoiceCheck = $this->db->prepare(
                    'SELECT invoice_record_id
                     FROM invoice_records
                     WHERE order_id = ?
                     LIMIT 1 FOR UPDATE'
                );
                $invoiceCheck->execute([$orderId]);
                if (!$invoiceCheck->fetch()) {
                    $invoiceOrder = $this->db->prepare(
                        'SELECT order_total, subtotal_before_tax_snapshot, tax_name_snapshot,
                                tax_rate_snapshot, tax_amount_snapshot, tax_calculation_mode_snapshot
                         FROM orders
                         WHERE order_id = ? FOR UPDATE'
                    );
                    $invoiceOrder->execute([$orderId]);
                    $orderInvoice = $invoiceOrder->fetch();
                    if (!$orderInvoice) {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'Order not found during invoice creation.', 'status' => 404];
                    }

                    $invoiceInsert = $this->db->prepare(
                        'INSERT INTO invoice_records
                            (reference_no, order_id, invoice_date, amount, status, description, created_by,
                             subtotal_before_tax_snapshot, tax_name_snapshot, tax_rate_snapshot,
                             tax_amount_snapshot, tax_calculation_mode_snapshot)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $invoiceDescription = 'Automatic invoice for completed Order';
                    if ($orderInvoice->tax_name_snapshot !== null) {
                        $invoiceDescription .= sprintf(
                            ' | Amount before tax: PHP %s | %s (%s%%, %s): PHP %s | Final total: PHP %s',
                            $orderInvoice->subtotal_before_tax_snapshot,
                            $orderInvoice->tax_name_snapshot,
                            number_format((float) $orderInvoice->tax_rate_snapshot, 2, '.', ''),
                            $orderInvoice->tax_calculation_mode_snapshot,
                            $orderInvoice->tax_amount_snapshot,
                            $orderInvoice->order_total
                        );
                    }
                    $invoiceInsert->execute([
                        'INV-' . $orderId,
                        $orderId,
                        date('Y-m-d'),
                        $orderInvoice->order_total,
                        'Recorded',
                        $invoiceDescription,
                        $userId > 0 ? $userId : null,
                        $orderInvoice->subtotal_before_tax_snapshot,
                        $orderInvoice->tax_name_snapshot,
                        $orderInvoice->tax_rate_snapshot,
                        $orderInvoice->tax_amount_snapshot,
                        $orderInvoice->tax_calculation_mode_snapshot,
                    ]);
                }

                $salesCheck = $this->db->prepare(
                    'SELECT sales_record_id
                     FROM sales_records
                     WHERE order_id = ?
                     LIMIT 1 FOR UPDATE'
                );
                $salesCheck->execute([$orderId]);
                if (!$salesCheck->fetch()) {
                    $salesQuantity = rtrim(rtrim((string) $order->quantity, '0'), '.');
                    $salesDescriptionParts = [
                        'Product: ' . $order->product_name,
                        'Quantity: ' . $salesQuantity,
                    ];
                    if (trim((string) $order->specifications) !== '') {
                        $salesDescriptionParts[] = (string) $order->specifications;
                    }
                    $salesDescriptionParts[] = 'Delivery: ' . ($order->delivery_method ?: 'Not specified');

                    $salesInsert = $this->db->prepare(
                        'INSERT INTO sales_records
                            (reference_no, order_id, sales_date, amount, status, description, created_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $salesInsert->execute([
                        'SALE-ORD-' . $orderId,
                        $orderId,
                        $order->order_date,
                        $order->order_total,
                        'Recorded',
                        implode(' | ', $salesDescriptionParts),
                        $userId > 0 ? $userId : null,
                    ]);
                }
            }

            $this->db->commit();
            $transactionCommitted = true;

            return [
                'success' => true,
                'message' => $orderId !== null
                    ? ($order->product_type === 'Ready-Made'
                        ? 'Ready-Made Customer Release recorded. The Order is now completed.'
                        : 'Release recorded. The Job Order and Order are now completed.')
                    : 'Stock transaction recorded successfully.',
                'new_quantity' => $updated
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Stock transaction failed before completion.');
            return ['success' => false, 'message' => 'Transaction failed.', 'status' => 500];
        } finally {
            if ($referenceLockAcquired) {
                try {
                    $released = $this->db
                        ->query("SELECT RELEASE_LOCK('northlandia_stock_reference_write')")
                        ->fetchColumn();
                    if ((int) $released !== 1) {
                        error_log('Stock reference advisory lock release failed ' . ($transactionCommitted ? 'after commit.' : 'before commit.'));
                    }
                } catch (\Throwable $releaseError) {
                    error_log('Stock reference advisory lock release failed ' . ($transactionCommitted ? 'after commit.' : 'before commit.'));
                }
            }
        }
    }

    private static function validUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    private static function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private static function normalizeDecimal(string $value): ?string
    {
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            return null;
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 8) {
            return null;
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');
        return $whole . '.' . $fraction;
    }

    private static function compareDecimals(string $left, string $right): int
    {
        [$leftWhole, $leftFraction] = array_pad(explode('.', $left, 2), 2, '00');
        [$rightWhole, $rightFraction] = array_pad(explode('.', $right, 2), 2, '00');
        $leftWhole = ltrim($leftWhole, '0') ?: '0';
        $rightWhole = ltrim($rightWhole, '0') ?: '0';

        if (strlen($leftWhole) !== strlen($rightWhole)) {
            return strlen($leftWhole) <=> strlen($rightWhole);
        }
        $wholeComparison = strcmp($leftWhole, $rightWhole);
        if ($wholeComparison !== 0) {
            return $wholeComparison <=> 0;
        }

        return strcmp(str_pad($leftFraction, 2, '0'), str_pad($rightFraction, 2, '0')) <=> 0;
    }

    private static function decimalDifference(string $left, string $right): string
    {
        $leftMinor = self::decimalToMinor($left);
        $rightMinor = self::decimalToMinor($right);
        if ($leftMinor === null || $rightMinor === null || $leftMinor < $rightMinor) return '0.00';
        $difference = $leftMinor - $rightMinor;
        return intdiv($difference, 100) . '.' . str_pad((string) ($difference % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function decimalToMinor(string $value): ?int
    {
        $normalized = self::normalizeDecimal($value);
        if ($normalized === null) return null;
        [$whole, $fraction] = explode('.', $normalized, 2);
        return ((int) $whole * 100) + (int) $fraction;
    }

    private static function productionOutputItemMatchError(object $output, object $item): ?string
    {
        $normalizeName = static function (string $value): string {
            $collapsed = preg_replace('/\s+/u', ' ', trim($value));
            return mb_strtolower($collapsed === null ? trim($value) : $collapsed, 'UTF-8');
        };
        if ($normalizeName((string) ($output->product_name ?? '')) === ''
            || $normalizeName((string) ($output->product_name ?? '')) !== $normalizeName((string) ($item->item_name ?? ''))) {
            return 'The inventory item name must exactly match the reported production product.';
        }

        $outputWidth = self::normalizeDecimal((string) ($output->width_cm ?? ''));
        $outputHeight = self::normalizeDecimal((string) ($output->height_cm ?? ''));
        $itemWidth = null;
        $itemHeight = null;
        if (($item->width_cm ?? null) !== null && ($item->height_cm ?? null) !== null
            && is_numeric((string) $item->width_cm) && is_numeric((string) $item->height_cm)) {
            $itemWidth = self::normalizeDecimal(number_format((float) $item->width_cm, 2, '.', ''));
            $itemHeight = self::normalizeDecimal(number_format((float) $item->height_cm, 2, '.', ''));
        } else {
            $dimensions = self::dimensionsInCentimeters((string) ($item->size ?? ''));
            if ($dimensions !== null) {
                $itemWidth = self::normalizeDecimal(number_format($dimensions[0], 2, '.', ''));
                $itemHeight = self::normalizeDecimal(number_format($dimensions[1], 2, '.', ''));
            }
        }
        if ($outputWidth === null || $outputHeight === null || $itemWidth === null || $itemHeight === null
            || self::compareDecimals($outputWidth, $itemWidth) !== 0
            || self::compareDecimals($outputHeight, $itemHeight) !== 0) {
            return 'The inventory item dimensions must exactly match the reported production dimensions.';
        }
        return null;
    }

    private static function moneyToCents(string $amount): ?int
    {
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            return null;
        }
        $whole = ltrim($matches[1], '0') ?: '0';
        if (strlen($whole) > 8) {
            return null;
        }
        return ((int) $whole * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private static function releaseItemMatchError(object $order, object $item): ?string
    {
        $normalizeName = static function (string $value): string {
            $collapsed = preg_replace('/\s+/u', ' ', trim($value));
            return mb_strtolower($collapsed === null ? trim($value) : $collapsed, 'UTF-8');
        };

        if ($normalizeName((string) ($order->product_name ?? '')) === ''
            || $normalizeName((string) ($order->product_name ?? ''))
                !== $normalizeName((string) ($item->item_name ?? ''))) {
            return 'The selected inventory item name does not match the Order product. Select the matching item.';
        }

        if (($item->width_cm ?? null) !== null && ($item->height_cm ?? null) !== null
            && is_numeric((string) $item->width_cm) && is_numeric((string) $item->height_cm)
            && (float) $item->width_cm > 0 && (float) $item->height_cm > 0) {
            $itemDimensions = [(float) $item->width_cm, (float) $item->height_cm];
        } else {
            $itemDimensions = self::dimensionsInCentimeters((string) ($item->size ?? ''));
        }

        if ($itemDimensions === null || $itemDimensions[0] <= 0 || $itemDimensions[1] <= 0) {
            return 'The linked inventory item must have valid dimensions stored in Inventory before Customer Release.';
        }

        // Ready-Made dimensions come from the exact linked Inventory SKU.
        // If the Order also specifies one clear dimension, make sure it agrees.
        $orderDimensions = self::dimensionsInCentimeters((string) ($order->specifications ?? ''));
        if ($orderDimensions !== null
            && ($orderDimensions[0] !== $itemDimensions[0] || $orderDimensions[1] !== $itemDimensions[1])) {
            return 'The linked inventory item dimensions do not match the Order specifications. Update the Order or select the correct SKU.';
        }

        return null;
    }

    private static function releaseDimensionsMatchError(object $order, object $item): ?string
    {
        $orderDimensions = self::dimensionsInCentimeters((string) ($order->specifications ?? ''));
        if ($orderDimensions === null) {
            return 'Order specifications must contain exactly one clear, valid dimension in centimeters before Customer Release. Update the Order specifications and try again.';
        }

        $itemWidth = $item->width_cm ?? null;
        $itemHeight = $item->height_cm ?? null;
        if ($itemWidth !== null && $itemHeight !== null
            && is_numeric((string) $itemWidth) && is_numeric((string) $itemHeight)) {
            $itemDimensions = [(float) $itemWidth, (float) $itemHeight];
        } else {
            $itemDimensions = self::dimensionsInCentimeters((string) ($item->size ?? ''));
        }

        if ($itemDimensions === null || $itemDimensions[0] <= 0 || $itemDimensions[1] <= 0) {
            return 'The selected inventory item must have valid dimensions before Customer Release.';
        }

        if ($orderDimensions[0] !== $itemDimensions[0]
            || $orderDimensions[1] !== $itemDimensions[1]) {
            return 'The selected inventory item dimensions do not match the Order specifications. Select an item with the exact Order dimensions.';
        }

        return null;
    }

    private static function dimensionsInCentimeters(string $value): ?array
    {
        $count = preg_match_all(
            '/(?<![\d.])(\d+(?:\.\d+)?)\s*(cm|mm|m|inches?|in|ft|feet)?\s*(?:x|×)\s*(\d+(?:\.\d+)?)\s*(cm|mm|m|inches?|in|ft|feet)?\b/iu',
            $value,
            $matches,
            PREG_SET_ORDER
        );
        if ($count !== 1
            || !in_array(strtolower((string) ($matches[0][2] ?? '')), ['', 'cm'], true)
            || !in_array(strtolower((string) ($matches[0][4] ?? '')), ['', 'cm'], true)
            || (float) $matches[0][1] <= 0
            || (float) $matches[0][3] <= 0) {
            return null;
        }

        return [(float) $matches[0][1], (float) $matches[0][3]];
    }
}
