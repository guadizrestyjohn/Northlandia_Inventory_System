<?php

namespace Api\TaxSettings;

use Api\BaseApiController;
use Core\Auth;
use Models\TaxSettings;

class TaxSettingsController extends BaseApiController
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
            $this->respond(['data' => $this->taxSettings->all()]);
        } catch (\Throwable $e) {
            $this->respondError('Tax Settings could not be loaded.', 500);
        }
    }

    public function save(): void
    {
        $this->requirePermission('tax_settings.manage');
        $this->requirePost();
        foreach (['tax_id', 'tax_name', 'tax_rate', 'calculation_mode', 'status'] as $key) {
            if (isset($_POST[$key]) && !is_scalar($_POST[$key])) {
                $this->respondError('Enter valid Tax Settings values.', 422);
            }
        }
        $user = Auth::user() ?? [];
        $this->respondResult($this->taxSettings->save(
            $_POST,
            isset($user['id']) ? (int) $user['id'] : null
        ), 422);
    }
}
