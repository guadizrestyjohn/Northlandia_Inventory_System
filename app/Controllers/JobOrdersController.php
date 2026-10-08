<?php

namespace Controllers;

use Core\Auth;
use Core\BaseController;
use Models\JobOrders;
use Models\Orders;

class JobOrdersController extends BaseController
{
    private $JobOrders;
    private $Orders;

    public function __construct()
    {
        $this->JobOrders = new JobOrders();
        $this->Orders = new Orders();
    }

    public function index(): void
    {
        Auth::requirePermission('job_orders.view');
        $user = Auth::user() ?? [];
        $productionAssignment = ($user['role'] ?? '') === 'Production Staff'
            ? 'Production Staff'
            : null;
        $this->view('JobOrders/index', [
            'title' => 'Job Orders',
            'canCreateJobOrders' => Auth::can('job_orders.create'),
            'canApproveJobOrders' => Auth::can('job_orders.approval'),
            'canEndorseJobOrders' => Auth::can('job_orders.endorse'),
            'canManageProduction' => Auth::can('production.timeframe') || Auth::can('production.complete'),
            'canSetProductionTimeframe' => Auth::can('production.timeframe'),
            'canCompleteProduction' => Auth::can('production.complete'),
            'jobOrders' => $this->JobOrders->find($productionAssignment),
            'orders' => $this->Orders->find(),
        ]);
    }
}
