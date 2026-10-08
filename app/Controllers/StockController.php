<?php

namespace Controllers;

use Core\Auth;
use Core\BaseController;
use Models\Inventory;
use Models\Orders;

class StockController extends BaseController
{
    private $Inventory;
    private $Orders;

    public function __construct()
    {
        $this->Inventory = new Inventory();
        $this->Orders = new Orders();
    }

    public function index(): void
    {
        Auth::requirePermission('stock.view');
        $this->view('Stock/index', [
            'title' => 'Stock',
            'items' => $this->Inventory->find(),
            'orders' => $this->Orders->find(),
        ]);
    }
}
