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
        $roles = array_values(array_filter(
            $this->Users->findRoles(),
            static fn($role): bool => Auth::canAssignRole((string) $role->role_name)
        ));

        $this->view('Users/index', [
            'title' => 'User Management',
            'roles' => $roles,
            'statuses' => ['Active', 'Inactive'],
            'canManageAllUserRoles' => Auth::canManageUserRole('Administrator'),
        ]);
    }
}
