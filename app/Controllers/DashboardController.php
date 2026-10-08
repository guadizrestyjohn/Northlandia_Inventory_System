<?php

namespace Controllers;

use Core\BaseController;
use Core\Auth;
use Models\Dashboard;

class DashboardController extends BaseController
{
    public function index(): void
    {
        Auth::requirePermission('dashboard.view');

        $dashboard = new Dashboard();

        $this->view('Dashboard/index', [
            'title' => 'Dashboard',
            'counts' => $dashboard->counts(),
            'dashboard_data' => $dashboard->chartData(),
        ]);
    }
}
