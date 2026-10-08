<?php

namespace Models;

use Core\Model;
use PDO;

class Dashboard extends Model
{
    public function counts(): array
    {
        $customers = (int) $this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
        $inventory = (int) $this->db->query('SELECT COUNT(*) FROM inventory_items')->fetchColumn();
        $orders = (int) $this->db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $jobOrders = (int) $this->db->query('SELECT COUNT(*) FROM job_orders')->fetchColumn();

        return [
            'customers' => $customers,
            'inventory' => $inventory,
            'orders' => $orders,
            'jobOrders' => $jobOrders,
        ];
    }

    public function chartData(): array
    {
        return [
            'trend' => $this->ordersTrend(7),
            'orderStatus' => $this->orderStatusBreakdown(),
            'inventoryStatus' => $this->inventoryStatusBreakdown(),
            'recentOrders' => $this->recentOrders(5),
            'recentTransactions' => $this->recentTransactions(5),
        ];
    }

    public function ordersTrend(int $days = 7): array
    {
        $days = max(1, $days);
        $stmt = $this->db->prepare(
            'SELECT DATE(order_date) AS label, COUNT(*) AS total
             FROM orders
             WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND order_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
             GROUP BY DATE(order_date)
             ORDER BY label ASC'
        );
        $stmt->bindValue(1, $days - 1, PDO::PARAM_INT);
        $stmt->execute();

        $totalsByDate = [];
        foreach ($stmt->fetchAll() as $row) {
            $totalsByDate[(string) $row->label] = (int) $row->total;
        }

        $startDate = (new \DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');
        $trend = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $date = $startDate->modify('+' . $offset . ' days');
            $dateKey = $date->format('Y-m-d');
            $trend[] = [
                'label' => $date->format('M d'),
                'total' => $totalsByDate[$dateKey] ?? 0,
            ];
        }

        return $trend;
    }

    public function orderStatusBreakdown(): array
    {
        $stmt = $this->db->query(
            'SELECT status, COUNT(*) AS total
             FROM orders
             GROUP BY status
             ORDER BY total DESC, status ASC'
        );

        $rows = $stmt->fetchAll();
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'label' => (string) $row->status,
                'value' => (int) $row->total,
            ];
        }

        return $data;
    }

    public function inventoryStatusBreakdown(): array
    {
        $stmt = $this->db->query(
            'SELECT status, COUNT(*) AS total
             FROM inventory_items
             GROUP BY status
             ORDER BY total DESC, status ASC'
        );

        $rows = $stmt->fetchAll();
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'label' => (string) $row->status,
                'value' => (int) $row->total,
            ];
        }

        return $data;
    }

    public function recentOrders(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.order_id, o.order_date, o.product_name, o.status, c.customer_name
             FROM orders o
             LEFT JOIN customers c ON c.customer_id = o.customer_id
             ORDER BY o.order_id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $records = [];
        foreach ($rows as $row) {
            $records[] = [
                'order_id' => (int) $row->order_id,
                'order_date' => (string) $row->order_date,
                'product_name' => (string) $row->product_name,
                'status' => (string) $row->status,
                'customer_name' => (string) ($row->customer_name ?? ''),
            ];
        }

        return $records;
    }

    public function recentTransactions(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT st.transaction_id, st.transaction_type, st.quantity, st.transaction_date,
                    i.item_name, u.full_name
             FROM stock_transactions st
             INNER JOIN inventory_items i ON i.inventory_id = st.inventory_id
             LEFT JOIN users u ON u.user_id = st.user_id
             ORDER BY st.transaction_id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $records = [];
        foreach ($rows as $row) {
            $records[] = [
                'transaction_id' => (int) $row->transaction_id,
                'transaction_type' => (string) $row->transaction_type,
                'quantity' => (string) $row->quantity,
                'transaction_date' => (string) $row->transaction_date,
                'item_name' => (string) $row->item_name,
                'full_name' => (string) ($row->full_name ?? ''),
            ];
        }

        return $records;
    }
}