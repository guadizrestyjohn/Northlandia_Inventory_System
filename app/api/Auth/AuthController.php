<?php

namespace Api\Auth;

use Core\BaseController;
use Core\Auth;
use Models\Users;

class AuthController extends BaseController
{
    private $Users;

    public function __construct()
    {
        $this->Users = new Users();
    }

    public function login(): void
    {
        $this->requirePost();
        $username = $this->postString('username');
        $password = $this->postString('password', false);
        if ($username === '' || $password === '') {
            $this->json(['status' => 'error', 'message' => 'Username and password are required.']);
        }
        $result = Auth::attemptLogin($username, $password, $this->Users);
        if (!empty($result['success'])) {
            $user = Auth::user() ?? [];
            $result['user'] = [
                'user_id' => (int) ($user['id'] ?? 0),
                'username' => (string) ($user['username'] ?? ''),
                'full_name' => (string) ($user['full_name'] ?? ''),
                'role_id' => (int) ($user['role_id'] ?? 0),
                'role_name' => (string) ($user['role'] ?? ''),
                'permissions' => Auth::permissions(),
            ];
        }
        $this->respondResult($result);
    }

    public function logout(): void
    {
        $this->requirePost();
        Auth::logout();
        $this->json(['status' => 'success', 'message' => 'Logged out successfully.']);
    }

    private function respondResult(array $result): void
    {
        $successful = !empty($result['success']);
        unset($result['success'], $result['status']);
        if (empty($result['message'])) {
            $result['message'] = $successful
                ? 'Operation completed successfully.'
                : 'Unable to complete the operation.';
        }
        $result['status'] = $successful ? 'success' : 'error';
        $this->json($result);
    }

    private function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'POST is required.']);
        }
    }

    private function postString(string $key, bool $trim = true): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;
        return $trim ? trim($value) : $value;
    }
}
