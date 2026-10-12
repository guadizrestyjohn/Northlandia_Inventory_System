<?php

namespace Models;

use Core\Model;
use PDOException;

class Records extends Model
{
    private const FILTERABLE_TYPES = [
        'Purchase',
        'Sales',
        'Order',
        'Receipt',
        'Invoice',
        'Bidding Document',
        'Stock In',
        'Stock Out',
        'Job Order',
    ];

    private const STATUSES = [
        'Recorded',
        'Pending',
        'Approved',
        'Rejected',
        'Processing',
        'Completed',
        'Cancelled',
        'Ready for Stock Out',
        'Ready for Release',
        'Released',
        'Stock In',
        'Stock Out',
    ];

    public function search(array $filters = []): array
    {
        $sql = $this->recordsQuery() . ' WHERE 1 = 1';
        $params = [];

        if (!empty($filters['type']) && !in_array($filters['type'], self::FILTERABLE_TYPES, true)) {
            return [];
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND record_type = ?';
            $params[] = $filters['type'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= ' AND record_date >= ?';
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= ' AND record_date <= ?';
            $params[] = $filters['end_date'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['keyword'])) {
            $sql .= ' AND (reference_no LIKE ? OR description LIKE ? OR customer_name LIKE ?
                OR supplier_name LIKE ? OR title LIKE ? OR client_name LIKE ?
                OR item_name LIKE ? OR product_name LIKE ? OR CAST(record_id AS CHAR) LIKE ?)';
            $keyword = '%' . $filters['keyword'] . '%';
            array_push($params, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword);
        }

        $sql .= ' ORDER BY record_date DESC, record_id DESC, record_type ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findOrders(): array
    {
        $stmt = $this->db->query(
            'SELECT o.order_id, o.product_name, o.status, c.customer_name
             FROM orders o
             INNER JOIN customers c ON c.customer_id = o.customer_id
             ORDER BY o.order_id DESC'
        );

        return $stmt->fetchAll();
    }

    public function findDocument($id, string $type): ?object
    {
        $definition = $this->documentDefinition($type);
        if ($definition === null || !ctype_digit((string) $id)) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM ' . $definition['table'] . ' WHERE ' . $definition['id'] . ' = ?'
        );
        $stmt->execute([(int) $id]);

        return $stmt->fetch() ?: null;
    }

