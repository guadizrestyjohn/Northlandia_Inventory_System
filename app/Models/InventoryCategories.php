<?php

namespace Models;

use Core\Model;

class InventoryCategories extends Model
{
    private $table = 'inventory_categories';

    public function find()
    {
        $stmt = $this->db->prepare(
            'SELECT category_id, category_name
             FROM ' . $this->table . '
             ORDER BY category_name ASC'
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT category_id, category_name FROM ' . $this->table . ' WHERE category_id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }
}
