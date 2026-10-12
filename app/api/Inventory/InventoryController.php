<?php

namespace Api\Inventory;

use Api\BaseApiController;
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
        $this->respond(['data' => $this->Inventory->find()]);
    }

    public function categories(): void
    {
        $this->requirePermission('inventory.view');
        $this->requireGet();
        $this->respond(['data' => $this->Categories->find()]);
    }

    public function edit($id): void
    {
        $this->requireLogin();
        $this->requireGetOrPost();
        $this->requirePermission(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'inventory.manage' : 'inventory.view');
        $item = ctype_digit((string) $id) ? $this->Inventory->findByID($id) : null;
        if (!$item) {
            $this->respondError('Inventory not found.', 404);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $data = $this->input();
            $error = $this->validateInventory($data);
            if ($error !== null) {
                $this->respondError($error, 422);
            }
            $success = $this->Inventory->update($id, $data);
            $this->respond(
                ['status' => $success ? 'success' : 'error', 'message' => $success ? 'Inventory updated successfully.' : 'Failed to update inventory.'],
                $success ? 200 : 500
            );
        }
        $this->respond($item);
    }

    public function add(): void
    {
        $this->requirePermission('inventory.manage');
        $this->requirePost();
        $data = $this->input();
        $error = $this->validateInventory($data);
        if ($error !== null) {
            $this->respondError($error, 422);
        }
        $success = $this->Inventory->create($data);
        $this->respond(
            ['status' => $success ? 'success' : 'error', 'message' => $success ? 'Inventory added successfully.' : 'Failed to add inventory.'],
            $success ? 200 : 500
        );
    }

    private function input(): array
    {
        return [
            'item_name' => $this->postString('item_name'),
            'size' => $this->postString('size'),
            'category_id' => $this->postString('category_id'),
            'unit' => $this->postString('unit')
        ];
    }


    private function validateInventory(array $data): ?string
    {
        if (($data['item_name'] ?? '') === '' || ($data['category_id'] ?? '') === '' || ($data['unit'] ?? '') === '') {
            return 'Please fill in all required fields.';
        }
        if (!ctype_digit((string) $data['category_id']) || !$this->Categories->findByID($data['category_id'])) {
            return 'Select a valid category.';
        }
        return null;
    }

}
