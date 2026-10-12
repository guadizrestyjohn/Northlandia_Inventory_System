<?php

namespace Api\Reports;

use Api\BaseApiController;
use Models\Reports;

class ReportsController extends BaseApiController
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
            $this->respondError('Select a valid report type.', 422);
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
            $this->respondError('Select a valid inventory category.', 422);
        }
        foreach (['start_date', 'end_date'] as $dateKey) {
            if ($filters[$dateKey] !== '' && !$this->validDate($filters[$dateKey])) {
                $this->respondError('Enter a valid report date.', 422);
            }
        }
        if ($filters['start_date'] !== '' && $filters['end_date'] !== ''
            && $filters['start_date'] > $filters['end_date']) {
            $this->respondError('The start date must not be after the end date.', 422);
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
                $this->respondError('Select a valid report status.', 422);
            }
        }

        try {
            $data = match ($type) {
                'inventory' => $this->Reports->inventory($filters),
                'movements' => $this->Reports->movements($filters),
                'orders' => $this->Reports->orders($filters),
            };
        } catch (\PDOException $e) {
            $this->respondError('Unable to load this report right now.', 500);
        }

        $this->respond(['data' => $data]);
    }

    private function queryValue(string $key): string
    {
        $value = $_GET[$key] ?? '';
        if (!is_scalar($value)) {
            $this->respondError('Enter valid report filters.', 400);
        }

        return trim((string) $value);
    }

    private function validDate(string $date): bool
    {
        $parsedDate = \DateTime::createFromFormat('!Y-m-d', $date);
        return $parsedDate && $parsedDate->format('Y-m-d') === $date;
    }
}
