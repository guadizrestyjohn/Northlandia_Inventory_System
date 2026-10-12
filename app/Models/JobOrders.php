<?php

namespace Models;

use Core\Model;

class JobOrders extends Model
{
    public static function productionEndDateError(string $endDate, ?string $startDate): ?string
    {
        $date = \DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$date || $date->format('Y-m-d') !== $endDate) return 'Enter a valid production end date.';
        if ($startDate && $endDate < $startDate) return 'Production end date cannot be earlier than the start date.';
        return null;
    }

    public function eligiblePendingOrders(): array
    {
        $stmt = $this->db->query(
            "SELECT o.order_id, c.customer_name, o.product_name
             FROM orders o
             INNER JOIN customers c ON c.customer_id = o.customer_id
             LEFT JOIN job_orders j ON j.order_id = o.order_id
             WHERE o.status = 'Pending' AND j.job_order_id IS NULL
             ORDER BY o.order_id DESC"
        );

        return $stmt->fetchAll();
    }

    public function find(?string $productionAssignment = null)
    {
        $sql =
            'SELECT j.job_order_id, j.order_id, c.customer_name, o.product_name, o.quantity, o.specifications,
                    j.production_assignment, j.production_start_date,
                    j.production_end_date, j.production_completed_at,
                    completed_user.full_name AS production_completed_by_name,
                    j.customer_approval, j.status, j.created_at,
                    release_tx.transaction_date AS released_at
             FROM job_orders j
             INNER JOIN orders o ON o.order_id = j.order_id
             INNER JOIN customers c ON c.customer_id = o.customer_id
             LEFT JOIN users completed_user ON completed_user.user_id = j.production_completed_by
             LEFT JOIN stock_transactions release_tx
                ON release_tx.job_order_id = j.job_order_id
               AND release_tx.transaction_type = \'Stock Out\'';
        if ($productionAssignment !== null) {
            $sql .= ' WHERE j.production_assignment = ?';
        }
        $sql .= ' ORDER BY j.job_order_id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($productionAssignment === null ? [] : [$productionAssignment]);

        return $stmt->fetchAll();
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM job_orders WHERE job_order_id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function create($orderId)
    {
        try {
            $this->db->beginTransaction();
            $order = $this->db->prepare('SELECT order_id, status FROM orders WHERE order_id = ? FOR UPDATE');
            $order->execute([$orderId]);
            $orderRecord = $order->fetch();
            if (!$orderRecord) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 404, 'message' => 'Order not found.'];
            }
            if ($orderRecord->status !== 'Pending') {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'A Job Order can only be created from a Pending order.'];
            }
            $check = $this->db->prepare('SELECT job_order_id FROM job_orders WHERE order_id = ? FOR UPDATE');
            $check->execute([$orderId]);
            if ($check->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 409, 'message' => 'A Job Order already exists for this Order.'];
            }
            $stmt = $this->db->prepare("INSERT INTO job_orders (order_id, customer_approval, status) VALUES (?, 'Pending', 'Pending')");
            $success = $stmt->execute([$orderId]);
            $this->db->commit();
            return ['success' => $success, 'status' => $success ? 200 : 500, 'message' => $success ? 'Job Order created successfully.' : 'Failed to create Job Order.'];
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['success' => false, 'status' => 500, 'message' => 'Failed to create Job Order.'];
        }
    }

    public function updateCustomerApproval($id, $approval)
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT j.status, j.customer_approval, o.status AS order_status FROM job_orders j INNER JOIN orders o ON o.order_id = j.order_id WHERE j.job_order_id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $job = $stmt->fetch();
            if (!$job || $job->status !== 'Pending' || $job->order_status !== 'Pending') {
                $this->db->rollBack();
                return false;
            }
            if ($job->customer_approval === $approval) {
                $this->db->commit();
                return true;
            }
            $stmt = $this->db->prepare("UPDATE job_orders SET customer_approval = ? WHERE job_order_id = ? AND status = 'Pending'");
            $stmt->execute([$approval, $id]);
            $success = $stmt->rowCount() === 1;
            $this->db->commit();
            return $success;
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function endorseToProduction($id, $assignment)
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT j.order_id, j.customer_approval, j.status, j.production_end_date, o.status AS order_status FROM job_orders j INNER JOIN orders o ON o.order_id = j.order_id WHERE j.job_order_id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $job = $stmt->fetch();
            if (!$job || $job->customer_approval !== 'Approved' || $job->status !== 'Pending' || $job->order_status !== 'Pending' || ($job->production_end_date && $job->production_end_date < date('Y-m-d'))) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare("UPDATE job_orders SET production_assignment = ?, production_start_date = COALESCE(production_start_date, CURDATE()), status = 'Processing' WHERE job_order_id = ? AND status = 'Pending'");
            $stmt->execute([$assignment, $id]);
            $stmt = $this->db->prepare("UPDATE orders SET status = 'Processing' WHERE order_id = ? AND status = 'Pending'");
            $stmt->execute([$job->order_id]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function updateProductionTimeframe($id, $endDate)
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("SELECT production_start_date, status FROM job_orders WHERE job_order_id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $job = $stmt->fetch();
            if (!$job || !in_array($job->status, ['Pending', 'Processing'], true)
                || self::productionEndDateError((string) $endDate, $job->production_start_date ? (string) $job->production_start_date : null) !== null) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare('UPDATE job_orders SET production_end_date = ? WHERE job_order_id = ?');
            $success = $stmt->execute([$endDate, $id]);
            $this->db->commit();
            return $success;
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function complete($id, ?int $userId)
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT j.order_id, j.status, j.customer_approval, j.production_assignment, j.production_start_date, j.production_end_date, o.status AS order_status FROM job_orders j INNER JOIN orders o ON o.order_id = j.order_id WHERE j.job_order_id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $job = $stmt->fetch();
            if (!$job || $job->status !== 'Processing' || $job->order_status !== 'Processing'
                || $job->customer_approval !== 'Approved'
                || trim((string) $job->production_assignment) === ''
                || !$job->production_start_date || !$job->production_end_date
                || self::productionEndDateError(
                    (string) $job->production_end_date,
                    (string) $job->production_start_date
                ) !== null) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare("UPDATE job_orders SET status = 'Ready for Release', production_completed_at = NOW(), production_completed_by = ? WHERE job_order_id = ? AND status = 'Processing'");
            $stmt->execute([$userId, $id]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare("UPDATE orders SET status = 'Ready for Stock Out' WHERE order_id = ? AND status = 'Processing'");
            $stmt->execute([$job->order_id]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }
}
