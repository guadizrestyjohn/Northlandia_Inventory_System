<?php

namespace Api\Reports;

use Core\Auth;
use Core\BaseController;
use Models\Reports;

class ReportsController extends BaseController
{
    private $Reports;

    public function __construct()
    {
        $this->Reports = new Reports();
    }

    public function all(): void
    {
        $this->requirePermission('reports.view');
        $this->requireGet();

        $type = $this->queryValue('type');
        if (!in_array($type, ['inventory', 'movements', 'orders'], true)) {
            $this->json(['status' => 'error', 'message' => 'Select a valid report type.']);
        }

        $filters = [
            'category_id' => $this->queryValue('category_id'),
            'status' => $this->queryValue('status'),
            'keyword' => $this->queryValue('keyword'),
            'customer' => $this->queryValue('customer'),
            'start_date' => $this->queryValue('start_date'),
            'end_date' => $this->queryValue('end_date'),
        ];

        if ($filters['category_id'] !== '' && !ctype_digit($filters['category_id'])) {
            $this->json(['status' => 'error', 'message' => 'Select a valid inventory category.']);
        }
        foreach (['start_date', 'end_date'] as $dateKey) {
            if ($filters[$dateKey] !== '' && !$this->validDate($filters[$dateKey])) {
                $this->json(['status' => 'error', 'message' => 'Enter a valid report date.']);
            }
        }
        if ($filters['start_date'] !== '' && $filters['end_date'] !== ''
            && $filters['start_date'] > $filters['end_date']) {
            $this->json(['status' => 'error', 'message' => 'The start date must not be after the end date.']);
        }
        if ($filters['status'] !== '') {
            $allowedStatuses = [
                'inventory' => ['Available', 'Low Stock', 'Out of Stock'],
                'movements' => ['Stock In', 'Stock Out'],
                'orders' => [
                    'Pending', 'Processing', 'Ready for Release', 'Ready for Stock Out',
                    'Completed', 'Released', 'Cancelled',
                ],
            ];
            if (!in_array($filters['status'], $allowedStatuses[$type], true)) {
                $this->json(['status' => 'error', 'message' => 'Select a valid report status.']);
            }
        }

        try {
            $data = match ($type) {
                'inventory' => $this->Reports->inventory($filters),
                'movements' => $this->Reports->movements($filters),
                'orders' => $this->Reports->orders($filters),
            };
        } catch (\PDOException $e) {
            $this->json(['status' => 'error', 'message' => 'Unable to load this report right now.']);
        }

        $this->json(['data' => $data]);
    }

    private function queryValue(string $key): string
    {
        $value = $_GET[$key] ?? '';
        if (!is_scalar($value)) {
            $this->json(['status' => 'error', 'message' => 'Enter valid report filters.']);
        }

        return trim((string) $value);
    }

    private function validDate(string $date): bool
    {
        $parsedDate = \DateTime::createFromFormat('!Y-m-d', $date);
        return $parsedDate && $parsedDate->format('Y-m-d') === $date;
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

    private function requireGet(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->json(['status' => 'error', 'message' => 'GET is required.']);
        }
    }
}
