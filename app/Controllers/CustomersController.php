<?php

namespace Controllers;

use Core\BaseController;
use Core\Auth;

class CustomersController extends BaseController
{
    public function index(): void
    {
        Auth::requirePermission('customers.view');

        $this->view('Customers/index', [
            'title' => 'Customers',
            'canManageCustomers' => Auth::can('customers.manage'),
        ]);
    }
}
