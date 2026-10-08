<?php

namespace Models;

use Core\Model;

class Customers extends Model
{
    private $table = 'customers';

    public function find()
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM ' . $this->table . '
             ORDER BY customer_id DESC'
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findByID($id)
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM ' . $this->table . '
             WHERE customer_id = ?'
        );

        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function searchForOrders(string $query): array
    {
        $stmt = $this->db->prepare(
            'SELECT customer_id, customer_name, contact_number
             FROM ' . $this->table . '
             WHERE customer_name LIKE ? OR contact_number LIKE ?
             ORDER BY customer_name ASC
             LIMIT 20'
        );
        $term = '%' . $query . '%';
        $stmt->execute([$term, $term]);

        return $stmt->fetchAll();
    }

    public function eligibleForOrders(): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.customer_id, c.customer_name, c.contact_number
             FROM ' . $this->table . ' c
             WHERE NOT EXISTS (
                    SELECT 1
                    FROM orders o
                    WHERE o.customer_id = c.customer_id
                )
             ORDER BY c.customer_id DESC
             LIMIT 20'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function create(
        $customer_name,
        $contact_number,
        $email,
        $address
    )
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ' . $this->table . '
                (customer_name, contact_number, email, address)
             VALUES
                (?, ?, ?, ?)'
        );

        return $stmt->execute([
            $customer_name,
            $contact_number,
            $email,
            $address
        ]);
    }

    public function update(
        $customer_id,
        $customer_name,
        $contact_number,
        $email,
        $address
    )
    {
        $stmt = $this->db->prepare(
            'UPDATE ' . $this->table . '
             SET
                customer_name = ?,
                contact_number = ?,
                email = ?,
                address = ?
             WHERE customer_id = ?'
        );

        return $stmt->execute([
            $customer_name,
            $contact_number,
            $email,
            $address,
            $customer_id
        ]);
    }
}
