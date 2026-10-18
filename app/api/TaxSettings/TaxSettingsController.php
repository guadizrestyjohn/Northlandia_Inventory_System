<?php

namespace Api\TaxSettings;

use Core\BaseController;
use Core\Auth;
use Models\TaxSettings;

class TaxSettingsController extends BaseController
{
    private TaxSettings $taxSettings;

    public function __construct()
    {
        $this->taxSettings = new TaxSettings();
    }

    public function all(): void
    {
        $this->requirePermission('tax_settings.manage');
        $this->requireGet();
        try {
            $this->json(['data' => $this->taxSettings->all()]);
        } catch (\Throwable $e) {
            $this->json(['status' => 'error', 'message' => 'Tax Settings could not be loaded.']);
        }
    }

    public function save(): void
    {
        $this->requirePermission('tax_settings.manage');
        $this->requirePost();
        foreach (['tax_id', 'tax_name', 'tax_rate', 'calculation_mode', 'status'] as $key) {
            if (isset($_POST[$key]) && !is_scalar($_POST[$key])) {
                $this->json(['status' => 'error', 'message' => 'Enter valid Tax Settings values.']);
            }
        }
        $user = Auth::user() ?? [];
        $this->respondResult($this->taxSettings->save(
            $_POST,
            isset($user['id']) ? (int) $user['id'] : null
        ));
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
}
