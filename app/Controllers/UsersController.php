<?php

namespace Controllers;

use Core\BaseController;
use Core\Auth;
use Models\Users;

class UsersController extends BaseController
{
    private $Users;

    public function __construct()
    {
        $this->Users = new Users();
    }

    public function index(): void
    {
        Auth::requirePermission('users.manage');
        $this->view('Users/index', [
            'title' => 'User Management',
            'roles' => $this->Users->findRoles(),
            'statuses' => ['Active', 'Inactive'],
        ]);
    }
}
