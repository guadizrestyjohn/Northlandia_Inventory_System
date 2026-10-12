<?php

namespace Api\Dashboard;

use Api\BaseApiController;
use Models\Dashboard;

class DashboardController extends BaseApiController
{
    private $dashboard;

    public function __construct()
    {
        $this->dashboard = new Dashboard();
    }

    public function summary(): void
    {
        $this->requirePermission('dashboard.view');
        $this->requireGet();

        $this->respond([
            'status' => 'success',
            'data' => $this->dashboard->chartData(),
        ]);
    }
}
