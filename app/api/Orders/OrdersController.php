<?php

namespace Api\Orders;

use Api\BaseApiController;
use Core\Auth;
use Core\Validation;
use Models\Customers;
use Models\Orders;

class OrdersController extends BaseApiController
{
    private $Orders;
    private $Customers;

    public function __construct()
    {
        $this->Orders = new Orders();
        $this->Customers = new Customers();
    }

    public function all(): void
    {
        $this->requirePermission('orders.view');
        $this->requireGet();
        $this->respond(['success' => true, 'orders' => $this->Orders->find()]);
    }

    public function customers(): void
    {
        $this->requirePermission('orders.view');
        $this->requireGet();
        $query = $_GET['q'] ?? '';
        if (!is_scalar($query)) {
            $this->respond(['success' => false, 'message' => 'Enter a valid customer search.'], 422);
        }

        $query = trim((string) $query);
        $orderId = $_GET['order_id'] ?? '';
        if (!is_scalar($orderId)) {
            $this->respond(['success' => false, 'message' => 'Enter a valid Order No.'], 422);
        }

        $orderId = trim((string) $orderId);
        if ($orderId !== '' && (!ctype_digit($orderId) || (int) $orderId < 1)) {
            $this->respond(['success' => false, 'message' => 'Enter a valid Order No.'], 422);
        }

        $customers = strlen($query) >= 2
            ? $this->Customers->searchForOrders($query)
            : ($query === '' && $orderId === ''
                ? $this->Customers->eligibleForOrders()
                : []);

        $this->respond(['success' => true, 'data' => $customers]);
    }

    public function payment($id): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->requireGetOrPost();
        $this->requirePermission($method === 'POST' ? 'orders.payment' : 'orders.view');

        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $this->respond(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($method === 'GET') {
            $order = $this->Orders->findByID((int) $id);
            if (!$order) {
                $this->respond(['success' => false, 'message' => 'Order not found.'], 404);
            }

            $this->respond([
                'success' => true,
                'order' => [
                    'order_id' => (int) $order->order_id,
                    'customer_name' => $order->customer_name ?? null,
                    'product_name' => $order->product_name,
                    'status' => $order->status,
                ],
                'summary' => $this->Orders->paymentSummary((int) $id),
                'payments' => $this->Orders->paymentHistory((int) $id),
                'can_add_payment' => Auth::can('orders.payment'),
            ]);
        }

        $amount = $_POST['amount'] ?? null;
        if (!is_scalar($amount)) {
            $this->respond(['success' => false, 'message' => 'Enter a valid payment amount.'], 422);
        }

        $user = Auth::user() ?? [];
        $result = $this->Orders->addPayment(
            (int) $id,
            trim((string) $amount),
            isset($user['id']) ? (int) $user['id'] : null
        );
        $status = (int) ($result['status'] ?? 500);
        unset($result['status']);
        $this->respond($result, $status);
    }

    public function edit($id): void
    {
        $this->requireLogin();
        $this->requireGetOrPost();
        $this->requirePermission(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'orders.update' : 'orders.view');
        $order = ctype_digit((string) $id) ? $this->Orders->findByID($id) : null;
        if (!$order) {
            $this->respond(['success' => false, 'message' => 'Order not found.'], 404);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $data = $this->input();
            if (!in_array($order->status, ['Pending', 'Cancelled'], true)) {
                if (isset($_POST['status']) && $_POST['status'] !== $order->status) {
                    $this->respond(['success' => false, 'message' => 'The status of an order already in production cannot be changed here.'], 422);
                }
                $data['status'] = $order->status;
            }
            $error = Validation::order($data, true, $this->Customers, (string) $order->status);
            if ($error !== null) {
                $this->respond(['success' => false, 'message' => $error], 422);
            }
            $result = $this->Orders->update($id, $data);
            $this->respond($result, (int) ($result['status'] ?? ($result['success'] ? 200 : 422)));
        }
        $this->respond(['success' => true, 'order' => $order]);
    }

    public function add(): void
    {
        $this->requirePermission('orders.create');
        $this->requirePost();
        $data = $this->input();
        $error = Validation::order($data, false, $this->Customers);
        if ($error !== null) {
            $this->respond(['success' => false, 'message' => $error], 422);
        }
        $data['status'] = 'Pending';
        $user = Auth::user() ?? [];
        $result = $this->Orders->create(
            $data,
            isset($user['id']) ? (int) $user['id'] : null
        );
        $this->respond($result, (int) ($result['status'] ?? ($result['success'] ? 200 : 500)));
    }

    public function delete($id = null): void
    {
        $this->requireLogin();
        $this->respond(['success' => false, 'message' => 'Order deletion is not allowed.'], 403);
    }

    private function input(): array
    {
        return [
            'customer_id' => $this->postValue('customer_id'),
            'order_date' => $this->postValue('order_date'),
            'product_name' => $this->postValue('product_name'),
            'quantity' => $this->postValue('quantity'),
            'specifications' => $this->postValue('specifications'),
            'order_total' => $this->postValue('order_total'),
            'downpayment' => $this->postValue('downpayment', '0'),
            'delivery_method' => $this->postValue('delivery_method'),
            'status' => $this->postValue('status', 'Pending')
        ];
    }

    private function postValue(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_scalar($value) ? trim((string) $value) : '';
    }

}
