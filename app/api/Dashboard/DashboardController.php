<?php

namespace Api\Dashboard;

use Core\Auth;
use Core\BaseController;
use Models\Dashboard;

class DashboardController extends BaseController
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

        $this->json([
            'status' => 'success',
            'data' => $this->dashboard->chartData(),
            'counts' => $this->dashboard->counts(),
        ]);
    }

    private function requireLogin(): void
    {
        if (!Auth::check()) {
            $this->json(['status' => 'error', 'message' => 'You must be logged in.']);
        }
    }

    private function requirePermission(string $permission): void
    {
        $this->requireLogin();
        if (!Auth::can($permission)) {
            $this->json([
                'status' => 'error',
                'message' => 'You are not authorized to perform this action.',
            ]);
        }
    }

    private function requireGet(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->json(['status' => 'error', 'message' => 'GET is required.']);
        }
    }
}
