<?php

namespace Api\Stock;

use Core\BaseController;
use Core\Auth;
use Models\StockTransactions;

class StockController extends BaseController
{
    private $Transactions;

    public function __construct()
    {
        $this->Transactions = new StockTransactions();
    }

    public function all(): void
    {
        $this->requirePermission('stock.view');
        $this->requireGet();
        $this->json(['data' => $this->Transactions->find()]);
    }

    public function stockIn(): void
    {
        $this->requirePermission('stock.in');
        $this->requirePost();
        $user = Auth::user() ?? [];
        $result = $this->Transactions->process('Stock In', $_POST, isset($user['id']) ? (int) $user['id'] : null);
        $this->respondResult($result);
    }

    public function productionOutputs(): void
    {
        $this->requirePermission('stock.in');
        $this->requireGet();
        $this->json(['data' => $this->Transactions->findProductionOutputs()]);
    }

    public function releaseOutputs(): void
    {
        $this->requirePermission('stock.out');
        $this->requireGet();
        $orderId = $_GET['order_id'] ?? '';
        $jobOrderId = $_GET['job_order_id'] ?? '';
        if (!is_scalar($orderId) || !is_scalar($jobOrderId)
            || !ctype_digit((string) $orderId) || !ctype_digit((string) $jobOrderId)
            || (int) $orderId < 1 || (int) $jobOrderId < 1) {
            $this->json(['status' => 'error', 'message' => 'Select a valid Custom-Made Order and Job Order.']);
        }
        $this->json(['data' => $this->Transactions->findReleaseOutputs((int) $orderId, (int) $jobOrderId)]);
    }

    public function verifyProductionOutput(): void
    {
        $this->requirePermission('stock.in');
        $this->requirePost();
        $user = Auth::user() ?? [];
        $outputId = $this->postString('output_id');
        $inventoryId = $this->postString('inventory_id');
        if (!ctype_digit($outputId) || !ctype_digit($inventoryId)) {
            $this->respondResult(['success' => false, 'status' => 422, 'message' => 'Select a valid production output and inventory item.']);
        }
        $result = $this->Transactions->verifyProductionOutput(
            (int) $outputId,
            (int) $inventoryId,
            (int) ($user['id'] ?? 0)
        );
        $this->respondResult($result);
    }

    public function stockOut(): void
    {
        $this->requirePermission('stock.out');
        $this->requirePost();
        $user = Auth::user() ?? [];
        $result = $this->Transactions->process('Stock Out', $_POST, isset($user['id']) ? (int) $user['id'] : null);
        $this->respondResult($result);
    }

    private function respondResult(array $result): void
    {
        $successful = !empty($result['success']);
        unset($result['success'], $result['status']);
        if (empty($result['message'])) {
            $result['message'] = $successful
                ? 'Operation completed successfully.'
                : 'Unable to complete the operation.';
        }
        $result['status'] = $successful ? 'success' : 'error';
        $this->json($result);
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

    private function postString(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
