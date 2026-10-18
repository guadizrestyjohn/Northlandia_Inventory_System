<?php

namespace Models;

use Core\Model;

class Reports extends Model
{
    public function inventory(array $filters): array
    {
        $sql = 'SELECT i.inventory_id, i.item_name, i.item_description, i.size, i.width_cm, i.height_cm,
                       i.quantity, i.total_stocks, i.unit,
                       i.status, i.category_id, c.category_name
                FROM inventory_items i
                INNER JOIN inventory_categories c ON c.category_id = i.category_id
                WHERE 1 = 1';
        $params = [];

        if ($filters['category_id'] !== '') {
            $sql .= ' AND i.category_id = ?';
            $params[] = $filters['category_id'];
        }
        if ($filters['status'] !== '') {
            $sql .= ' AND i.status = ?';
            $params[] = $filters['status'];
        }
        if ($filters['keyword'] !== '') {
            $sql .= ' AND (i.item_name LIKE ? OR i.item_description LIKE ? OR i.size LIKE ?
                           OR CAST(i.width_cm AS CHAR) LIKE ? OR CAST(i.height_cm AS CHAR) LIKE ? OR c.category_name LIKE ?)';
            $term = '%' . $filters['keyword'] . '%';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        $sql .= ' ORDER BY i.inventory_id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function movements(array $filters): array
    {
        $sql = 'SELECT st.transaction_id, st.inventory_id, i.item_name,
                       st.transaction_type, st.quantity, st.reference_no,
                       st.order_id, st.job_order_id, st.production_output_id, st.release_id, st.remarks,
                       st.transaction_date, st.user_id, u.full_name, u.username
                FROM stock_transactions st
                INNER JOIN inventory_items i ON i.inventory_id = st.inventory_id
                LEFT JOIN users u ON u.user_id = st.user_id
                WHERE 1 = 1';
        $params = [];

        if ($filters['status'] !== '') {
            $sql .= ' AND st.transaction_type = ?';
            $params[] = $filters['status'];
        }
        if ($filters['start_date'] !== '') {
            $sql .= ' AND st.transaction_date >= ?';
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if ($filters['end_date'] !== '') {
            $sql .= ' AND st.transaction_date < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $filters['end_date'];
        }
        if ($filters['keyword'] !== '') {
            $sql .= ' AND (i.item_name LIKE ? OR st.reference_no LIKE ?
                           OR st.remarks LIKE ? OR u.full_name LIKE ?
                           OR u.username LIKE ? OR CAST(st.order_id AS CHAR) LIKE ?
                           OR CAST(st.job_order_id AS CHAR) LIKE ?)';
            $term = '%' . $filters['keyword'] . '%';
            array_push($params, $term, $term, $term, $term, $term, $term, $term);
        }

        $sql .= ' ORDER BY st.transaction_date DESC, st.transaction_id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function orders(array $filters): array
    {
        $sql = 'SELECT o.order_id, o.customer_id, c.customer_name, o.order_date,
                       o.product_name, o.quantity, o.order_total, o.downpayment,
                       COALESCE(paid.additional_paid, 0) AS additional_paid,
                       COALESCE(o.downpayment, 0) + COALESCE(paid.additional_paid, 0) AS total_paid,
                       o.order_total - COALESCE(o.downpayment, 0)
                           - COALESCE(paid.additional_paid, 0) AS payment_remaining,
                       o.delivery_method, o.status AS order_status,
                       j.job_order_id, j.production_assignment,
                       j.production_start_date, j.production_end_date,
                       j.customer_approval, j.status AS job_order_status
                FROM orders o
                INNER JOIN customers c ON c.customer_id = o.customer_id
                LEFT JOIN job_orders j ON j.order_id = o.order_id
                LEFT JOIN (
                    SELECT order_id, SUM(amount) AS additional_paid
                    FROM receipt_records
                    WHERE payment_type = \'Additional\' AND status = \'Recorded\'
                    GROUP BY order_id
                ) paid ON paid.order_id = o.order_id
                WHERE 1 = 1';
        $params = [];

        if ($filters['status'] !== '') {
            $sql .= ' AND (o.status = ? OR j.status = ?)';
            $params[] = $filters['status'];
            $params[] = $filters['status'];
        }
        if ($filters['start_date'] !== '') {
            $sql .= ' AND o.order_date >= ?';
            $params[] = $filters['start_date'];
        }
        if ($filters['end_date'] !== '') {
            $sql .= ' AND o.order_date <= ?';
            $params[] = $filters['end_date'];
        }
        if ($filters['customer'] !== '') {
            $sql .= ' AND c.customer_name LIKE ?';
            $params[] = '%' . $filters['customer'] . '%';
        }
        if ($filters['keyword'] !== '') {
            $sql .= ' AND (c.customer_name LIKE ? OR o.product_name LIKE ?
                           OR CAST(o.order_id AS CHAR) LIKE ?
                           OR CAST(j.job_order_id AS CHAR) LIKE ?)';
            $term = '%' . $filters['keyword'] . '%';
            array_push($params, $term, $term, $term, $term);
        }

        $sql .= ' ORDER BY o.order_date DESC, o.order_id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
