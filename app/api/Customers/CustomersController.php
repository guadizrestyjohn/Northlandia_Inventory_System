<?php

namespace Api\Customers;

use Api\BaseApiController;
use Models\Customers;

class CustomersController extends BaseApiController
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
        $this->respond(['data' => $this->Customers->find()]);
    }

    public function edit($id): void
    {
        $this->requireLogin();
        $this->requireGetOrPost();
        $this->requirePermission(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'customers.manage' : 'customers.view');
        $customer = ctype_digit((string) $id) ? $this->Customers->findByID($id) : null;
        if (!$customer) {
            $this->respondError('Customer not found.', 404);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $data = $this->input();
            $error = $this->validateCustomer($data);
            if ($error !== null) {
                $this->respondError($error, 422);
            }
            $success = $this->Customers->update($id, $data['customer_name'], $data['contact_number'], $data['email'], $data['address']);
            $this->respond(
                ['status' => $success ? 'success' : 'error', 'message' => $success ? 'Customer updated successfully.' : 'Failed to update customer.'],
                $success ? 200 : 500
            );
        }
        $this->respond($customer);
    }

    public function add(): void
    {
        $this->requirePermission('customers.manage');
        $this->requirePost();
        $data = $this->input();
        $error = $this->validateCustomer($data);
        if ($error !== null) {
            $this->respondError($error, 422);
        }
        $success = $this->Customers->create($data['customer_name'], $data['contact_number'], $data['email'], $data['address']);
        $this->respond(
            ['status' => $success ? 'success' : 'error', 'message' => $success ? 'Customer added successfully.' : 'Failed to add customer.'],
            $success ? 200 : 500
        );
    }

    public function delete($id = null): void
    {
        $this->requireLogin();
        $this->respondError('Customer deletion is not allowed.', 405);
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
        return trim((string) ($data['customer_name'] ?? '')) === ''
            ? 'Customer name is required.'
            : null;
    }
}
