<?php

namespace Api;

use Core\Auth;
use Core\BaseController;

abstract class BaseApiController extends BaseController
{
    protected function requireLogin(): void
    {
        if (!Auth::check()) {
            $this->respond(['success' => false, 'message' => 'You must be logged in.'], 401);
        }
    }

    protected function requirePermission(string $permission): void
    {
        $this->requireLogin();
        if (!Auth::can($permission)) {
            $this->respond([
                'success' => false,
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        }
    }

    protected function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->respond(['success' => false, 'message' => 'POST is required.'], 405);
        }
    }

    protected function requireGet(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->respond(['success' => false, 'message' => 'GET is required.'], 405);
        }
    }

    protected function requireGetOrPost(): void
    {
        if (!in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'POST'], true)) {
            $this->respond(['success' => false, 'message' => 'GET or POST is required.'], 405);
        }
    }

    protected function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        $this->json($data);
    }
}
