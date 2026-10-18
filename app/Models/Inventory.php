<?php

namespace Models;

use Core\Model;

class Inventory extends Model
{
    public function find()
    {
        $stmt = $this->db->query(
            'SELECT i.inventory_id, i.item_name, i.item_description, i.size, i.width_cm, i.height_cm,
                    i.quantity, i.total_stocks, i.unit, i.status,
                    i.category_id, c.category_name
             FROM inventory_items i
             LEFT JOIN inventory_categories c ON c.category_id = i.category_id
             ORDER BY i.inventory_id DESC'
        );

        return $stmt->fetchAll();
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM inventory_items WHERE inventory_id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function create($data)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO inventory_items
                (item_name, item_description, size, width_cm, height_cm, category_id,
                 quantity, total_stocks, unit, status)
             VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, 'Out of Stock')"
        );

        return $stmt->execute([
            $data['item_name'], $data['item_description'], $data['size'],
            $data['width_cm'], $data['height_cm'], $data['category_id'], $data['unit']
        ]);
    }

    public function update($id, $data)
    {
        try {
            $this->db->beginTransaction();
            $lock = $this->db->prepare('SELECT inventory_id FROM inventory_items WHERE inventory_id = ? FOR UPDATE');
            $lock->execute([$id]);
            $current = $lock->fetch();
            if (!$current) {
                $this->db->rollBack();
                return false;
            }
            $stmt = $this->db->prepare(
                'UPDATE inventory_items
                 SET item_name = ?, item_description = ?, size = ?, width_cm = ?, height_cm = ?, category_id = ?, unit = ?
                 WHERE inventory_id = ?'
            );
            $success = $stmt->execute([
                $data['item_name'], $data['item_description'], $data['size'],
                $data['width_cm'], $data['height_cm'], $data['category_id'], $data['unit'], $id
            ]);
            $this->db->commit();
            return $success;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Inventory update failed.');
            return false;
        }
    }
}
