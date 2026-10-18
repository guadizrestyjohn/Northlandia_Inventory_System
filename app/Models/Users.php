<?php

namespace Models;

use Core\Model;
use PDOException;

class Users extends Model
{
    public function find(): array
    {
        $stmt = $this->db->query(
            'SELECT u.user_id, u.username, u.full_name, u.role_id, r.role_name,
                    u.production_team, u.status
             FROM users u
             INNER JOIN roles r ON r.role_id = u.role_id
             ORDER BY u.user_id DESC'
        );

        return $stmt->fetchAll();
    }

    public function findRoles(): array
    {
        return $this->db->query('SELECT role_id, role_name FROM roles ORDER BY role_id')->fetchAll();
    }

    public function findRoleByID(int $roleId): ?object
    {
        $stmt = $this->db->prepare('SELECT role_id, role_name FROM roles WHERE role_id = ?');
        $stmt->execute([$roleId]);

        return $stmt->fetch() ?: null;
    }

    public function productionTeams(): array
    {
        $teams = $this->db->query(
            "SELECT DISTINCT u.production_team
             FROM users u
             INNER JOIN roles r ON r.role_id = u.role_id
             WHERE r.role_name = 'Production Staff'
               AND u.status = 'Active'
               AND u.production_team IS NOT NULL
               AND TRIM(u.production_team) <> ''
             ORDER BY u.production_team"
        )->fetchAll();

        $result = [];
        foreach ($teams as $team) {
            $name = trim((string) $team->production_team);
            if ($name !== '' && !in_array($name, $result, true)) {
                $result[] = $name;
            }
        }

        return $result;
    }

    public function productionTeamExists(string $team): bool
    {
        $team = trim($team);
        if ($team === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT 1
             FROM users u
             INNER JOIN roles r ON r.role_id = u.role_id
             WHERE r.role_name = 'Production Staff'
               AND u.status = 'Active'
               AND u.production_team = ?
             LIMIT 1"
        );
        $stmt->execute([$team]);

        return (bool) $stmt->fetchColumn();
    }

    public function findByID(int $userId): ?object
    {
        $stmt = $this->db->prepare(
            'SELECT u.user_id, u.username, u.full_name, u.role_id, r.role_name,
                    u.production_team, u.status
             FROM users u
             INNER JOIN roles r ON r.role_id = u.role_id
             WHERE u.user_id = ?'
        );
        $stmt->execute([$userId]);

        return $stmt->fetch() ?: null;
    }

    public function usernameExists(string $username, ?int $excludeUserId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE username = ?';
        $params = [$username];
        if ($excludeUserId !== null) {
            $sql .= ' AND user_id <> ?';
            $params[] = $excludeUserId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data): array
    {
        if (!$this->findRoleByID((int) $data['role_id'])) {
            return ['success' => false, 'message' => 'Select an existing role.', 'status' => 422];
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (username, password, full_name, role_id, production_team, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['username'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['full_name'],
                (int) $data['role_id'],
                $data['production_team'] !== '' ? $data['production_team'] : null,
                $data['status'],
            ]);

            return ['success' => true, 'user_id' => (int) $this->db->lastInsertId()];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'That username is already in use.', 'status' => 409];
            }

            return ['success' => false, 'message' => 'Unable to create this user right now.', 'status' => 500];
        }
    }

    public function update(int $userId, array $data): array
    {
        try {
            $this->db->beginTransaction();
            $current = $this->lockUser($userId);
            if (!$current) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'User not found.', 'status' => 404];
            }
            if (!$this->findRoleByID((int) $data['role_id'])) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Select an existing role.', 'status' => 422];
            }
            if ($this->usernameExists($data['username'], $userId)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'That username is already in use.', 'status' => 409];
            }
            if ($this->wouldRemoveLastAdministrator($current, (int) $data['role_id'], $data['status'])) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'The last active Administrator cannot be deactivated or reassigned.', 'status' => 409];
            }

            $sql = 'UPDATE users SET username = ?, full_name = ?, role_id = ?, production_team = ?, status = ?';
            $params = [
                $data['username'], $data['full_name'], (int) $data['role_id'],
                $data['production_team'] !== '' ? $data['production_team'] : null,
                $data['status'],
            ];
            if (($data['password'] ?? '') !== '') {
                $sql .= ', password = ?';
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE user_id = ?';
            $params[] = $userId;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $this->db->commit();

            return ['success' => true];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'That username is already in use.', 'status' => 409];
            }
            return ['success' => false, 'message' => 'Unable to update this user right now.', 'status' => 500];
        }
    }

    public function updateStatus(int $userId, string $status): array
    {
        if (!in_array($status, ['Active', 'Inactive'], true)) {
            return ['success' => false, 'message' => 'Select a valid user status.', 'status' => 422];
        }

        try {
            $this->db->beginTransaction();
            $current = $this->lockUser($userId);
            if (!$current) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'User not found.', 'status' => 404];
            }
            if ($this->wouldRemoveLastAdministrator($current, (int) $current->role_id, $status)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'The last active Administrator cannot be deactivated.', 'status' => 409];
            }

            $stmt = $this->db->prepare('UPDATE users SET status = ? WHERE user_id = ?');
            $stmt->execute([$status, $userId]);
            $this->db->commit();

            return ['success' => true];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['success' => false, 'message' => 'Unable to update this user status right now.', 'status' => 500];
        }
    }

    private function lockUser(int $userId): ?object
    {
        $stmt = $this->db->prepare(
            'SELECT u.user_id, u.role_id, r.role_name, u.status
             FROM users u INNER JOIN roles r ON r.role_id = u.role_id
             WHERE u.user_id = ? FOR UPDATE'
        );
        $stmt->execute([$userId]);

        return $stmt->fetch() ?: null;
    }

    private function wouldRemoveLastAdministrator(object $current, int $newRoleId, string $newStatus): bool
    {
        $administrator = $this->db->query("SELECT role_id FROM roles WHERE role_name = 'Administrator'")->fetchColumn();
        if ($administrator === false || $current->role_name !== 'Administrator' || $current->status !== 'Active') {
            return false;
        }
        if ($newStatus === 'Active' && $newRoleId === (int) $administrator) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT u.user_id
             FROM users u
             WHERE u.role_id = ? AND u.status = \'Active\'
             FOR UPDATE'
        );
        $stmt->execute([(int) $administrator]);

        return count($stmt->fetchAll()) <= 1;
    }

    public function findByUsername($username)
    {
        $stmt = $this->db->prepare(
            'SELECT
                u.user_id,
                u.username,
                u.password,
                u.full_name,
                u.role_id,
                r.role_name,
                u.production_team,
                u.status
             FROM users u
             INNER JOIN roles r
                ON u.role_id = r.role_id
             WHERE u.username = ?'
        );

        $stmt->execute([$username]);

        return $stmt->fetch() ?: null;
    }

}
