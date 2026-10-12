<?php

namespace Api\Orders;

use Api\BaseApiController;
use Core\Auth;
use Models\Customers;
use Models\Orders;
use Models\TaxSettings;

class OrdersController extends BaseApiController
{
    private $Orders;
    private $Customers;
    private $TaxSettings;

    public function __construct()
    {
        $this->Orders = new Orders();
        $this->Customers = new Customers();
        $this->TaxSettings = new TaxSettings();
    }

    public function all(): void
    {
        $this->requirePermission('orders.view');
        $this->requireGet();
        $this->respond(['data' => $this->Orders->find()]);
    }

    public function customers(): void
    {
        $this->requirePermission('orders.view');
        $this->requireGet();
        $query = $_GET['q'] ?? '';
        if (!is_scalar($query)) {
            $this->respondError('Enter a valid customer search.', 400);
        }

        $query = trim((string) $query);
        $orderId = $_GET['order_id'] ?? '';
        if (!is_scalar($orderId)) {
            $this->respondError('Enter a valid Order No.', 400);
        }

        $orderId = trim((string) $orderId);
        if ($orderId !== '' && (!ctype_digit($orderId) || (int) $orderId < 1)) {
            $this->respondError('Enter a valid Order No.', 400);
        }

        $customers = strlen($query) >= 2
            ? $this->Customers->searchForOrders($query)
            : ($query === '' && $orderId === ''
                ? $this->Customers->eligibleForOrders()
                : []);

        $this->respond(['data' => $customers]);
    }

    public function taxConfiguration(): void
    {
        $this->requirePermission('orders.create');
        $this->requireGet();
        $state = $this->TaxSettings->activeConfiguration();
        if (!$state['configuration']) {
            $this->respondError($state['message'], 422);
        }
        $this->respond(['status' => 'success', 'data' => $state['configuration']]);
    }

