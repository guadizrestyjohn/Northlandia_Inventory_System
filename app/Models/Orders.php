<?php

namespace Models;

use Core\Model;
use PDOException;

class Orders extends Model
{
    public function find()
    {
        $stmt = $this->db->query(
            'SELECT o.order_id, o.customer_id, c.customer_name, o.order_date,
                    o.product_name, o.quantity, o.specifications, o.order_total,
                    o.downpayment, o.delivery_method, o.status,
                    COALESCE(receipt_totals.additional_paid, 0) AS additional_paid,
                    j.job_order_id, j.status AS job_order_status,
                    j.production_assignment, j.production_start_date,
                    j.production_end_date, j.production_completed_at,
                    j.production_completed_by, j.customer_approval
             FROM orders o
             LEFT JOIN customers c ON c.customer_id = o.customer_id
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
            'SELECT o.*, c.customer_name
             FROM orders o
             LEFT JOIN customers c ON c.customer_id = o.customer_id
             WHERE o.order_id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function create($data, ?int $userId = null): array
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                'INSERT INTO orders
                    (customer_id, order_date, product_name, quantity, specifications,
                     order_total, downpayment, delivery_method, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['customer_id'], $data['order_date'], $data['product_name'],
                $data['quantity'], $data['specifications'],
                $data['order_total'], $data['downpayment'],
                $data['delivery_method'], $data['status']
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
            return ['success' => false, 'message' => 'Unable to save the Order and its initial payment.'];
        }
    }

    public function update($id, $data): array
    {
        try {
            $this->db->beginTransaction();
            $lock = $this->db->prepare(
                'SELECT order_total, downpayment
                 FROM orders WHERE order_id = ? FOR UPDATE'
            );
            $lock->execute([$id]);
            $current = $lock->fetch();
            if (!$current) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 404, 'message' => 'Order not found.'];
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

            $stmt = $this->db->prepare(
                'UPDATE orders SET customer_id = ?, order_date = ?, product_name = ?,
                    quantity = ?, specifications = ?, order_total = ?, downpayment = ?,
                    delivery_method = ?, status = ?
                 WHERE order_id = ?'
            );
            $stmt->execute([
                $data['customer_id'], $data['order_date'], $data['product_name'],
                $data['quantity'], $data['specifications'],
                $data['order_total'], $data['downpayment'],
                $data['delivery_method'], $data['status'], $id
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
                'SELECT order_id, order_total, downpayment, status
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
