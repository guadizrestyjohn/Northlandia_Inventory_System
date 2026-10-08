<?php

namespace Api\Auth;

use Api\BaseApiController;
use Core\Auth;
use Models\Users;

class AuthController extends BaseApiController
{
    private $Users;

    public function __construct()
    {
        $this->Users = new Users();
    }

    public function login(): void
    {
        $this->requirePost();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username === '' || $password === '') {
            $this->respond(['success' => false, 'message' => 'Username and password are required.'], 422);
        }
        $result = Auth::attemptLogin($username, $password, $this->Users);
        $this->respond(['success' => $result['success'], 'message' => $result['message']], $result['status']);
    }

    public function logout(): void
    {
        $this->requirePost();
        Auth::logout();
        $this->respond(['success' => true, 'message' => 'Logged out successfully.']);
    }
}
