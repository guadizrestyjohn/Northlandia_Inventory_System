<?php

namespace Controllers;

use Core\Auth;
use Core\BaseController;

class OrdersController extends BaseController
{
    public function index(): void
    {
        Auth::requirePermission('orders.view');
        $this->view('Orders/index', [
            'title' => 'Orders',
            'canCreateOrders' => Auth::can('orders.create'),
            'canUpdateOrders' => Auth::can('orders.update'),
            'canRecordPayments' => Auth::can('orders.payment'),
            'canApproveReadyMadeOrders' => Auth::can('job_orders.approval'),
        ]);
    }
}
