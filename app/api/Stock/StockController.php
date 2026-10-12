<?php

namespace Api\Stock;

use Api\BaseApiController;
use Core\Auth;
use Models\StockTransactions;

class StockController extends BaseApiController
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
        $this->respond(['data' => $this->Transactions->find()]);
    }

    public function stockIn(): void
    {
        $this->requirePermission('stock.in');
        $this->requirePost();
        $user = Auth::user() ?? [];
        $result = $this->Transactions->process('Stock In', $_POST, isset($user['id']) ? (int) $user['id'] : null);
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
}
