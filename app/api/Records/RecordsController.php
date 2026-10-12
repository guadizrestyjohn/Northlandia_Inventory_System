<?php

namespace Api\Records;

use Api\BaseApiController;
use Core\Auth;
use Models\Records;

class RecordsController extends BaseApiController
{
    private $Records;

    public function __construct()
    {
        $this->Records = new Records();
    }

    public function all(): void
    {
        $this->requireRecordsAccess();
        $this->requireGet();
        try {
            $this->respond([
                'data' => $this->Records->search($this->filters()),
            ]);
        } catch (\Throwable $e) {
            $this->respondError('Unable to load records right now.', 500);
        }
    }

    public function add(): void
    {
        $this->requireRecordsManagement();
        $this->requirePost();
        $this->save(null);
    }

    public function edit($id): void
    {
        $this->requireRecordsManagement();
        $this->requireGetOrPost();
        if (!ctype_digit((string) $id)) {
            $this->respondError('Record not found.', 404);
        }

        $type = $this->queryValue('record_type');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $type = $this->inputValue('record_type');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            try {
                $record = $this->Records->findDocument($id, $type);
            } catch (\Throwable $e) {
                $this->respondError('Unable to load this record right now.', 500);
            }
            if (!$record) {
                $this->respondError('Record not found.', 404);
            }
            $this->respond($record);
        }

        $this->save((int) $id);
    }

    private function save(?int $id): void
    {
        $user = Auth::user() ?? [];
        try {
            $result = $this->Records->saveDocument($id, $this->input(), (int) ($user['id'] ?? 0));
        } catch (\Throwable $e) {
            $result = ['status' => 500, 'message' => 'Unable to save this record right now.'];
        }

        $this->respondResult($result, 422);
    }

    private function filters(): array
    {
        $type = $this->queryValue('type');
        $startDate = $this->queryValue('start_date');
        $endDate = $this->queryValue('end_date');

        return [
            'type' => $type,
            'keyword' => $this->queryValue('keyword'),
            'start_date' => $this->validDateFilter($startDate) ? $startDate : '',
            'end_date' => $this->validDateFilter($endDate) ? $endDate : '',
            'status' => $this->queryValue('status'),
        ];
    }

    private function validDateFilter(string $date): bool
    {
        if ($date === '') {
            return false;
        }

        $parsedDate = \DateTime::createFromFormat('!Y-m-d', $date);
        return $parsedDate && $parsedDate->format('Y-m-d') === $date;
    }

    private function inputValue(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function queryValue(string $key): string
    {
        $value = $_GET[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function input(): array
    {
        $data = [];
        foreach ([
            'record_type', 'reference_no', 'record_date', 'order_id', 'amount',
            'description', 'items_details', 'supplier_name', 'title', 'client_name', 'status',
        ] as $key) {
            $data[$key] = $this->inputValue($key);
        }

        return $data;
    }

    private function requireRecordsAccess(): void
    {
        $this->requireLogin();
        if (!Auth::canAccessRecords()) {
            $this->respondError('You are not authorized to access company records.', 403);
        }
    }

    private function requireRecordsManagement(): void
    {
        $this->requireLogin();
        if (!Auth::canManageRecords()) {
            $this->respondError('You are not authorized to manage company records.', 403);
        }
    }
}
