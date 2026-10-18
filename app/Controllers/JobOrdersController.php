<?php

namespace Controllers;

use Core\Auth;
use Core\BaseController;
use Models\JobOrders;
use Models\Orders;
use Models\Users;

class JobOrdersController extends BaseController
{
    private $JobOrders;
    private $Orders;
    private $Users;

    public function __construct()
    {
        $this->JobOrders = new JobOrders();
        $this->Orders = new Orders();
        $this->Users = new Users();
    }

    public function index(): void
    {
        Auth::requirePermission('job_orders.view');
        $user = Auth::user() ?? [];
        $currentUser = $this->Users->findByID((int) ($user['id'] ?? 0));
        $isAdministrator = ($user['role'] ?? '') === 'Administrator';
        $isProductionStaff = ($user['role'] ?? '') === 'Production Staff';
        $productionTeam = trim((string) ($currentUser->production_team ?? ''));
        $productionTeams = $isProductionStaff
            ? array_values(array_unique(array_filter([
                trim((string) ($currentUser->production_team ?? '')),
            ], static fn($team) => $team !== '')))
            : null;
        $this->view('JobOrders/index', [
            'title' => 'Job Orders',
            'canCreateJobOrders' => Auth::can('job_orders.create'),
            'canApproveJobOrders' => Auth::can('job_orders.approval'),
            'canEndorseJobOrders' => Auth::can('job_orders.endorse'),
            'canManageProduction' => Auth::can('production.timeframe') || Auth::can('production.complete'),
            'canSetProductionTimeframe' => Auth::can('production.timeframe'),
            'canCompleteProduction' => Auth::can('production.complete'),
            'canReportProductionOutput' => ($isAdministrator || ($isProductionStaff && $productionTeam !== '')) && Auth::can('job_orders.view'),
            'canViewProductionOutputHistory' => in_array($user['role'] ?? '', ['Administrator', 'Manager', 'Production Staff'], true),
            'jobOrders' => $this->JobOrders->find($productionTeams),
            'orders' => $this->Orders->find(),
            'productionTeams' => $this->Users->productionTeams(),
        ]);
    }
}
