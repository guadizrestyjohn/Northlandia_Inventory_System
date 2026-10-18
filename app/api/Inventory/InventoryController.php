<?php

namespace Api\Inventory;

use Core\Auth;
use Core\BaseController;
use Models\Inventory;
use Models\InventoryCategories;

class InventoryController extends BaseController
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
        $this->json(['data' => $this->Inventory->find()]);
    }

    public function categories(): void
    {
        $this->requirePermission('inventory.view');
        $this->requireGet();
        $this->json(['data' => $this->Categories->find()]);
    }

    public function edit($id): void
    {
        $this->requireLogin();
        $this->requireGetOrPost();
        $this->requirePermission(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'inventory.manage' : 'inventory.view');
        $item = ctype_digit((string) $id) ? $this->Inventory->findByID($id) : null;
        if (!$item) {
            $this->json(['status' => 'error', 'message' => 'Inventory not found.']);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $data = $this->input();
            if ($data['width_cm'] === '' && $data['height_cm'] === '') {
                $data['size'] = $item->size;
                $data['width_cm'] = $item->width_cm;
                $data['height_cm'] = $item->height_cm;
            }
            $error = $this->validateInventory($data, false);
            if ($error !== null) {
                $this->json(['status' => 'error', 'message' => $error]);
            }
            $success = $this->Inventory->update($id, $data);
            $this->json(['status' => $success ? 'success' : 'error', 'message' => $success ? 'Inventory updated successfully.' : 'Failed to update inventory.']);
        }
        $this->json($item);
    }

    public function add(): void
    {
        $this->requirePermission('inventory.manage');
        $this->requirePost();
        $data = $this->input();
        $error = $this->validateInventory($data, true);
        if ($error !== null) {
            $this->json(['status' => 'error', 'message' => $error]);
        }
        $success = $this->Inventory->create($data);
        $this->json(['status' => $success ? 'success' : 'error', 'message' => $success ? 'Inventory added successfully.' : 'Failed to add inventory.']);
    }

    private function input(): array
    {
        return [
            'item_name' => $this->postString('item_name'),
            'item_description' => $this->postString('item_description'),
            'size' => $this->postString('size'),
            'width_cm' => $this->postString('width_cm'),
            'height_cm' => $this->postString('height_cm'),
            'category_id' => $this->postString('category_id'),
            'unit' => $this->postString('unit')
        ];
    }


    private function validateInventory(array &$data, bool $isNew): ?string
    {
        if (($data['item_name'] ?? '') === '' || ($data['category_id'] ?? '') === '' || ($data['unit'] ?? '') === '') {
            return 'Please fill in all required fields.';
        }
        if ($isNew && $data['item_description'] === '') {
            return 'Enter an Item Description.';
        }
        if (mb_strlen($data['item_name']) > 100 || mb_strlen($data['item_description']) > 255) {
            return 'Item Name or Item Description is too long.';
        }
        if (($data['width_cm'] === '') !== ($data['height_cm'] === '')) {
            return 'Enter both width and height, or leave both blank.';
        }
        if ($data['width_cm'] !== '') {
            foreach (['width_cm', 'height_cm'] as $dimension) {
                if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $data[$dimension]) || (float) $data[$dimension] <= 0) {
                    return 'Dimensions must be positive measurements in centimeters.';
                }
                if ((float) $data[$dimension] > 99999999.99) {
                    return 'Dimensions exceed the supported measurement range.';
                }
                $data[$dimension] = number_format((float) $data[$dimension], 2, '.', '');
            }
            $data['size'] = rtrim(rtrim($data['width_cm'], '0'), '.') . 'x'
                . rtrim(rtrim($data['height_cm'], '0'), '.') . 'cm';
            if (strlen($data['size']) > 40) {
                return 'The dimensions exceed the supported Size field limit.';
            }
        } else {
            $data['width_cm'] = null;
            $data['height_cm'] = null;
        }
        $category = ctype_digit((string) $data['category_id']) ? $this->Categories->findByID($data['category_id']) : null;
        if (!$category) {
            return 'Select a valid category.';
        }
        if (in_array($category->category_name, ['Finished Products', 'Modulars'], true)
            && ($data['width_cm'] === null || $data['height_cm'] === null)) {
            return 'Finished Products and Modulars require width and height in centimeters.';
        }
        if ($isNew && $data['unit'] !== 'pcs') {
            return 'New inventory items must use pcs as the unit.';
        }
        return null;
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

    private function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'POST is required.']);
        }
    }

    private function requireGet(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->json(['status' => 'error', 'message' => 'GET is required.']);
        }
    }

    private function requireGetOrPost(): void
    {
        if (!in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'POST'], true)) {
            $this->json(['status' => 'error', 'message' => 'GET or POST is required.']);
        }
    }

    private function postString(string $key, bool $trim = true): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;
        return $trim ? trim($value) : $value;
    }
}
