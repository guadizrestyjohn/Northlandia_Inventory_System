<?php

namespace Api\Inventory;

use Api\BaseApiController;
use Core\Validation;
use Models\Inventory;
use Models\InventoryCategories;

class InventoryController extends BaseApiController
{
    private $Inventory;
    private $Categories;

    public function __construct()
    {
        $this->Inventory = new Inventory();
        $this->Categories = new InventoryCategories();
    }

    public function all(): void
    {
        $this->requirePermission('inventory.view');
        $this->requireGet();
        $this->respond(['success' => true, 'data' => $this->Inventory->find()]);
    }

    public function categories(): void
    {
        $this->requirePermission('inventory.view');
        $this->requireGet();
        $this->respond(['success' => true, 'data' => $this->Categories->find()]);
    }

    public function edit($id): void
    {
        $this->requireLogin();
        $this->requireGetOrPost();
        $this->requirePermission(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'inventory.manage' : 'inventory.view');
        $item = ctype_digit((string) $id) ? $this->Inventory->findByID($id) : null;
        if (!$item) {
            $this->respond(['success' => false, 'message' => 'Inventory not found.'], 404);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $data = $this->input();
            $error = Validation::inventory($data, $this->Categories);
            if ($error !== null) {
                $this->respond(['success' => false, 'message' => $error], 422);
            }
            $success = $this->Inventory->update($id, $data);
            $this->respond(
                ['success' => $success, 'message' => $success ? 'Inventory updated successfully.' : 'Failed to update inventory.'],
                $success ? 200 : 500
            );
        }
        $this->respond(['success' => true, 'data' => $item]);
    }

    public function add(): void
    {
        $this->requirePermission('inventory.manage');
        $this->requirePost();
        $data = $this->input();
        $error = Validation::inventory($data, $this->Categories);
        if ($error !== null) {
            $this->respond(['success' => false, 'message' => $error], 422);
        }
        $success = $this->Inventory->create($data);
        $this->respond(
            ['success' => $success, 'message' => $success ? 'Inventory added successfully.' : 'Failed to add inventory.'],
            $success ? 200 : 500
        );
    }

    private function input(): array
    {
        return [
            'item_name' => trim($_POST['item_name'] ?? ''),
            'size' => trim($_POST['size'] ?? ''),
            'category_id' => trim($_POST['category_id'] ?? ''),
            'unit' => trim($_POST['unit'] ?? '')
        ];
    }

}
