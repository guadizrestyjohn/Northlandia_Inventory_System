<?php

namespace Api\Users;

use Api\BaseApiController;
use Core\Auth;
use Models\Users;

class UsersController extends BaseApiController
{
    private $Users;

    public function __construct()
    {
        $this->Users = new Users();
    }

    public function all(): void
    {
        $this->requirePermission('users.manage');
        $this->requireGet();
        $this->respond([
            'data' => $this->Users->find(),
        ]);
    }

    public function add(): void
    {
        $this->requirePermission('users.manage');
        $this->requirePost();
        $data = $this->input();
        $error = $this->validate($data, true);
        if ($error !== null) {
            $this->respondError($error, 422);
        }

        $result = $this->Users->create($data);
        if (!empty($result['success']) && empty($result['message'])) {
            $result['message'] = 'User added successfully.';
        }
        $this->respondResult($result, 500, 201);
    }

    public function edit($id): void
    {
        $this->requirePermission('users.manage');
        $this->requireGetOrPost();
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $this->respondError('User not found.', 404);
        }

        $userId = (int) $id;
        $user = $this->Users->findByID($userId);
        if (!$user) {
            $this->respondError('User not found.', 404);
        }
        if (!Auth::canManageUserRole((string) $user->role_name)) {
            $this->respondError('You are not authorized to manage this user.', 403);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $this->respond($user);
        }

        $data = $this->input();
        $error = $this->validate($data, false, $userId);
        if ($error !== null) {
            $this->respondError($error, 422);
        }
        if ($userId === (int) (Auth::user()['id'] ?? 0)
            && (int) $data['role_id'] !== (int) (Auth::user()['role_id'] ?? 0)) {
            $this->respondError('Ask another Administrator to change your own role.', 403);
        }
        $result = $this->Users->update($userId, $data);
        if (empty($result['success'])) {
            $this->respondResult($result);
        }

        if ($userId === (int) (Auth::user()['id'] ?? 0) && $data['status'] === 'Inactive') {
            Auth::logout();
            $this->respond([
                'status' => 'success',
                'message' => 'Your account was deactivated and you have been signed out.',
                'signed_out' => true,
            ]);
        }
        $user = $this->Users->findByID($userId);
        if ($user) Auth::refreshCurrentUser($user);
        $this->respond(['status' => 'success', 'message' => 'User updated successfully.']);
    }

    public function status($id): void
    {
        $this->requirePermission('users.manage');
        $this->requirePost();
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $this->respondError('User not found.', 404);
        }

        $status = $this->inputValue('status');
        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $this->respondError('Select a valid user status.', 422);
        }

        $currentUserId = (int) (Auth::user()['id'] ?? 0);
        $userId = (int) $id;
        $user = $this->Users->findByID($userId);
        if (!$user) {
            $this->respondError('User not found.', 404);
        }
        if (!Auth::canManageUserRole((string) $user->role_name)) {
            $this->respondError('You are not authorized to manage this user.', 403);
        }
        $result = $this->Users->updateStatus($userId, $status);
        if (empty($result['success'])) {
            $this->respondResult($result);
        }

        if ($currentUserId === $userId && $status === 'Inactive') {
            Auth::logout();
            $this->respond([
                'status' => 'success',
                'message' => 'Your account was deactivated and you have been signed out.',
                'signed_out' => true,
            ]);
        }

        $this->respond(['status' => 'success', 'message' => 'User status updated successfully.']);
    }

    private function input(): array
    {
        return [
            'username' => $this->inputValue('username'),
            'full_name' => $this->inputValue('full_name'),
            'password' => $this->inputValue('password', false),
            'role_id' => $this->inputValue('role_id'),
            'status' => $this->inputValue('status'),
        ];
    }

    private function inputValue(string $key, bool $trim = true): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_scalar($value)) return '';
        $value = (string) $value;
        return $trim ? trim($value) : $value;
    }

    private function validate(array $data, bool $creating, ?int $userId = null): ?string
    {
        if ($data['username'] === '' || mb_strlen($data['username']) > 50) {
            return 'Username is required and must be 50 characters or fewer.';
        }
        if ($data['full_name'] === '' || mb_strlen($data['full_name']) > 100) {
            return 'Full Name is required and must be 100 characters or fewer.';
        }
        if ($creating && $data['password'] === '') {
            return 'Password is required when creating a user.';
        }
        if ($data['password'] !== '' && (mb_strlen($data['password']) < 8 || mb_strlen($data['password']) > 255)) {
            return 'Password must be between 8 and 255 characters.';
        }
        if (!ctype_digit($data['role_id']) || (int) $data['role_id'] < 1) {
            return 'Select an existing role.';
        }
        $role = $this->Users->findRoleByID((int) $data['role_id']);
        if (!$role) {
            return 'Select an existing role.';
        }
        if (!Auth::canAssignRole((string) $role->role_name)) {
            return 'You are not authorized to assign this role.';
        }
        if (!in_array($data['status'], ['Active', 'Inactive'], true)) {
            return 'Select a valid user status.';
        }
        if ($this->Users->usernameExists($data['username'], $userId)) {
            return 'That username is already in use.';
        }

        return null;
    }
}