    public function saveDocument(?int $id, array $data, int $userId): array
    {
        $type = trim((string) ($data['record_type'] ?? ''));
        $definition = $this->documentDefinition($type);
        if ($definition === null) {
            return ['success' => false, 'message' => 'Select a valid record type.'];
        }
        if ($type === 'Receipt') {
            return [
                'success' => false,
                'message' => 'Receipt Records are created when a payment is recorded from Orders.',
            ];
        }
        if ($id === null && !in_array($type, ['Purchase', 'Bidding Document'], true)) {
            return [
                'success' => false,
                'message' => 'Only Purchase and Bidding Document records can be added here.',
            ];
        }

        $reference = trim((string) ($data['reference_no'] ?? ''));
        $dateValue = trim((string) ($data['record_date'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'Recorded'));
        $description = trim((string) ($data['description'] ?? ''));
        $amountInput = trim((string) ($data['amount'] ?? ''));
        $orderIdInput = trim((string) ($data['order_id'] ?? ''));

        if ($reference === '' || strlen($reference) > 50) {
            return ['success' => false, 'message' => 'Reference number is required and must be 50 characters or fewer.'];
        }
        if (!$this->isValidDate($dateValue)) {
            return ['success' => false, 'message' => 'Enter a valid record date.'];
        }
        if (!in_array($status, self::STATUSES, true)) {
            return ['success' => false, 'message' => 'Select a valid record status.'];
        }

        $amount = null;
        if ($definition['requires_amount']) {
            if ($amountInput === '' || !is_numeric($amountInput)
                || !is_finite((float) $amountInput) || (float) $amountInput < 0
                || (float) $amountInput > 99999999.99
            ) {
                return ['success' => false, 'message' => 'Enter a valid non-negative amount.'];
            }
            $amount = (float) $amountInput;
        } elseif ($amountInput !== '') {
            if (!is_numeric($amountInput) || !is_finite((float) $amountInput)
                || (float) $amountInput < 0 || (float) $amountInput > 99999999.99
            ) {
                return ['success' => false, 'message' => 'Enter a valid non-negative amount.'];
            }
            $amount = (float) $amountInput;
        }

        if ($type === 'Purchase' && trim((string) ($data['items_details'] ?? $description)) === '') {
            return ['success' => false, 'message' => 'Purchase item details are required.'];
        }
        if ($type === 'Bidding Document' && trim((string) ($data['title'] ?? '')) === '') {
            return ['success' => false, 'message' => 'Bidding document title is required.'];
        }
        if ($type === 'Bidding Document' && mb_strlen(trim((string) $data['title'])) > 150) {
            return ['success' => false, 'message' => 'Bidding document title must be 150 characters or fewer.'];
        }

        $orderId = null;
        if ($orderIdInput !== '') {
            if (!ctype_digit($orderIdInput)) {
                return ['success' => false, 'message' => 'Select a valid related order.'];
            }
            $orderId = (int) $orderIdInput;
        }

        if ($definition['requires_order'] && $orderId === null) {
            return ['success' => false, 'message' => 'Select a related order.'];
        }
        if ($orderId !== null) {
            $order = $this->db->prepare('SELECT order_id, status FROM orders WHERE order_id = ?');
            $order->execute([$orderId]);
            $orderRecord = $order->fetch();
            if (!$orderRecord) {
                return ['success' => false, 'status' => 404, 'message' => 'The selected order could not be found.'];
            }
            if ($definition['requires_completed_order'] && $orderRecord->status !== 'Completed') {
                return ['success' => false, 'message' => 'This record must be linked to a completed Order.'];
            }
        }

        $duplicate = $this->db->prepare(
            'SELECT ' . $definition['id'] . ' FROM ' . $definition['table'] . '
             WHERE reference_no = ? AND (? IS NULL OR ' . $definition['id'] . ' <> ?)
             LIMIT 1'
        );
        $duplicate->execute([$reference, $id, $id]);
        if ($duplicate->fetch()) {
            return ['success' => false, 'status' => 409, 'message' => 'That reference number is already used for this record type.'];
        }

        $fields = $definition['fields'];
        $values = [];
        foreach ($fields as $field) {
            $values[] = match ($field) {
                'reference_no' => $reference,
                'order_id' => $orderId,
                'purchase_date', 'sales_date', 'receipt_date', 'invoice_date', 'submission_date' => $dateValue,
                'amount' => $amount,
                'status' => $status,
                'items_details' => trim((string) ($data['items_details'] ?? $description)),
                'description' => $description !== '' ? $description : null,
                'supplier_name' => $this->nullableString($data['supplier_name'] ?? null, 150),
                'title' => trim((string) ($data['title'] ?? '')),
                'client_name' => $this->nullableString($data['client_name'] ?? null, 150),
                'created_by' => $userId > 0 ? $userId : null,
                default => null,
            };
        }

        try {
            if ($id === null) {
                $columns = implode(', ', $fields);
                $placeholders = implode(', ', array_fill(0, count($fields), '?'));
                $stmt = $this->db->prepare(
                    'INSERT INTO ' . $definition['table'] . ' (' . $columns . ')
                     VALUES (' . $placeholders . ')'
                );
                $success = $stmt->execute($values);
            } else {
                if (!$this->findDocument($id, $type)) {
                    return ['success' => false, 'status' => 404, 'message' => 'Record not found or cannot be edited.'];
                }
                $fieldValues = array_combine($fields, $values);
                $updateFields = array_values(array_diff($fields, ['created_by']));
                $updateValues = array_map(
                    static fn (string $field) => $fieldValues[$field],
                    $updateFields
                );
                $assignments = implode(' = ?, ', $updateFields) . ' = ?';
                $stmt = $this->db->prepare(
                    'UPDATE ' . $definition['table'] . ' SET ' . $assignments
                    . ' WHERE ' . $definition['id'] . ' = ?'
                );
                $success = $stmt->execute(array_merge($updateValues, [$id]));
            }
        } catch (PDOException $e) {
            return ['success' => false, 'status' => 500, 'message' => 'Unable to save this record.'];
        }

        return [
            'success' => $success,
            'status' => $success ? 200 : 500,
            'message' => $success ? 'Record saved successfully.' : 'Unable to save this record.',
        ];
    }

    private function documentDefinition(string $type): ?array
    {
        $definitions = [
            'Purchase' => [
                'table' => 'purchase_records', 'id' => 'purchase_record_id',
                'requires_amount' => true, 'requires_order' => false, 'requires_completed_order' => false,
                'fields' => ['reference_no', 'supplier_name', 'purchase_date', 'items_details', 'amount', 'status', 'created_by'],
            ],
            'Sales' => [
                'table' => 'sales_records', 'id' => 'sales_record_id',
                'requires_amount' => true, 'requires_order' => true, 'requires_completed_order' => true,
                'fields' => ['reference_no', 'order_id', 'sales_date', 'amount', 'status', 'description', 'created_by'],
            ],
            'Receipt' => [
                'table' => 'receipt_records', 'id' => 'receipt_record_id',
                'requires_amount' => true, 'requires_order' => true, 'requires_completed_order' => false,
                'fields' => ['reference_no', 'order_id', 'receipt_date', 'amount', 'status', 'description', 'created_by'],
            ],
            'Invoice' => [
                'table' => 'invoice_records', 'id' => 'invoice_record_id',
                'requires_amount' => true, 'requires_order' => true, 'requires_completed_order' => true,
                'fields' => ['reference_no', 'order_id', 'invoice_date', 'amount', 'status', 'description', 'created_by'],
            ],
            'Bidding Document' => [
                'table' => 'bidding_document_records', 'id' => 'bidding_document_record_id',
                'requires_amount' => false, 'requires_order' => false, 'requires_completed_order' => false,
                'fields' => ['reference_no', 'title', 'client_name', 'submission_date', 'status', 'description', 'created_by'],
            ],
        ];

        return $definitions[$type] ?? null;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    private function nullableString($value, int $maxLength): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }

    private function recordsQuery(): string
    {
        $receiptTaxDetails = $this->hasOrderTaxSnapshotSchema()
            ? "CASE WHEN o.tax_name_snapshot IS NOT NULL THEN CONCAT(
                   'Order total includes ', o.subtotal_before_tax_snapshot,
                   ' before tax; ', o.tax_name_snapshot, ' (',
                   FORMAT(o.tax_rate_snapshot, 2), '%) tax ', o.tax_amount_snapshot
               ) ELSE NULL END"
            : 'NULL';

        return "SELECT records.*,
            CASE WHEN records.record_type = 'Job Order' THEN (
                SELECT release_tx.transaction_date
                FROM stock_transactions release_tx
                WHERE release_tx.job_order_id = records.record_id
                  AND release_tx.transaction_type = 'Stock Out'
                LIMIT 1
            ) ELSE NULL END AS released_at
        FROM (
            SELECT 'Purchase' AS record_type,
                   pr.purchase_record_id AS record_id,
                   pr.reference_no,
                   pr.purchase_date AS record_date,
                   pr.items_details AS description,
                   pr.amount,
                   NULL AS order_id,
                   NULL AS customer_name,
                   pr.status,
                   u.full_name AS created_by_name,
                   pr.created_at,
                   pr.supplier_name,
                   NULL AS title,
                   NULL AS client_name,
                   NULL AS product_name,
                   NULL AS quantity,
                   NULL AS downpayment,
                   NULL AS delivery_method,
                   NULL AS customer_approval,
                   NULL AS production_assignment,
                   NULL AS production_start_date,
                   NULL AS production_end_date,
                   NULL AS item_name,
                   NULL AS specifications,
                   NULL AS production_completed_at
            FROM purchase_records pr
            LEFT JOIN users u ON u.user_id = pr.created_by

            UNION ALL

            SELECT 'Sales' AS record_type,
                   sr.sales_record_id AS record_id,
                   sr.reference_no,
                   sr.sales_date AS record_date,
                   sr.description,
                   sr.amount,
                   sr.order_id,
                   c.customer_name,
                   sr.status,
                   u.full_name AS created_by_name,
                   sr.created_at,
                   NULL AS supplier_name,
                   NULL AS title,
                   NULL AS client_name,
                   o.product_name,
                   o.quantity,
                   o.downpayment,
                   o.delivery_method,
                   NULL AS customer_approval,
                   NULL AS production_assignment,
                   NULL AS production_start_date,
                   NULL AS production_end_date,
                   NULL AS item_name,
                   o.specifications,
                   NULL AS production_completed_at
            FROM sales_records sr
            LEFT JOIN orders o ON o.order_id = sr.order_id
            LEFT JOIN customers c ON c.customer_id = o.customer_id
            LEFT JOIN users u ON u.user_id = sr.created_by

            UNION ALL

            -- Preserve the historical Sales view for completed Orders created
            -- after this migration; negative IDs mark these rows read-only.
            SELECT 'Sales' AS record_type,
                   -o.order_id AS record_id,
                   CONCAT('SALE-ORD-', o.order_id) AS reference_no,
                   o.order_date AS record_date,
                   CONCAT_WS(' | ', CONCAT('Product: ', o.product_name),
                       CONCAT('Quantity: ', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(o.quantity AS CHAR)))), NULLIF(o.specifications, ''),
                       CONCAT('Delivery: ', COALESCE(o.delivery_method, 'Not specified'))) AS description,
                   NULL AS amount,
                   o.order_id,
                   c.customer_name,
                   o.status,
                   NULL AS created_by_name,
                   o.created_at,
                   NULL AS supplier_name,
                   NULL AS title,
                   NULL AS client_name,
                   o.product_name,
                   o.quantity,
                   o.downpayment,
                   o.delivery_method,
                   NULL AS customer_approval,
                   NULL AS production_assignment,
                   NULL AS production_start_date,
                   NULL AS production_end_date,
                   NULL AS item_name,
                   o.specifications,
                   NULL AS production_completed_at
            FROM orders o
            INNER JOIN customers c ON c.customer_id = o.customer_id
            WHERE o.status = 'Completed'
              AND NOT EXISTS (
                  SELECT 1 FROM sales_records sr
                  WHERE sr.order_id = o.order_id
                     OR sr.reference_no = CONCAT('SALE-ORD-', o.order_id)
              )

