<?php

namespace Controllers;

use Core\Auth;
use Core\BaseController;
use Models\TaxSettings;

class TaxSettingsController extends BaseController
{
    public function index(): void
    {
        Auth::requirePermission('tax_settings.manage');
        try {
            $settings = (new TaxSettings())->all();
            $loadError = '';
        } catch (\Throwable $e) {
            $settings = [];
            $loadError = 'Tax Settings could not be loaded. Apply the tax calculation database migration and try again.';
        }

        $this->view('TaxSettings/index', [
            'title' => 'Tax Settings',
            'taxSettings' => $settings,
            'taxSettingsLoadError' => $loadError,
        ]);
    }
}
