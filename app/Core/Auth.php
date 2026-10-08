<?php

namespace Core;

use Models\Users;

class Auth{

    private const PERMISSIONS = [
        'Administrator' => ['*'],
        'Manager' => [
            'dashboard.view', 'customers.view', 'orders.view', 'orders.payment', 'job_orders.view',
            'inventory.view', 'stock.view', 'stock.out', 'records.view', 'records.manage',
            'reports.view',
        ],
        'Sales Staff' => [
            'dashboard.view', 'customers.view', 'customers.manage',
            'orders.view', 'orders.create', 'orders.update', 'orders.payment',
            'job_orders.view', 'job_orders.create', 'job_orders.approval', 'job_orders.endorse',
            'records.view',
        ],
        'Production Staff' => [
            'dashboard.view', 'job_orders.view', 'production.timeframe', 'production.complete',
        ],
        'Inventory Staff' => [
            'dashboard.view', 'inventory.view', 'inventory.manage', 'stock.view', 'stock.in', 'stock.out',
            'records.view',
        ],
        'Administrative Staff' => [
            'dashboard.view', 'orders.view', 'orders.payment', 'records.view', 'records.manage',
        ],
    ];

    public static function attemptLogin(string $username, string $password, $users): array
    {
        $user = $users->findByUsername($username);
        if (!$user || !password_verify($password, $user->password)) {
            return ['success' => false, 'status' => 401, 'message' => 'Invalid username or password.'];
        }
        if ($user->status !== 'Active') {
            return ['success' => false, 'status' => 403, 'message' => 'This account is inactive.'];
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user->user_id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'role' => $user->role_name,
            'role_id' => $user->role_id,
        ];
        return ['success' => true, 'status' => 200, 'message' => 'Login successful.'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId < 1) {
            return false;
        }

        try {
            $user = (new Users())->findByID($userId);
        } catch (\Throwable $e) {
            return false;
        }

        if (!$user || $user->status !== 'Active') {
            self::logout();
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int) $user->user_id,
            'username' => (string) $user->username,
            'full_name' => (string) $user->full_name,
            'role' => (string) $user->role_name,
            'role_id' => (int) $user->role_id,
        ];

        return true;
    }

    public static function user()
    {
        return $_SESSION['user'] ?? null;
    }

    public static function refreshCurrentUser(object $user): void
    {
        if ((int) (self::user()['id'] ?? 0) !== (int) $user->user_id) {
            return;
        }
        if (($user->status ?? '') !== 'Active') {
            self::logout();
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user->user_id,
            'username' => (string) $user->username,
            'full_name' => (string) $user->full_name,
            'role' => (string) $user->role_name,
            'role_id' => (int) $user->role_id,
        ];
    }

    public static function canStockOut(): bool
    {
        return self::can('stock.out');
    }

    public static function can(string $permission): bool
    {
        $role = self::user()['role'] ?? '';
        $permissions = self::PERMISSIONS[$role] ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public static function canAssignRole(string $roleName): bool
    {
        if (!self::can('users.manage')) {
            return false;
        }

        $actorRole = self::user()['role'] ?? '';
        if ($actorRole === 'Administrator') {
            return $roleName !== '';
        }

        return $actorRole === 'Manager' && in_array($roleName, self::ordinaryStaffRoles(), true);
    }

    public static function canManageUserRole(string $targetRoleName): bool
    {
        if (!self::can('users.manage')) {
            return false;
        }

        $actorRole = self::user()['role'] ?? '';
        if ($actorRole === 'Administrator') {
            return true;
        }

        return $actorRole === 'Manager' && in_array($targetRoleName, self::ordinaryStaffRoles(), true);
    }

    private static function ordinaryStaffRoles(): array
    {
        return ['Sales Staff', 'Production Staff', 'Inventory Staff', 'Administrative Staff'];
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            http_response_code(403);
            exit('You are not authorized to perform this action.');
        }
    }

    public static function canAccessRecords(): bool
    {
        return self::can('records.view');
    }

    public static function canManageRecords(): bool
    {
        return self::can('records.manage');
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/Auth');
            exit;
        }
    }

}
