<?php

namespace Controllers;

use Core\Auth;
use Core\BaseController;
use Models\Inventory;
use Models\InventoryCategories;
use Models\Orders;

class InventoryController extends BaseController
{
    private $Inventory;
    private $Categories;
    private $Orders;

    public function __construct()
    {
        $this->Inventory = new Inventory();
        $this->Categories = new InventoryCategories();
        $this->Orders = new Orders();
    }

    public function index(): void
    {
        Auth::requirePermission('inventory.view');
        $this->view('Inventory/index', [
            'title' => 'Inventory',
            'canManageInventory' => Auth::can('inventory.manage'),
            'items' => $this->Inventory->find(),
            'orders' => $this->Orders->find(),
            'categories' => $this->Categories->find(),
        ]);
    }
}