            UNION ALL

            SELECT 'Receipt' AS record_type,
                   rr.receipt_record_id AS record_id,
                   rr.reference_no,
                   rr.receipt_date AS record_date,
                   CONCAT_WS(' · ', rr.payment_type, rr.description, $receiptTaxDetails) AS description,
                   rr.amount,
                   rr.order_id,
                   c.customer_name,
                   rr.status,
                   u.full_name AS created_by_name,
                   rr.created_at,
                   NULL AS supplier_name,
                   NULL AS title,
                   NULL AS client_name,
                   o.product_name,
                   o.quantity,
                   o.downpayment,
                   o.delivery_method,
                   NULL AS customer_approval,
                   NULL AS production_assignment,
                   NULL AS production_start_date,
                   NULL AS production_end_date,
                   NULL AS item_name,
                   o.specifications,
                   NULL AS production_completed_at
            FROM receipt_records rr
            LEFT JOIN orders o ON o.order_id = rr.order_id
            LEFT JOIN customers c ON c.customer_id = o.customer_id
            LEFT JOIN users u ON u.user_id = rr.created_by

            UNION ALL

            SELECT 'Invoice' AS record_type,
                   ir.invoice_record_id AS record_id,
                   ir.reference_no,
                   ir.invoice_date AS record_date,
                   ir.description,
                   ir.amount,
                   ir.order_id,
                   c.customer_name,
                   ir.status,
                   u.full_name AS created_by_name,
                   ir.created_at,
                   NULL AS supplier_name,
                   NULL AS title,
                   NULL AS client_name,
                   o.product_name,
                   o.quantity,
                   o.downpayment,
                   o.delivery_method,
                   NULL AS customer_approval,
                   NULL AS production_assignment,
                   NULL AS production_start_date,
                   NULL AS production_end_date,
                   NULL AS item_name,
                   o.specifications,
                   NULL AS production_completed_at
            FROM invoice_records ir
            LEFT JOIN orders o ON o.order_id = ir.order_id
            LEFT JOIN customers c ON c.customer_id = o.customer_id
            LEFT JOIN users u ON u.user_id = ir.created_by

