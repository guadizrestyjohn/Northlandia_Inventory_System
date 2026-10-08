<?php

namespace Models;

use Core\Model;

class Inventory extends Model
{
    public function find()
    {
        $stmt = $this->db->query(
            'SELECT i.inventory_id, i.item_name, i.size, i.quantity, i.unit, i.status,
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
                (item_name, size, category_id, quantity, unit, status)
             VALUES (?, ?, ?, 0, ?, 'Out of Stock')"
        );

        return $stmt->execute([
            $data['item_name'], $data['size'], $data['category_id'], $data['unit']
        ]);
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare(
            'UPDATE inventory_items
             SET item_name = ?, size = ?, category_id = ?, unit = ?
             WHERE inventory_id = ?'
        );

        return $stmt->execute([
            $data['item_name'], $data['size'], $data['category_id'], $data['unit'], $id
        ]);
    }
}
