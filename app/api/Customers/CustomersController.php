<?php

namespace Api\Customers;

use Api\BaseApiController;
use Core\Validation;
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
        $this->respond(['success' => true, 'data' => $this->Customers->find()]);
    }

    public function edit($id): void
    {
        $this->requireLogin();
        $this->requireGetOrPost();
        $this->requirePermission(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'customers.manage' : 'customers.view');
        $customer = ctype_digit((string) $id) ? $this->Customers->findByID($id) : null;
        if (!$customer) {
            $this->respond(['success' => false, 'message' => 'Customer not found.'], 404);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $data = $this->input();
            $error = Validation::customer($data);
            if ($error !== null) {
                $this->respond(['success' => false, 'message' => $error], 422);
            }
            $success = $this->Customers->update($id, $data['customer_name'], $data['contact_number'], $data['email'], $data['address']);
            $this->respond(
                ['success' => $success, 'message' => $success ? 'Customer updated successfully.' : 'Failed to update customer.'],
                $success ? 200 : 500
            );
        }
        $this->respond(['success' => true, 'data' => $customer]);
    }

    public function add(): void
    {
        $this->requirePermission('customers.manage');
        $this->requirePost();
        $data = $this->input();
        $error = Validation::customer($data);
        if ($error !== null) {
            $this->respond(['success' => false, 'message' => $error], 422);
        }
        $success = $this->Customers->create($data['customer_name'], $data['contact_number'], $data['email'], $data['address']);
        $this->respond(
            ['success' => $success, 'message' => $success ? 'Customer added successfully.' : 'Failed to add customer.'],
            $success ? 200 : 500
        );
    }

    public function delete($id = null): void
    {
        $this->requireLogin();
        $this->respond(['success' => false, 'message' => 'Customer deletion is not allowed.'], 403);
    }

    private function input(): array
    {
        return [
            'customer_name' => trim($_POST['customer_name'] ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? '')
        ];
    }
}
