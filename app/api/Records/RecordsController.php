<?php

namespace Api\Records;

use Core\BaseController;
use Core\Auth;
use Models\Records;

class RecordsController extends BaseController
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
            $this->json([
                'data' => $this->Records->search($this->filters()),
            ]);
        } catch (\Throwable $e) {
            $this->json(['status' => 'error', 'message' => 'Unable to load records right now.']);
        }
    }

    public function add(): void
    {
        $this->requireRecordsManagement();
        $this->requirePost();
        $this->save(null);
    }

    public function nextReference($type): void
    {
        $this->requireRecordsManagement();
        $this->requireGet();

        $recordType = rawurldecode((string) $type);
        $reference = $this->Records->previewDocumentReference($recordType);
        if ($reference === null) {
            $this->json(['status' => 'error', 'message' => 'Unable to generate a reference number right now.']);
        }

        $this->json(['status' => 'success', 'reference_no' => $reference]);
    }

    public function edit($id): void
    {
        $this->requireRecordsManagement();
        $this->requireGetOrPost();
        if (!ctype_digit((string) $id)) {
            $this->json(['status' => 'error', 'message' => 'Record not found.']);
        }

        $type = $this->queryValue('record_type');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $type = $this->inputValue('record_type');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            try {
                $record = $this->Records->findDocument($id, $type);
            } catch (\Throwable $e) {
                $this->json(['status' => 'error', 'message' => 'Unable to load this record right now.']);
            }
            if (!$record) {
                $this->json(['status' => 'error', 'message' => 'Record not found.']);
            }
            unset($record->attachment_path);
            $this->json($record);
        }

        $this->save((int) $id);
    }

    public function delete($id): void
    {
        $this->requireRecordsManagement();
        $this->requirePost();
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $this->json(['status' => 'error', 'message' => 'Record not found.']);
        }

        $type = $this->inputValue('record_type');
        if ($type === 'Purchase' && !Auth::canDeletePurchaseRecords()) {
            $this->json([
                'status' => 'error',
                'message' => 'Only Administrators and Managers can delete Purchase Records.',
            ]);
        }
        if ($type === 'Bidding Document' && !Auth::canDeleteBiddingDocuments()) {
            $this->json([
                'status' => 'error',
                'message' => 'Only Administrators and Managers can delete Bidding Documents.',
            ]);
        }
        $this->respondResult($this->Records->deleteDocument((int) $id, $type));
    }

    public function download($id): void
    {
        $this->requireRecordsAccess();
        $this->requireGet();
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $this->json(['status' => 'error', 'message' => 'Bidding document attachment not found.']);
        }

        $record = $this->Records->biddingAttachment((int) $id);
        if (!$record || empty($record->attachment_path) || empty($record->attachment_name)) {
            $this->json(['status' => 'error', 'message' => 'This Bidding Document has no attachment.']);
        }

        $path = $this->Records->storedBiddingAttachmentPath((string) $record->attachment_path);
        if ($path === null) {
            $this->json(['status' => 'error', 'message' => 'Bidding document attachment not found.']);
        }

        $fileName = basename(str_replace('\\', '/', (string) $record->attachment_name));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $inline = $extension === 'pdf' && ($_GET['view'] ?? '') === '1';
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path) ?: 'application/octet-stream';
        $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName) ?: 'bidding-document.' . $extension;

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
            . '; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function addPhoto(): void
    {
        $this->requireRecordsManagement();
        $this->requirePost();
        $user = Auth::user() ?? [];
        $upload = $_FILES['photo'] ?? null;
        if (!is_array($upload)) {
            $this->json(['status' => 'error', 'message' => 'Take or select a photo first.']);
        }

        try {
            $remarks = $_POST['remarks'] ?? '';
            $remarks = is_scalar($remarks) ? trim((string) $remarks) : '';
            $result = $this->Records->savePhoto($upload, (int) ($user['id'] ?? 0), $remarks);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => 'Unable to save this photo right now.'];
        }
        $this->respondResult($result);
    }

    public function photo($id): void
    {
        $this->requireRecordsAccess();
        $this->requireGet();
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $this->json(['status' => 'error', 'message' => 'Photo not found.']);
        }

        $record = $this->Records->photoDocumentation((int) $id);
        $path = $record ? $this->Records->storedPhotoPath((string) $record->storage_name) : null;
        if ($path === null) {
            $this->json(['status' => 'error', 'message' => 'Photo not found.']);
        }

        // The bootstrap may have buffered whitespace before the PHP opening tag.
        // Discard it so the image body matches the Content-Length header exactly.
        while (ob_get_level() > 0) {
            if (!ob_end_clean()) {
                break;
            }
        }

        header('Content-Type: ' . $record->mime_type);
        header('Content-Disposition: inline; filename="photo-' . (int) $id . '.' . $record->file_extension . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    private function save(?int $id): void
    {
        $user = Auth::user() ?? [];
        $upload = $_FILES['attachment'] ?? null;
        if ($upload !== null && !is_array($upload)) {
            $this->json(['status' => 'error', 'message' => 'The selected attachment is invalid.']);
        }
        if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_NO_FILE) {
            $upload = null;
        }
        try {
            $result = $this->Records->saveDocument($id, $this->input(), (int) ($user['id'] ?? 0), $upload);
        } catch (\Throwable $e) {
            $result = ['status' => 500, 'message' => 'Unable to save this record right now.'];
        }

        $this->respondResult($result);
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
            'description', 'items_details', 'quantity', 'remarks', 'supplier_name',
            'title', 'document_type', 'client_name', 'status',
        ] as $key) {
            $data[$key] = $this->inputValue($key);
        }

        return $data;
    }

    private function requireRecordsAccess(): void
    {
        $this->requireLogin();
        if (!Auth::canAccessRecords()) {
            $this->json(['status' => 'error', 'message' => 'You are not authorized to access company records.']);
        }
    }

    private function requireRecordsManagement(): void
    {
        $this->requireLogin();
        if (!Auth::canManageRecords()) {
            $this->json(['status' => 'error', 'message' => 'You are not authorized to manage company records.']);
        }
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

    private function requireGetOrPost(): void
    {
        if (!in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'POST'], true)) {
            $this->json(['status' => 'error', 'message' => 'GET or POST is required.']);
        }
    }
}
