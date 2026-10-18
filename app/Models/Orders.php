<?php

namespace Models;

use Core\Model;
use PDOException;

class Orders extends Model
{
    private const MAX_ORDER_TOTAL_CENTS = 9999999999;

    public static function parseTaxRate(string $rate): ?int
    {
        if (!preg_match('/^(?:0|[1-9]\d{0,2})(?:\.\d{1,2})?$/', $rate)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function formatTaxRate(int $rateHundredths): string
    {
        return intdiv($rateHundredths, 100) . '.' . str_pad((string) ($rateHundredths % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function calculateTax(string $amount, array $configuration): ?array
    {
        $enteredCents = self::amountToCents($amount);
        $rateHundredths = self::parseTaxRate((string) ($configuration['tax_rate'] ?? ''));
        $mode = $configuration['calculation_mode'] ?? '';
        if ($enteredCents === null || $enteredCents < 1 || $rateHundredths === null
            || !in_array($mode, ['Inclusive', 'Exclusive'], true)
            || trim((string) ($configuration['tax_name'] ?? '')) === '') {
            return null;
        }

        if ($mode === 'Inclusive') {
            $grossCents = $enteredCents;
            $taxCents = self::roundTaxRatio($grossCents * $rateHundredths, 10000 + $rateHundredths);
            $subtotalCents = $grossCents - $taxCents;
        } else {
            $subtotalCents = $enteredCents;
            $taxCents = self::roundTaxRatio($subtotalCents * $rateHundredths, 10000);
            $grossCents = $subtotalCents + $taxCents;
        }

        if ($grossCents < 1 || $grossCents > self::MAX_ORDER_TOTAL_CENTS) {
            return null;
        }

        return [
            'subtotal_before_tax_snapshot' => self::formatCents($subtotalCents),
            'tax_name_snapshot' => trim((string) $configuration['tax_name']),
            'tax_rate_snapshot' => self::formatTaxRate($rateHundredths),
            'tax_amount_snapshot' => self::formatCents($taxCents),
            'tax_calculation_mode_snapshot' => $mode,
            'order_total' => self::formatCents($grossCents),
        ];
    }

    public static function calculateGrossTotalFromUnitPrice(string $unitPrice, string $quantity): ?array
    {
        $unitPriceCents = self::amountToCents($unitPrice);
        if ($unitPriceCents === null || $unitPriceCents <= 0
            || !preg_match('/^[1-9]\d{0,7}$/', $quantity)) {
            return null;
        }

        $pieces = (int) $quantity;
        if ($unitPriceCents > intdiv(self::MAX_ORDER_TOTAL_CENTS, $pieces)) {
            return null;
        }

        $grossCents = $unitPriceCents * $pieces;
        return [
            'unit_price' => self::formatCents($unitPriceCents),
            'quantity' => (string) $pieces,
            'order_total' => self::formatCents($grossCents),
        ];
    }

    private static function roundTaxRatio(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    public function find()
    {
        $stmt = $this->db->query(
            'SELECT o.order_id, o.customer_id, c.customer_name, o.order_date,
                    o.product_name, o.product_type, o.inventory_id,
                    linked_item.item_name AS inventory_item_name,
                    linked_item.size AS inventory_size,
                    linked_item.width_cm AS inventory_width_cm,
                    linked_item.height_cm AS inventory_height_cm,
                    o.quantity, o.unit_price, o.specifications, o.order_total,
                    o.downpayment, o.delivery_method, o.status,
                    o.subtotal_before_tax_snapshot, o.tax_name_snapshot,
                    o.tax_rate_snapshot, o.tax_amount_snapshot,
                    o.tax_calculation_mode_snapshot,
                    COALESCE(receipt_totals.additional_paid, 0) AS additional_paid,
                    j.job_order_id, j.status AS job_order_status,
                    j.production_assignment, j.production_start_date,
                    j.production_end_date, j.production_completed_at,
                    j.production_completed_by, j.customer_approval,
                    o.customer_approval AS order_customer_approval
             FROM orders o
             LEFT JOIN customers c ON c.customer_id = o.customer_id
             LEFT JOIN inventory_items linked_item ON linked_item.inventory_id = o.inventory_id
             LEFT JOIN job_orders j ON j.order_id = o.order_id
             LEFT JOIN (
                SELECT order_id, SUM(amount) AS additional_paid
                FROM receipt_records
                WHERE payment_type = \'Additional\' AND status = \'Recorded\'
                GROUP BY order_id
             ) receipt_totals ON receipt_totals.order_id = o.order_id
             ORDER BY o.order_id DESC'
        );

        $orders = $stmt->fetchAll();
        foreach ($orders as $order) {
            $summary = self::buildPaymentSummary(
                (string) $order->order_total,
                (string) $order->downpayment,
                (string) $order->additional_paid
            );
            foreach ($summary as $key => $value) {
                $order->{$key} = $value;
            }
        }

        return $orders;
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, c.customer_name,
                    linked_item.item_name AS inventory_item_name,
                    linked_item.size AS inventory_size,
                    linked_item.width_cm AS inventory_width_cm,
                    linked_item.height_cm AS inventory_height_cm
             FROM orders o
             LEFT JOIN customers c ON c.customer_id = o.customer_id
             LEFT JOIN inventory_items linked_item ON linked_item.inventory_id = o.inventory_id
             WHERE o.order_id = ?'
        );
        $stmt->execute([$id]);

        $order = $stmt->fetch();
        if (!$order) return null;

        $paidStmt = $this->db->prepare(
            'SELECT COALESCE(SUM(amount), 0)
             FROM receipt_records
             WHERE order_id = ? AND payment_type = \'Additional\'
               AND status = \'Recorded\''
        );
        $paidStmt->execute([$id]);
        $summary = self::buildPaymentSummary(
            (string) $order->order_total,
            (string) $order->downpayment,
            (string) $paidStmt->fetchColumn()
        );
        foreach ($summary as $key => $value) {
            $order->{$key} = $value;
        }

        return $order;
    }

    public function availableProducts(): array
    {
        $stmt = $this->db->query(
            "SELECT i.inventory_id, i.item_name, i.item_description,
                    i.size, i.width_cm, i.height_cm, i.quantity, i.unit
             FROM inventory_items i
             INNER JOIN inventory_categories c ON c.category_id = i.category_id
             WHERE c.category_name IN ('Finished Products', 'Modulars')
             ORDER BY i.item_name ASC, i.width_cm ASC, i.height_cm ASC, i.inventory_id ASC"
        );

        return $stmt->fetchAll();
    }

    public function findAvailableProductByID(int $productId): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT i.inventory_id, i.item_name, i.item_description,
                    i.size, i.width_cm, i.height_cm
             FROM inventory_items i
             INNER JOIN inventory_categories c ON c.category_id = i.category_id
             WHERE i.inventory_id = ?
               AND c.category_name IN ('Finished Products', 'Modulars')"
        );
        $stmt->execute([$productId]);

        return $stmt->fetch() ?: null;
    }

    public function create($data, ?int $userId = null): array
    {
        if (!self::validProductSelection($data, false)) {
            return ['success' => false, 'status' => 422, 'message' => 'The product type and Inventory item selection are inconsistent.'];
        }
        $pricing = self::calculateGrossTotalFromUnitPrice(
            (string) ($data['unit_price'] ?? ''),
            (string) ($data['quantity'] ?? '')
        );
        if ($pricing === null
            || self::amountToCents((string) ($data['order_total'] ?? '')) !== self::amountToCents($pricing['order_total'])) {
            return ['success' => false, 'status' => 422, 'message' => 'The Unit Price and quantity do not match the Order Total.'];
        }
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                'INSERT INTO orders
                    (customer_id, order_date, product_name, product_type, inventory_id, customer_approval,
                     quantity, unit_price, specifications,
                     order_total, downpayment, delivery_method, status,
                     subtotal_before_tax_snapshot, tax_name_snapshot, tax_rate_snapshot,
                     tax_amount_snapshot, tax_calculation_mode_snapshot)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['customer_id'], $data['order_date'], $data['product_name'],
                $data['product_type'], $data['inventory_id'],
                $data['product_type'] === 'Ready-Made' ? 'Pending' : null,
                $pricing['quantity'], $pricing['unit_price'], $data['specifications'],
                $data['order_total'], $data['downpayment'],
                $data['delivery_method'], $data['status'],
                $data['subtotal_before_tax_snapshot'], $data['tax_name_snapshot'],
                $data['tax_rate_snapshot'], $data['tax_amount_snapshot'],
                $data['tax_calculation_mode_snapshot']
            ]);

            $orderId = (int) $this->db->lastInsertId();
            $downpaymentCents = self::amountToCents((string) $data['downpayment']);
            if ($downpaymentCents > 0) {
                $this->insertReceipt(
                    $orderId,
                    $downpaymentCents,
                    'Downpayment',
                    'Initial downpayment',
                    $userId
                );
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Order added successfully.'];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'status' => 500, 'message' => 'Unable to save the Order and its initial payment.'];
        }
    }

    public function update($id, $data): array
    {
        if (($data['unit_price'] ?? null) !== null) {
            $pricing = self::calculateGrossTotalFromUnitPrice(
                (string) $data['unit_price'],
                (string) ($data['quantity'] ?? '')
            );
            if ($pricing === null
                || self::amountToCents((string) ($data['order_total'] ?? '')) !== self::amountToCents($pricing['order_total'])) {
                return ['success' => false, 'status' => 422, 'message' => 'The Unit Price and quantity do not match the Order Total.'];
            }
            $data['unit_price'] = $pricing['unit_price'];
            $data['quantity'] = $pricing['quantity'];
        }
        try {
            $this->db->beginTransaction();
            $lock = $this->db->prepare(
                'SELECT order_total, downpayment, product_name, specifications, quantity, unit_price,
                        product_type, inventory_id, customer_approval, status
                 FROM orders WHERE order_id = ? FOR UPDATE'
            );
            $lock->execute([$id]);
            $current = $lock->fetch();
            if (!$current) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 404, 'message' => 'Order not found.'];
            }
            if ($current->unit_price === null) {
                // Never infer or backfill a historical Unit Price during edits.
                if (($data['unit_price'] ?? null) !== null) {
                    $this->db->rollBack();
                    return ['success' => false, 'status' => 422, 'message' => 'A historical Order without a recorded Unit Price cannot be converted during editing.'];
                }
                $data['unit_price'] = null;
            } elseif (($data['unit_price'] ?? null) === null) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'Unit Price is required for this Order.'];
            }
            if (!self::validProductSelection($data, true)
                || (($data['product_type'] ?? null) === null
                    && ($current->product_type !== null || $current->inventory_id !== null))) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'The product type and Inventory item selection are inconsistent.'];
            }

            $linkedJob = $this->db->prepare('SELECT job_order_id FROM job_orders WHERE order_id = ? LIMIT 1 FOR UPDATE');
            $linkedJob->execute([$id]);
            if (($data['product_type'] ?? null) === 'Ready-Made' && $linkedJob->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'An Order with an existing Job Order cannot be changed to Ready-Made.'];
            }

            $currentDownpayment = self::amountToCents((string) $current->downpayment);
            $newDownpayment = self::amountToCents((string) $data['downpayment']);
            if ($newDownpayment !== $currentDownpayment) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'The initial downpayment is historical payment information and cannot be edited. Record further payments from Orders.'];
            }

            $currentTotal = self::amountToCents((string) $current->order_total);
            $newTotal = self::amountToCents((string) $data['order_total']);
            $paidStmt = $this->db->prepare(
                'SELECT COALESCE(SUM(amount), 0)
                 FROM receipt_records
                 WHERE order_id = ? AND payment_type = \'Additional\'
                   AND status = \'Recorded\''
            );
            $paidStmt->execute([$id]);
            $totalPaid = $currentDownpayment
                + self::amountToCents((string) $paidStmt->fetchColumn());

            if ($newTotal < $totalPaid) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'Order Total cannot be less than the amount already paid.'];
            }
            if ($newTotal !== $currentTotal && $currentTotal > 0 && $totalPaid > 0) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'Order Total cannot be changed after payments have been recorded.'];
            }

            $identityChanged = (string) $current->product_name !== (string) $data['product_name']
                || (string) ($current->product_type ?? '') !== (string) ($data['product_type'] ?? '')
                || (int) ($current->inventory_id ?? 0) !== (int) ($data['inventory_id'] ?? 0)
                || (float) $current->quantity !== (float) $data['quantity'];
            $orderApproval = ($data['product_type'] ?? null) === 'Ready-Made'
                ? (($current->product_type === 'Ready-Made' && !$identityChanged)
                    ? $current->customer_approval
                    : 'Pending')
                : null;
            $orderStatus = (string) $data['status'];
            if (($data['product_type'] ?? null) === 'Ready-Made' && $identityChanged
                && $current->status === 'Ready for Stock Out') {
                $orderStatus = 'Pending';
            }

            $stmt = $this->db->prepare(
                'UPDATE orders SET customer_id = ?, order_date = ?, product_name = ?,
                    product_type = ?, inventory_id = ?, customer_approval = ?, quantity = ?, unit_price = ?, specifications = ?, order_total = ?, downpayment = ?,
                    delivery_method = ?, status = ?, subtotal_before_tax_snapshot = ?,
                    tax_name_snapshot = ?, tax_rate_snapshot = ?, tax_amount_snapshot = ?,
                    tax_calculation_mode_snapshot = ?
                 WHERE order_id = ?'
            );
            $stmt->execute([
                $data['customer_id'], $data['order_date'], $data['product_name'],
                $data['product_type'], $data['inventory_id'],
                $orderApproval, $data['quantity'], $data['unit_price'] ?? null, $data['specifications'],
                $data['order_total'], $data['downpayment'],
                $data['delivery_method'], $orderStatus,
                $data['subtotal_before_tax_snapshot'] ?? null,
                $data['tax_name_snapshot'] ?? null,
                $data['tax_rate_snapshot'] ?? null,
                $data['tax_amount_snapshot'] ?? null,
                $data['tax_calculation_mode_snapshot'] ?? null,
                $id
            ]);

            $this->db->commit();
            return ['success' => true, 'status' => 200, 'message' => 'Order updated successfully.'];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'status' => 500, 'message' => 'Unable to update the Order.'];
        }
    }

    public function paymentSummary(int $orderId): array
    {
        $order = $this->findByID($orderId);
        if (!$order) {
            return [
                'order_total' => 0.0,
                'downpayment' => 0.0,
                'total_paid' => 0.0,
                'payment_remaining' => 0.0,
                'balance' => 0.0,
                'payment_status' => 'Unpaid',
            ];
        }

        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(amount), 0)
             FROM receipt_records
             WHERE order_id = ? AND payment_type = \'Additional\'
               AND status = \'Recorded\''
        );
        $stmt->execute([$orderId]);

        return self::buildPaymentSummary(
            (string) $order->order_total,
            (string) $order->downpayment,
            (string) $stmt->fetchColumn()
        );
    }

    public function paymentHistory(int $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT rr.receipt_record_id, rr.reference_no, rr.receipt_date,
                    rr.amount, rr.payment_type, rr.status, rr.description,
                    u.full_name AS created_by_name
             FROM receipt_records rr
             LEFT JOIN users u ON u.user_id = rr.created_by
             WHERE rr.order_id = ?
             ORDER BY rr.receipt_date DESC, rr.receipt_record_id DESC'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    public function addPayment(int $orderId, string $amountInput, ?int $userId): array
    {
        $paymentCents = self::amountToCents($amountInput);
        if ($paymentCents === null || $paymentCents <= 0) {
            return ['success' => false, 'status' => 422, 'message' => 'Payment must be a valid amount greater than zero.'];
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                'SELECT order_id, order_total, downpayment, status, product_type, customer_approval
                 FROM orders WHERE order_id = ? FOR UPDATE'
            );
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 404, 'message' => 'Order not found.'];
            }
            if ($order->status === 'Cancelled') {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'Payments cannot be added to a cancelled Order.'];
            }

            $orderTotalCents = self::amountToCents((string) $order->order_total);
            $downpaymentCents = self::amountToCents((string) $order->downpayment);
            if ($orderTotalCents === null || $orderTotalCents <= 0) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'Set a valid Order Total before recording a payment.'];
            }

            $sumStmt = $this->db->prepare(
                'SELECT COALESCE(SUM(amount), 0)
                 FROM receipt_records
                 WHERE order_id = ? AND payment_type = \'Additional\'
                   AND status = \'Recorded\''
            );
            $sumStmt->execute([$orderId]);
            $additionalPaidCents = self::amountToCents((string) $sumStmt->fetchColumn());
            $paidCents = $downpaymentCents + $additionalPaidCents;
            $remainingCents = $orderTotalCents - $paidCents;

            if ($remainingCents <= 0) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'This Order is already fully paid.'];
            }
            if ($paymentCents > $remainingCents) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'Payment cannot exceed the remaining balance.'];
            }

            $this->insertReceipt(
                $orderId,
                $paymentCents,
                'Additional',
                'Additional payment',
                $userId
            );

            if ($order->product_type === 'Ready-Made'
                && $order->customer_approval === 'Approved'
                && $paidCents + $paymentCents >= $orderTotalCents) {
                $ready = $this->db->prepare(
                    "UPDATE orders SET status = 'Ready for Stock Out'
                     WHERE order_id = ? AND status = 'Pending' AND product_type = 'Ready-Made'"
                );
                $ready->execute([$orderId]);
            }
            $this->db->commit();

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Payment recorded and Receipt created successfully.',
                'summary' => self::buildPaymentSummary(
                    (string) $order->order_total,
                    (string) $order->downpayment,
                    self::formatCents($additionalPaidCents + $paymentCents)
                ),
            ];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'status' => 500, 'message' => 'Unable to record the payment and Receipt.'];
        }
    }

    public function canReleaseOrder(int $orderId): bool
    {
        $summary = $this->paymentSummary($orderId);
        return (float) ($summary['order_total'] ?? 0.0) > 0.0
            && (float) ($summary['payment_remaining'] ?? 0.0) <= 0.0;
    }

    public function updateReadyMadeCustomerApproval(int $orderId, string $approval): array
    {
        if ($orderId < 1 || !in_array($approval, ['Approved', 'Rejected'], true)) {
            return ['success' => false, 'status' => 422, 'message' => 'Select a valid customer approval decision.'];
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                'SELECT order_id, product_type, customer_approval, status, order_total, downpayment
                 FROM orders WHERE order_id = ? FOR UPDATE'
            );
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if (!$order || $order->product_type !== 'Ready-Made') {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'Customer approval through this action is only available for Ready-Made Orders.'];
            }
            $job = $this->db->prepare('SELECT job_order_id FROM job_orders WHERE order_id = ? LIMIT 1 FOR UPDATE');
            $job->execute([$orderId]);
            if ($job->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'This Ready-Made Order has an existing Job Order and cannot use the Job-Order-free approval flow.'];
            }
            if ($order->customer_approval === $approval) {
                $this->db->commit();
                return ['success' => true, 'status' => 200, 'message' => 'Customer approval is already recorded.'];
            }
            if ($order->status !== 'Pending' || !in_array($order->customer_approval, [null, 'Pending'], true)) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'Customer approval can no longer be changed after a decision or release-ready status has been recorded.'];
            }

            $status = 'Pending';
            if ($approval === 'Approved') {
                $total = self::amountToCents((string) $order->order_total);
                $paid = self::amountToCents((string) $order->downpayment);
                $additional = $this->db->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM receipt_records
                     WHERE order_id = ? AND payment_type = 'Additional' AND status = 'Recorded'"
                );
                $additional->execute([$orderId]);
                $paid += self::amountToCents((string) $additional->fetchColumn());
                if ($total !== null && $total > 0 && $paid >= $total) {
                    $status = 'Ready for Stock Out';
                }
            }

            $update = $this->db->prepare(
                'UPDATE orders SET customer_approval = ?, status = ?
                 WHERE order_id = ? AND product_type = \'Ready-Made\' AND status = \'Pending\''
            );
            $update->execute([$approval, $status, $orderId]);
            if ($update->rowCount() !== 1) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'The Order changed before approval could be saved. Refresh and try again.'];
            }
            $this->db->commit();
            return ['success' => true, 'status' => 200, 'message' => $status === 'Ready for Stock Out'
                ? 'Customer approval recorded. The fully paid Ready-Made Order is ready for release.'
                : 'Customer approval recorded. The Order will become release-ready after full payment.'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Ready-Made Order approval failed.');
            return ['success' => false, 'status' => 500, 'message' => 'Customer approval could not be recorded.'];
        }
    }

    private function insertReceipt(
        int $orderId,
        int $amountCents,
        string $paymentType,
        string $description,
        ?int $userId
    ): void {
        $reference = 'OR-' . $orderId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $stmt = $this->db->prepare(
            'INSERT INTO receipt_records
                (reference_no, order_id, receipt_date, amount, payment_type, status, description, created_by)
             VALUES (?, ?, CURDATE(), ?, ?, \'Recorded\', ?, ?)'
        );
        $stmt->execute([
            $reference,
            $orderId,
            self::formatCents($amountCents),
            $paymentType,
            $description,
            $userId && $userId > 0 ? $userId : null,
        ]);
    }

    private static function buildPaymentSummary(
        string $orderTotal,
        string $downpayment,
        string $additionalPaid
    ): array {
        $totalCents = self::amountToCents($orderTotal) ?? 0;
        $downpaymentCents = self::amountToCents($downpayment) ?? 0;
        $additionalCents = self::amountToCents($additionalPaid) ?? 0;
        $paidCents = $downpaymentCents + $additionalCents;
        $remainingCents = $totalCents - $paidCents;
        $status = $totalCents <= 0
            ? 'Unpaid'
            : ($paidCents <= 0
                ? 'Unpaid'
                : ($paidCents >= $totalCents ? 'Fully Paid' : 'Partially Paid'));

        return [
            'order_total' => self::formatCents($totalCents),
            'downpayment' => self::formatCents($downpaymentCents),
            'total_paid' => self::formatCents($paidCents),
            'payment_remaining' => self::formatCents($remainingCents),
            'balance' => self::formatCents($remainingCents),
            'payment_status' => $status,
        ];
    }

    private static function validProductSelection(array $data, bool $allowLegacy): bool
    {
        $type = $data['product_type'] ?? null;
        $inventoryId = $data['inventory_id'] ?? null;

        if ($type === 'Ready-Made') {
            return is_scalar($inventoryId)
                && ctype_digit((string) $inventoryId)
                && (int) $inventoryId > 0;
        }

        if ($type === 'Custom-Made') {
            return $inventoryId === null
                && trim((string) ($data['product_name'] ?? '')) !== '';
        }

        return $allowLegacy && $type === null && $inventoryId === null;
    }

    private static function amountToCents(string $amount): ?int
    {
        if (!preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', $amount)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private static function formatCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);
        return $sign . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

}
