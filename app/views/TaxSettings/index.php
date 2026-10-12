<?php
$taxSettings = $taxSettings ?? [];
$taxSettingsLoadError = $taxSettingsLoadError ?? '';
$activeTaxCount = count(array_filter($taxSettings, static fn($setting) => $setting->status === 'Active'));
$activeVatCount = count(array_filter($taxSettings, static fn($setting) => $setting->status === 'Active' && strcasecmp(trim((string) $setting->tax_name), 'VAT') === 0));
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Tax Settings</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" id="tax-setting-new">
                <i class="fas fa-plus"></i> New Tax Configuration
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if ($taxSettingsLoadError !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($taxSettingsLoadError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($activeVatCount > 1): ?>
            <div class="alert alert-danger" role="alert">More than one VAT configuration is Active. Review the rows below, choose the intended VAT record, and set the other conflicting records to Inactive. Historical rows are preserved.</div>
        <?php endif; ?>
        <?php if ($activeTaxCount > 1): ?>
            <div class="alert alert-danger" role="alert">Multiple tax configurations are Active. New Orders use one tax policy at a time. Deactivate unused policies explicitly before creating Orders.</div>
        <?php elseif ($activeTaxCount === 0): ?>
            <div class="alert alert-warning" role="alert">There is no Active tax configuration. New Orders are blocked until a tax policy is configured.</div>
        <?php endif; ?>
        <p>VAT uses one configuration with a selectable Inclusive or Exclusive mode. Editing VAT updates its existing record. Other tax records remain separate. Only one tax policy can be Active for new Orders; saving never deactivates other records automatically. Existing Orders keep their saved tax calculation.</p>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tax-settings-table">
                <thead><tr><th>Name</th><th>Rate</th><th>Calculation Mode</th><th>Status</th><th>Last Updated</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($taxSettings as $setting): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $setting->tax_name, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(number_format((float) $setting->tax_rate, 2), ENT_QUOTES, 'UTF-8') ?>%</td>
                            <td><?= htmlspecialchars((string) $setting->calculation_mode, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $setting->status, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $setting->updated_at, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><button type="button" class="btn btn-sm btn-warning js-edit-tax-setting"
                                data-tax-id="<?= (int) $setting->tax_id ?>"
                                data-tax-name="<?= htmlspecialchars((string) $setting->tax_name, ENT_QUOTES, 'UTF-8') ?>"
                                data-tax-rate="<?= htmlspecialchars((string) $setting->tax_rate, ENT_QUOTES, 'UTF-8') ?>"
                                data-calculation-mode="<?= htmlspecialchars((string) $setting->calculation_mode, ENT_QUOTES, 'UTF-8') ?>"
                                data-status="<?= htmlspecialchars((string) $setting->status, ENT_QUOTES, 'UTF-8') ?>">Edit</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$taxSettings): ?><tr><td colspan="6" class="text-center text-muted">No tax configuration has been created. Add one before creating Orders.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="tax-setting-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" id="tax-setting-form" method="post" action="<?= BASE_URL ?>/api/TaxSettings/save">
            <div class="modal-header">
                <h5 class="modal-title" id="tax-setting-modal-title">New Tax Configuration</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="tax_id" id="tax-setting-id">
                <div class="form-group">
                    <label for="tax-setting-name">Tax Name</label>
                    <input class="form-control" id="tax-setting-name" name="tax_name" maxlength="50" required placeholder="VAT or another distinct tax">
                </div>
                <div class="form-group">
                    <label for="tax-setting-rate">Tax Rate (%)</label>
                    <input class="form-control" id="tax-setting-rate" name="tax_rate" type="number" min="0" max="999.99" step="0.01" required>
                    <small class="form-text text-muted">Enter the rate approved by management. No rate is preconfigured.</small>
                </div>
                <div class="form-group">
                    <label for="tax-setting-mode">Calculation Mode</label>
                    <select class="form-control" id="tax-setting-mode" name="calculation_mode" required>
                        <option value="Inclusive">Inclusive — entered Order amount already includes tax</option>
                        <option value="Exclusive">Exclusive — tax is added to the entered subtotal</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="tax-setting-status">Status</label>
                    <select class="form-control" id="tax-setting-status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="tax-setting-save">Save Tax Settings</button>
            </div>
        </form>
    </div>
</div>
