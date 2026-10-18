<?php

namespace Api\Customers;

use Core\Auth;
use Core\BaseController;
use Models\Customers;

class CustomersController extends BaseController
{
    private $Customers;

    public function __construct()
    {
        $this->Customers = new Customers();
    }

    public function all(): void
    {
        $this->requirePermission('customers.view');
        $this->requireGet();
        $this->json(['data' => $this->Customers->find()]);
    }

    public function edit($id): void
    {
        $this->requireLogin();
        $this->requireGetOrPost();
        $this->requirePermission(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'customers.manage' : 'customers.view');
        $customer = ctype_digit((string) $id) ? $this->Customers->findByID($id) : null;
        if (!$customer) {
            $this->json(['status' => 'error', 'message' => 'Customer not found.']);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $data = $this->input();
            $error = $this->validateCustomer($data);
            if ($error !== null) {
                $this->json(['status' => 'error', 'message' => $error]);
            }
            $success = $this->Customers->update($id, $data['customer_name'], $data['contact_number'], $data['email'], $data['address']);
            $this->json(['status' => $success ? 'success' : 'error', 'message' => $success ? 'Customer updated successfully.' : 'Failed to update customer.']);
        }
        $this->json($customer);
    }

    public function add(): void
    {
        $this->requirePermission('customers.manage');
        $this->requirePost();
        $data = $this->input();
        $error = $this->validateCustomer($data);
        if ($error !== null) {
            $this->json(['status' => 'error', 'message' => $error]);
        }
        $success = $this->Customers->create($data['customer_name'], $data['contact_number'], $data['email'], $data['address']);
        $this->json(['status' => $success ? 'success' : 'error', 'message' => $success ? 'Customer added successfully.' : 'Failed to add customer.']);
    }

    public function delete($id = null): void
    {
        $this->requireLogin();
        $this->json(['status' => 'error', 'message' => 'Customer deletion is not allowed.']);
    }

    private function input(): array
    {
        return [
            'customer_name' => $this->postString('customer_name'),
            'contact_number' => $this->postString('contact_number'),
            'email' => $this->postString('email'),
            'address' => $this->postString('address')
        ];
    }

    private function validateCustomer(array $data): ?string
    {
        $name = trim((string) ($data['customer_name'] ?? ''));
        if ($name === '') {
            return 'Customer name is required.';
        }
        if (mb_strlen($name) > 100) {
            return 'Customer name must be 100 characters or fewer.';
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
