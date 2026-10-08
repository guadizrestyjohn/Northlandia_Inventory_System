<?php

namespace Controllers;

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

    public function index(): void
    {
        $this->view('Auth/index', [
            'title' => 'Login',
        ], 'auth');
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . '/Auth');
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $_SESSION['error'] = 'Username and password are required.';
            $this->redirect(BASE_URL . '/Auth');
        }

        $result = Auth::attemptLogin($username, $password, $this->Users);
        if (!$result['success']) {
            $_SESSION['error'] = $result['message'];
            $this->redirect(BASE_URL . '/Auth');
        }

        $this->redirect(BASE_URL . '/Dashboard');
    }

    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('POST is required.');
        }

        Auth::logout();

        $this->redirect(BASE_URL . '/Auth');
    }
}
