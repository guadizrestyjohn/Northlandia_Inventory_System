<?php

namespace Api;

use Core\Auth;
use Core\BaseController;

abstract class BaseApiController extends BaseController
{
    protected function requireLogin(): void
    {
        if (!Auth::check()) {
            $this->respond(['status' => 'error', 'message' => 'You must be logged in.'], 401);
        }
    }

    protected function requirePermission(string $permission): void
    {
        $this->requireLogin();
        if (!Auth::can($permission)) {
            $this->respond([
                'status' => 'error',
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        }
    }

    protected function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->respond(['status' => 'error', 'message' => 'POST is required.'], 405);
        }
    }

    protected function postString(string $key, bool $trim = true): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;
        return $trim ? trim($value) : $value;
    }

    protected function requireGet(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->respond(['status' => 'error', 'message' => 'GET is required.'], 405);
        }
    }

    protected function requireGetOrPost(): void
    {
        if (!in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'POST'], true)) {
            $this->respond(['status' => 'error', 'message' => 'GET or POST is required.'], 405);
        }
    }

    protected function respond($data, int $httpStatus = 200): void
    {
        http_response_code($httpStatus);
        $this->json($data);
    }

    protected function respondError(string $message, int $httpStatus = 422): void
    {
        $this->respond(['status' => 'error', 'message' => $message], $httpStatus);
    }

    protected function respondResult(array $result, int $defaultErrorStatus = 500, int $successHttpStatus = 200): void
    {
        $successful = !empty($result['success']);
        $httpStatus = $successful ? $successHttpStatus : $defaultErrorStatus;
        if (!$successful && isset($result['status']) && is_numeric($result['status'])) {
            $candidateStatus = (int) $result['status'];
            if (in_array($candidateStatus, [400, 401, 403, 404, 405, 409, 422, 500], true)) {
                $httpStatus = $candidateStatus;
            }
        }
        unset($result['success'], $result['status']);
        if (empty($result['message'])) {
            $result['message'] = $successful
                ? 'Operation completed successfully.'
                : 'Unable to complete the operation.';
        }
        $result['status'] = $successful ? 'success' : 'error';
        $this->respond($result, $httpStatus);
    }
}