            UNION ALL

            SELECT 'Bidding Document' AS record_type,
                   bdr.bidding_document_record_id AS record_id,
                   bdr.reference_no,
                   bdr.submission_date AS record_date,
                   bdr.description,
                   NULL AS amount,
                   NULL AS order_id,
                   NULL AS customer_name,
                   bdr.status,
                   u.full_name AS created_by_name,
                   bdr.created_at,
                   NULL AS supplier_name,
                   bdr.title,
                   bdr.client_name,
                   NULL AS product_name,
                   NULL AS quantity,
                   NULL AS downpayment,
                   NULL AS delivery_method,
                   NULL AS customer_approval,
                   NULL AS production_assignment,
                   NULL AS production_start_date,
                   NULL AS production_end_date,
                   NULL AS item_name,
                   NULL AS specifications,
                   NULL AS production_completed_at
            FROM bidding_document_records bdr
            LEFT JOIN users u ON u.user_id = bdr.created_by

            UNION ALL

            SELECT 'Order' AS record_type,
                   o.order_id AS record_id,
                   CONCAT('ORD-', LPAD(o.order_id, GREATEST(4, CHAR_LENGTH(o.order_id)), '0')) AS reference_no,
                   o.order_date AS record_date,
                   CONCAT_WS(' | ', CONCAT('Product: ', o.product_name),
                       CONCAT('Quantity: ', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(o.quantity AS CHAR)))), NULLIF(o.specifications, ''),
                       CONCAT('Downpayment: ', o.downpayment),
                       CONCAT('Delivery: ', COALESCE(o.delivery_method, 'Not specified'))) AS description,
                   o.downpayment AS amount,
                   o.order_id,
                   c.customer_name,
                   o.status,
                   NULL AS created_by_name,
                   o.created_at,
                   NULL AS supplier_name,
                   NULL AS title,
                   NULL AS client_name,
                   o.product_name,
                   o.quantity,
                   o.downpayment,
                   o.delivery_method,
                   NULL AS customer_approval,
                   NULL AS production_assignment,
                   NULL AS production_start_date,
                   NULL AS production_end_date,
                   NULL AS item_name,
                   o.specifications,
                   NULL AS production_completed_at
            FROM orders o
            INNER JOIN customers c ON c.customer_id = o.customer_id

            UNION ALL

            SELECT 'Stock In' AS record_type,
                   st.transaction_id AS record_id,
                   st.reference_no,
                   DATE(st.transaction_date) AS record_date,
                   CONCAT_WS(' | ', CONCAT('Item: ', i.item_name),
                       CONCAT('Quantity: ', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(st.quantity AS CHAR)))), NULLIF(st.remarks, '')) AS description,
                   NULL AS amount,
                   NULL AS order_id,
                   NULL AS customer_name,
                   st.transaction_type AS status,
                   u.full_name AS created_by_name,
                   st.transaction_date AS created_at,
                   NULL AS supplier_name,
                   NULL AS title,
                   NULL AS client_name,
                   NULL AS product_name,
                   st.quantity,
                   NULL AS downpayment,
                   NULL AS delivery_method,
                   NULL AS customer_approval,
                   NULL AS production_assignment,
                   NULL AS production_start_date,
                   NULL AS production_end_date,
                   i.item_name,
                   NULL AS specifications,
                   NULL AS production_completed_at
            FROM stock_transactions st
            INNER JOIN inventory_items i ON i.inventory_id = st.inventory_id
            LEFT JOIN users u ON u.user_id = st.user_id
            WHERE st.transaction_type = 'Stock In'

            UNION ALL

            SELECT 'Stock Out' AS record_type,
                   st.transaction_id AS record_id,
                   st.reference_no,
                   DATE(st.transaction_date) AS record_date,
                   CONCAT_WS(' | ', CONCAT('Item: ', i.item_name),
                       CONCAT('Quantity: ', TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(st.quantity AS CHAR)))),
                       IF(st.order_id IS NOT NULL, CONCAT('Order No.: ORD-', LPAD(st.order_id, GREATEST(4, CHAR_LENGTH(st.order_id)), '0')), NULL),
                       IF(st.job_order_id IS NOT NULL, CONCAT('Job Order No.: JO-', LPAD(st.job_order_id, GREATEST(4, CHAR_LENGTH(st.job_order_id)), '0')), NULL),
                       NULLIF(st.remarks, '')) AS description,
                   NULL AS amount,
                   st.order_id,
                   NULL AS customer_name,
                   st.transaction_type AS status,
                   u.full_name AS created_by_name,
                   st.transaction_date AS created_at,
                   NULL AS supplier_name,
                   NULL AS title,
                   NULL AS client_name,
                   NULL AS product_name,
                   st.quantity,
                   NULL AS downpayment,
                   NULL AS delivery_method,
                   NULL AS customer_approval,
                   NULL AS production_assignment,
                   NULL AS production_start_date,
                   NULL AS production_end_date,
                   i.item_name,
                   NULL AS specifications,
                   NULL AS production_completed_at
            FROM stock_transactions st
            INNER JOIN inventory_items i ON i.inventory_id = st.inventory_id
            LEFT JOIN users u ON u.user_id = st.user_id
            WHERE st.transaction_type = 'Stock Out'

            UNION ALL

            SELECT 'Job Order' AS record_type,
                   j.job_order_id AS record_id,
                   CONCAT('JO-', LPAD(j.job_order_id, GREATEST(4, CHAR_LENGTH(j.job_order_id)), '0')) AS reference_no,
                   DATE(j.created_at) AS record_date,
                   CONCAT_WS(' | ', CONCAT('Order No.: ORD-', LPAD(o.order_id, GREATEST(4, CHAR_LENGTH(o.order_id)), '0')),
                       CONCAT('Product: ', o.product_name),
                       CONCAT('Approval: ', j.customer_approval),
                       CONCAT('Assignment: ', COALESCE(j.production_assignment, 'Not assigned'))) AS description,
                   NULL AS amount,
                   o.order_id,
                   c.customer_name,
                   j.status,
                   NULL AS created_by_name,
                   j.created_at,
                   NULL AS supplier_name,
                   NULL AS title,
                   NULL AS client_name,
                   o.product_name,
                   o.quantity,
                   o.downpayment,
                   o.delivery_method,
                   j.customer_approval,
                   j.production_assignment,
                   j.production_start_date,
                   j.production_end_date,
                   NULL AS item_name,
                   o.specifications,
                   j.production_completed_at
            FROM job_orders j
            INNER JOIN orders o ON o.order_id = j.order_id
            INNER JOIN customers c ON c.customer_id = o.customer_id
        ) AS records";
    }

    private function hasOrderTaxSnapshotSchema(): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = \'orders\'
                   AND COLUMN_NAME IN (\'subtotal_before_tax_snapshot\', \'tax_name_snapshot\',
                       \'tax_rate_snapshot\', \'tax_amount_snapshot\')'
            );
            $stmt->execute();
            return (int) $stmt->fetchColumn() === 4;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
