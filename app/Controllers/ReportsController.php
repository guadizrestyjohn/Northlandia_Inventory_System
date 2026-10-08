<?php

namespace Controllers;

use Core\Auth;
use Core\BaseController;
use Models\InventoryCategories;

class ReportsController extends BaseController
{
    public function index(): void
    {
        Auth::requirePermission('reports.view');
        $categories = (new InventoryCategories())->find();

        $this->view('Reports/index', [
            'title' => 'Reports',
            'categories' => $categories,
        ]);
    }
}
