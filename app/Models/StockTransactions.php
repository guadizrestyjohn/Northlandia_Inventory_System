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

        $orderId = null;
        $jobOrderId = null;
        $confirmRelease = false;
        if ($type === 'Stock Out') {
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
            if (($orderId === null) !== ($jobOrderId === null)) {
                return ['success' => false, 'message' => 'Select both the related Order and Job Order for a release.', 'status' => 422];
            }
            $confirmValue = $data['confirm_release'] ?? '';
            $confirmRelease = is_scalar($confirmValue) && (string) $confirmValue === '1';
            if ($orderId !== null && !$confirmRelease) {
                return ['success' => false, 'message' => 'Confirm that this finished inventory item is the product for the selected Job Order.', 'status' => 422];
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
            $confirmRelease
        );
    }

    public function find()
    {
        $stmt = $this->db->query(
            'SELECT st.transaction_id, st.inventory_id, st.order_id, st.job_order_id, i.item_name,
                    st.transaction_type, st.quantity, st.reference_no, st.remarks,
                    st.transaction_date, st.user_id, u.username, u.full_name
             FROM stock_transactions st
             INNER JOIN inventory_items i ON i.inventory_id = st.inventory_id
             LEFT JOIN users u ON u.user_id = st.user_id
             ORDER BY st.transaction_id DESC'
        );

        return $stmt->fetchAll();
    }

    public function create($inventoryId, $type, $quantity, $remarks, $userId, $orderId = null, $jobOrderId = null, bool $confirmRelease = false)
    {
        $referenceLockAcquired = false;
        $transactionCommitted = false;
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'SELECT i.quantity, c.category_name
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
            $order = null;
            if ($type === 'Stock Out') {
                if (($orderId === null) !== ($jobOrderId === null)) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Select both the related Order and Job Order for a release.', 'status' => 422];
                }
                if ($orderId !== null) {
                    $releaseCheck = $this->db->prepare(
                        "SELECT transaction_id
                         FROM stock_transactions
                         WHERE order_id = ? AND transaction_type = 'Stock Out'
                         LIMIT 1 FOR UPDATE"
                    );
                    $releaseCheck->execute([$orderId]);
                    if ($releaseCheck->fetch()) {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'This Order has already been released. Duplicate Stock Out is blocked.', 'status' => 409];
                    }

                    $orderStmt = $this->db->prepare(
                        'SELECT o.order_id, o.status, o.product_name, o.specifications,
                                o.quantity, o.order_total, o.downpayment, j.job_order_id,
                                j.status AS job_order_status, j.customer_approval,
                                j.production_assignment, j.production_start_date,
                                j.production_end_date, j.production_completed_at
                         FROM orders o
                         INNER JOIN job_orders j ON j.order_id = o.order_id
                         WHERE o.order_id = ? AND j.job_order_id = ? FOR UPDATE'
                    );
                    $orderStmt->execute([$orderId, $jobOrderId]);
                    $order = $orderStmt->fetch();
                    if (!$order || $order->status !== 'Ready for Stock Out'
                        || $order->job_order_status !== 'Ready for Release'
                        || $order->customer_approval !== 'Approved'
                        || trim((string) $order->production_assignment) === ''
                        || !$order->production_start_date || !$order->production_end_date
                        || !$order->production_completed_at || !$confirmRelease) {
                        $this->db->rollBack();
                        return ['success' => false, 'message' => 'The linked Order and Job Order are not ready for release.', 'status' => 422];
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
            $reference = $prefix . '-' . date('Y') . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);

            $stmt = $this->db->prepare(
                'INSERT INTO stock_transactions
                    (inventory_id, order_id, job_order_id, transaction_type, quantity, reference_no, remarks, user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$inventoryId, $orderId, $jobOrderId, $type, $quantity, $reference, $remarks, $userId]);

            $stmt = $this->db->prepare(
                'UPDATE inventory_items SET quantity = CAST(? AS DECIMAL(10,2)), status = ? WHERE inventory_id = ?'
            );
            $stmt->execute([$updated, $status, $inventoryId]);

            if ($type === 'Stock Out' && $orderId !== null) {
                $jobUpdate = $this->db->prepare("UPDATE job_orders SET status = 'Released' WHERE job_order_id = ? AND order_id = ? AND status = 'Ready for Release'");
                $jobUpdate->execute([$jobOrderId, $orderId]);
                $orderUpdate = $this->db->prepare("UPDATE orders SET status = 'Completed' WHERE order_id = ? AND status = 'Ready for Stock Out'");
                $orderUpdate->execute([$orderId]);
                if ($jobUpdate->rowCount() !== 1 || $orderUpdate->rowCount() !== 1) {
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
            }

            $this->db->commit();
            $transactionCommitted = true;

            return [
                'success' => true,
                'message' => $orderId !== null
                    ? 'Release recorded. The Job Order and Order are now completed.'
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
}
