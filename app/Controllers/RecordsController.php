<?php

namespace Controllers;

use Core\Auth;
use Core\BaseController;
use Models\Records;

class RecordsController extends BaseController
{
    private $Records;

    public function __construct()
    {
        $this->Records = new Records();
    }

    public function index(): void
    {
        $this->requireRecordsAccess();
        $filters = $this->filters();
        $orders = [];

        try {
            $orders = $this->Records->findOrders();
        } catch (\Throwable $e) {
            $orders = [];
        }

        $this->view('Records/index', [
            'title' => 'Records',
            'orders' => $orders,
            'filters' => $filters,
            'canManageRecords' => Auth::canManageRecords(),
            'canDeleteBiddingDocuments' => Auth::canDeleteBiddingDocuments(),
            'canDeletePurchaseRecords' => Auth::canDeletePurchaseRecords(),
        ]);
    }

    private function filters(): array
    {
        $allowedTypes = [
            'Purchase', 'Sales', 'Order', 'Receipt', 'Invoice',
            'Bidding Document', 'Stock In', 'Stock Out', 'Job Order', 'Photo Documentation',
        ];
        $type = $this->queryValue('type');
        $startDate = $this->queryValue('start_date');
        $endDate = $this->queryValue('end_date');

        return [
            'type' => in_array($type, $allowedTypes, true) ? $type : '',
            'keyword' => $this->queryValue('keyword'),
            'start_date' => $this->validDateFilter($startDate) ? $startDate : '',
            'end_date' => $this->validDateFilter($endDate) ? $endDate : '',
            'status' => $this->queryValue('status'),
        ];
    }

    private function queryValue(string $key): string
    {
        $value = $_GET[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function validDateFilter(string $date): bool
    {
        if ($date === '') {
            return false;
        }

        $parsedDate = \DateTime::createFromFormat('!Y-m-d', $date);
        return $parsedDate && $parsedDate->format('Y-m-d') === $date;
    }

    private function requireRecordsAccess(): void
    {
        Auth::requireLogin();
        if (!Auth::canAccessRecords()) {
            http_response_code(403);
            exit('You are not authorized to access company records.');
        }
    }

}
