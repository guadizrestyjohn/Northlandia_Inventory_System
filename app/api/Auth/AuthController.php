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
        $username = $this->postString('username');
        $password = $this->postString('password', false);
        if ($username === '' || $password === '') {
            $this->respondError('Username and password are required.', 422);
        }
        $result = Auth::attemptLogin($username, $password, $this->Users);
        $this->respondResult($result);
    }

    public function logout(): void
    {
        $this->requirePost();
        Auth::logout();
        $this->respond(['status' => 'success', 'message' => 'Logged out successfully.']);
    }

}