    public function payment($id): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->requireGetOrPost();
        $this->requirePermission($method === 'POST' ? 'orders.payment' : 'orders.view');

        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $this->respondError('Order not found.', 404);
        }

        if ($method === 'GET') {
            $order = $this->Orders->findByID((int) $id);
            if (!$order) {
                $this->respondError('Order not found.', 404);
            }

            $this->respond([
                'status' => 'success',
                'data' => [
                    'order' => [
                        'order_id' => (int) $order->order_id,
                        'customer_name' => $order->customer_name ?? null,
                        'product_name' => $order->product_name,
                        'status' => $order->status,
                        'order_total' => $order->order_total,
                        'subtotal_before_tax_snapshot' => $order->subtotal_before_tax_snapshot,
                        'tax_name_snapshot' => $order->tax_name_snapshot,
                        'tax_rate_snapshot' => $order->tax_rate_snapshot,
                        'tax_amount_snapshot' => $order->tax_amount_snapshot,
                        'tax_calculation_mode_snapshot' => $order->tax_calculation_mode_snapshot,
                    ],
                    'summary' => $this->Orders->paymentSummary((int) $id),
                    'payments' => $this->Orders->paymentHistory((int) $id),
                    'can_add_payment' => Auth::can('orders.payment'),
                ],
            ]);
        }

        $amount = $_POST['amount'] ?? null;
        if (!is_scalar($amount)) {
            $this->respondError('Enter a valid payment amount.', 422);
        }

        $user = Auth::user() ?? [];
        $result = $this->Orders->addPayment(
            (int) $id,
            trim((string) $amount),
            isset($user['id']) ? (int) $user['id'] : null
        );
        $this->respondResult($result);
    }

    public function edit($id): void
    {
        $this->requireLogin();
        $this->requireGetOrPost();
        $this->requirePermission(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'orders.update' : 'orders.view');
        $order = ctype_digit((string) $id) ? $this->Orders->findByID($id) : null;
        if (!$order) {
            $this->respondError('Order not found.', 404);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $data = $this->input();
            if (!in_array($order->status, ['Pending', 'Cancelled'], true)) {
                if (isset($_POST['status']) && $_POST['status'] !== $order->status) {
                    $this->respondError('The status of an order already in production cannot be changed here.', 409);
                }
                $data['status'] = $order->status;
            }
            $snapshotFields = [
                $order->subtotal_before_tax_snapshot,
                $order->tax_name_snapshot,
                $order->tax_rate_snapshot,
                $order->tax_amount_snapshot,
                $order->tax_calculation_mode_snapshot,
            ];
            $hasSnapshot = count(array_filter($snapshotFields, static fn($value) => $value !== null)) > 0;
            if ($hasSnapshot) {
                if (in_array(null, $snapshotFields, true)) {
                    $this->respondError('This Order has an incomplete tax snapshot and cannot be recalculated safely.', 422);
                }
                $calculation = Orders::calculateTax($data['order_total'], [
                    'tax_name' => $order->tax_name_snapshot,
                    'tax_rate' => (string) $order->tax_rate_snapshot,
                    'calculation_mode' => $order->tax_calculation_mode_snapshot,
                ]);
                if ($calculation === null) {
                    $this->respondError('Enter a valid amount for this Order tax calculation.', 422);
                }
                $data = array_merge($data, $calculation);
            } else {
                $data['subtotal_before_tax_snapshot'] = null;
                $data['tax_name_snapshot'] = null;
                $data['tax_rate_snapshot'] = null;
                $data['tax_amount_snapshot'] = null;
                $data['tax_calculation_mode_snapshot'] = null;
            }
            $error = $this->validateOrder($data, true, (string) $order->status);
            if ($error !== null) {
                $this->respondError($error, 422);
            }
            $result = $this->Orders->update($id, $data);
            $this->respondResult($result);
        }
        $this->respond($order);
    }

    public function add(): void
    {
        $this->requirePermission('orders.create');
        $this->requirePost();
        $data = $this->input();
        $taxState = $this->TaxSettings->activeConfiguration();
        if (!$taxState['configuration']) {
            $this->respondError($taxState['message'], 422);
        }
        $calculation = Orders::calculateTax($data['order_total'], $taxState['configuration']);
        if ($calculation === null) {
            $this->respondError('Enter a valid Order amount. The final total must fit the supported currency range.', 422);
        }
        $data = array_merge($data, $calculation);
        $error = $this->validateOrder($data, false);
        if ($error !== null) {
            $this->respondError($error, 422);
        }
        $data['status'] = 'Pending';
        $user = Auth::user() ?? [];
        $result = $this->Orders->create(
            $data,
            isset($user['id']) ? (int) $user['id'] : null
        );
        $this->respondResult($result);
    }

    public function delete($id = null): void
    {
        $this->requireLogin();
        $this->respondError('Order deletion is not allowed.', 405);
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

    private function validateOrder(array $data, bool $updating, ?string $currentStatus = null): ?string
    {
        foreach (['customer_id', 'order_date', 'product_name', 'quantity'] as $required) {
            if (($data[$required] ?? '') === '') return 'Please complete the required fields.';
        }
        if (!ctype_digit((string) $data['customer_id']) || !$this->Customers->findByID($data['customer_id'])) {
            return 'Select a valid customer.';
        }
        $orderTotal = (string) ($data['order_total'] ?? '');
        $downpayment = (string) ($data['downpayment'] ?? '0');
        if (!$this->isCurrencyAmount($orderTotal) || $this->currencyCents($orderTotal) <= 0) {
            return 'Order Total must be a valid amount greater than zero.';
        }
        if (!$this->isCurrencyAmount($downpayment)) {
            return 'Downpayment must be a valid non-negative amount.';
        }
        if ($this->currencyCents($downpayment) > $this->currencyCents($orderTotal)) {
            return 'Downpayment cannot exceed the order total.';
        }
        if (!is_numeric($data['quantity']) || !is_finite((float) $data['quantity']) || (float) $data['quantity'] <= 0) {
            return 'Quantity must be a valid number greater than 0.';
        }
        $date = \DateTime::createFromFormat('Y-m-d', $data['order_date']);
        if (!$date || $date->format('Y-m-d') !== $data['order_date']) return 'Enter a valid order date.';
        if (!$updating && $data['order_date'] < date('Y-m-d')) return 'Order date cannot be earlier than today.';
        if ($updating) {
            $status = $data['status'] ?? '';
            $allowedStatuses = in_array($currentStatus, ['Pending', 'Cancelled'], true)
                ? ['Pending', 'Cancelled']
                : [$currentStatus];
            if (!in_array($status, $allowedStatuses, true)) return 'Invalid order status.';
        }
        if ($updating && ($data['order_date'] < '2000-01-01' || $data['order_date'] > '2100-12-31')) return 'Invalid order date.';
        return null;
    }

    private function isCurrencyAmount(string $amount): bool
    {
        return (bool) preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', $amount);
    }

    private function currencyCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

}
