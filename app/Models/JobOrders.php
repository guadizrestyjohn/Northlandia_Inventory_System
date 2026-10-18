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
        if (!$startDate && $endDate < date('Y-m-d')) return 'Production end date cannot be earlier than today.';
        return null;
    }

    public function eligiblePendingOrders(): array
    {
        $stmt = $this->db->query(
            "SELECT o.order_id, c.customer_name, o.product_name
             FROM orders o
             INNER JOIN customers c ON c.customer_id = o.customer_id
             LEFT JOIN job_orders j ON j.order_id = o.order_id
             WHERE o.status = 'Pending' AND o.product_type = 'Custom-Made' AND j.job_order_id IS NULL
             ORDER BY o.order_id DESC"
        );

        return $stmt->fetchAll();
    }

    public function find(?array $productionTeams = null)
    {
        $sql =
            'SELECT j.job_order_id, j.order_id, c.customer_name, o.product_name, o.quantity, o.specifications,
                    j.production_assignment, j.assigned_team, j.production_start_date,
                    j.production_end_date, j.production_completed_at,
                    completed_user.full_name AS production_completed_by_name,
                    j.customer_approval, j.status, j.created_at,
                    release_tx.released_at AS released_at
             FROM job_orders j
             INNER JOIN orders o ON o.order_id = j.order_id
             INNER JOIN customers c ON c.customer_id = o.customer_id
             LEFT JOIN users completed_user ON completed_user.user_id = j.production_completed_by
             LEFT JOIN job_order_releases release_tx
                ON release_tx.job_order_id = j.job_order_id
             ';
        if ($productionTeams !== null) {
            $productionTeams = array_values(array_unique(array_filter(
                $productionTeams ?? [],
                static fn($team) => is_string($team) && trim($team) !== ''
            )));
            if ($productionTeams === []) {
                $sql .= ' WHERE 1 = 0';
            } else {
                $teamPlaceholders = implode(', ', array_fill(0, count($productionTeams), '?'));
                $sql .= ' WHERE j.assigned_team IN (' . $teamPlaceholders . ')';
                $params = $productionTeams;
            }
        }
        $sql .= ' ORDER BY j.job_order_id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params ?? []);

        return $stmt->fetchAll();
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM job_orders WHERE job_order_id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findProductionOutputs(int $jobOrderId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.output_id, o.job_order_id, o.product_name, o.item_description,
                    o.width_cm, o.height_cm, o.reported_quantity, o.verification_status,
                    o.reported_at, reporter.full_name AS reported_by_name,
                    o.verified_at, verifier.full_name AS verified_by_name,
                    o.inventory_id,
                    (SELECT COALESCE(SUM(st.quantity), 0) FROM stock_transactions st
                     WHERE st.production_output_id = o.output_id AND st.transaction_type = 'Stock In') AS received_quantity,
                    GREATEST(o.reported_quantity - (SELECT COALESCE(SUM(st.quantity), 0) FROM stock_transactions st
                     WHERE st.production_output_id = o.output_id AND st.transaction_type = 'Stock In'), 0) AS remaining_quantity
             FROM job_order_outputs o
             LEFT JOIN users reporter ON reporter.user_id = o.reported_by
             LEFT JOIN users verifier ON verifier.user_id = o.verified_by
             WHERE o.job_order_id = ?
             ORDER BY o.output_id DESC"
        );
        $stmt->execute([$jobOrderId]);

        return $stmt->fetchAll();
    }

    public function reportProductionOutput(
        int $jobOrderId,
        string $productName,
        string $description,
        string $width,
        string $height,
        string $quantity,
        string $submissionKey,
        int $userId,
        string $assignedTeam
    ): array {
        $productName = trim($productName);
        $description = trim($description);
        $width = self::normalizeOutputDecimal($width);
        $height = self::normalizeOutputDecimal($height);
        $quantity = self::normalizeOutputQuantity($quantity);

        if ($jobOrderId < 1 || $userId < 1 || trim($assignedTeam) === '') {
            return ['success' => false, 'status' => 422, 'message' => 'A valid assigned Job Order is required.'];
        }
        if ($productName === '' || mb_strlen($productName) > 100) {
            return ['success' => false, 'status' => 422, 'message' => 'Enter an actual product name of 100 characters or fewer.'];
        }
        if ($description === '' || mb_strlen($description) > 255) {
            return ['success' => false, 'status' => 422, 'message' => 'Enter a product description of 255 characters or fewer.'];
        }
        if ($width === null || $height === null || (float) $width <= 0 || (float) $height <= 0) {
            return ['success' => false, 'status' => 422, 'message' => 'Enter valid positive product dimensions in centimeters.'];
        }
        if ($quantity === null || (int) $quantity < 1) {
            return ['success' => false, 'status' => 422, 'message' => 'Reported quantity must be a positive whole number.'];
        }
        if (!self::validOutputUuid($submissionKey)) {
            return ['success' => false, 'status' => 422, 'message' => 'A valid report request key is required. Refresh the form and try again.'];
        }

        try {
            $this->db->beginTransaction();

            // Serialize all production-output reports for this Job Order before checking prior request keys or totals.
            $jobStmt = $this->db->prepare(
                "SELECT j.job_order_id, j.assigned_team, j.status, j.customer_approval,
                        o.status AS order_status, o.product_type, o.quantity AS order_quantity
                 FROM job_orders j
                 INNER JOIN orders o ON o.order_id = j.order_id
                 WHERE j.job_order_id = ? FOR UPDATE"
            );
            $jobStmt->execute([$jobOrderId]);
            $job = $jobStmt->fetch();

            $prior = $this->db->prepare('SELECT output_id, job_order_id, product_name, item_description, width_cm, height_cm, reported_quantity, reported_by FROM job_order_outputs WHERE submission_key = ? FOR UPDATE');
            $prior->execute([$submissionKey]);
            $existing = $prior->fetch();
            if ($existing) {
                $same = (int) $existing->job_order_id === $jobOrderId
                    && (int) $existing->reported_by === $userId
                    && (string) $existing->product_name === $productName
                    && (string) $existing->item_description === $description
                    && (float) $existing->width_cm === (float) $width
                    && (float) $existing->height_cm === (float) $height
                    && (float) $existing->reported_quantity === (float) $quantity;
                $this->db->commit();
                return [
                    'success' => $same,
                    'status' => $same ? 200 : 409,
                    'output_id' => (int) $existing->output_id,
                    'message' => $same ? 'This production output report was already saved.' : 'The report request key was already used for different output data.',
                ];
            }

            if (!$job || $job->product_type !== 'Custom-Made'
                || $job->status !== 'Processing' || $job->order_status !== 'Processing'
                || $job->customer_approval !== 'Approved'
                || strcasecmp(trim((string) $job->assigned_team), trim($assignedTeam)) !== 0) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 403, 'message' => 'This Job Order is not in production for your assigned team.'];
            }

            $quantityLimit = $this->db->prepare(
                'SELECT COALESCE(SUM(reported_quantity), 0) + CAST(? AS DECIMAL(10,2)) > CAST(? AS DECIMAL(10,2))
                 FROM job_order_outputs WHERE job_order_id = ?'
            );
            $quantityLimit->execute([$quantity, (string) $job->order_quantity, $jobOrderId]);
            if ((int) $quantityLimit->fetchColumn() === 1) {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'Reported production output cannot exceed the Order quantity.'];
            }

            $insert = $this->db->prepare(
                "INSERT INTO job_order_outputs
                    (job_order_id, product_name, item_description, width_cm, height_cm, reported_quantity,
                     submission_key, reported_by, verification_status)
                 VALUES (?, ?, ?, CAST(? AS DECIMAL(10,2)), CAST(? AS DECIMAL(10,2)),
                         CAST(? AS DECIMAL(10,2)), ?, ?, 'Reported')"
            );
            $insert->execute([$jobOrderId, $productName, $description, $width, $height, $quantity, $submissionKey, $userId]);
            $outputId = (int) $this->db->lastInsertId();
            $this->db->commit();

            return ['success' => true, 'status' => 200, 'output_id' => $outputId, 'message' => 'Production output reported for verification.'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            try {
                $prior = $this->db->prepare('SELECT output_id, job_order_id, product_name, item_description, width_cm, height_cm, reported_quantity, reported_by FROM job_order_outputs WHERE submission_key = ?');
                $prior->execute([$submissionKey]);
                $existing = $prior->fetch();
                if ($existing
                    && (int) $existing->job_order_id === $jobOrderId
                    && (int) $existing->reported_by === $userId
                    && (string) $existing->product_name === $productName
                    && (string) $existing->item_description === $description
                    && (float) $existing->width_cm === (float) $width
                    && (float) $existing->height_cm === (float) $height
                    && (float) $existing->reported_quantity === (float) $quantity) {
                    return ['success' => true, 'status' => 200, 'output_id' => (int) $existing->output_id, 'message' => 'This production output report was already saved.'];
                }
            } catch (\Throwable $lookupError) {
                // Keep the original operation failure response.
            }
            error_log('Production output report failed.');
            return ['success' => false, 'status' => 500, 'message' => 'Production output could not be reported.'];
        }
    }

    private static function normalizeOutputDecimal(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/', $value, $matches)) return null;
        return (ltrim($matches[1], '0') ?: '0') . '.' . str_pad($matches[2] ?? '', 2, '0');
    }

    private static function normalizeOutputQuantity(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^(\d{1,8})(?:\.0{1,2})?$/', $value, $matches)) return null;
        return (ltrim($matches[1], '0') ?: '0') . '.00';
    }

    private static function validOutputUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    public function create($orderId)
    {
        try {
            $this->db->beginTransaction();
            $order = $this->db->prepare('SELECT order_id, status, product_type FROM orders WHERE order_id = ? FOR UPDATE');
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
            if ($orderRecord->product_type !== 'Custom-Made') {
                $this->db->rollBack();
                return ['success' => false, 'status' => 422, 'message' => 'Only an explicitly classified Custom-Made Order can enter the Job Order workflow.'];
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
            if (!$job || $job->status !== 'Pending' || $job->customer_approval !== 'Pending'
                || $job->order_status !== 'Pending') {
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

    public function endorseToProduction($id, $assignment, $assignedTeam)
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT j.order_id, j.customer_approval, j.status, j.production_end_date, o.status AS order_status, o.product_type FROM job_orders j INNER JOIN orders o ON o.order_id = j.order_id WHERE j.job_order_id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $job = $stmt->fetch();
            if (!$job || $job->product_type !== 'Custom-Made' || $job->customer_approval !== 'Approved' || $job->status !== 'Pending' || $job->order_status !== 'Pending' || ($job->production_end_date && $job->production_end_date < date('Y-m-d'))) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare("UPDATE job_orders SET production_assignment = ?, assigned_team = ?, production_start_date = COALESCE(production_start_date, CURDATE()), status = 'Processing' WHERE job_order_id = ? AND status = 'Pending'");
            $stmt->execute([$assignment, $assignedTeam, $id]);
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
            $stmt = $this->db->prepare('SELECT j.order_id, j.status, j.customer_approval, j.production_assignment, j.production_start_date, j.production_end_date, o.status AS order_status, o.product_type FROM job_orders j INNER JOIN orders o ON o.order_id = j.order_id WHERE j.job_order_id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $job = $stmt->fetch();
            if (!$job || $job->product_type !== 'Custom-Made'
                || $job->status !== 'Processing' || $job->order_status !== 'Processing'
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
