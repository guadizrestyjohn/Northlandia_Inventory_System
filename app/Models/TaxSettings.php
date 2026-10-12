<?php

namespace Models;

use Core\Model;
use PDOException;

class TaxSettings extends Model
{
    public function all(): array
    {
        return $this->db->query(
            'SELECT tax_id, tax_name, tax_rate, calculation_mode, status, updated_at
             FROM tax_settings ORDER BY tax_id DESC'
        )->fetchAll();
    }

    public function activeConfiguration(): array
    {
        $active = $this->db->query(
            "SELECT tax_id, tax_name, tax_rate, calculation_mode, status
             FROM tax_settings WHERE status = 'Active' ORDER BY tax_id"
        )->fetchAll();
        if (count($active) !== 1) {
            return [
                'configuration' => null,
                'message' => count($active) === 0
                    ? 'No active tax configuration is available. Ask an authorized manager to configure Tax Settings before creating Orders.'
                    : 'Multiple tax configurations are Active. New Orders support one active tax policy at a time. Deactivate the unused policy in Tax Settings before creating Orders.',
            ];
        }

        $row = $active[0];
        $rate = Orders::parseTaxRate((string) $row->tax_rate);
        if (trim((string) $row->tax_name) === '' || $rate === null
            || !in_array($row->calculation_mode, ['Inclusive', 'Exclusive'], true)) {
            return [
                'configuration' => null,
                'message' => 'The active tax configuration is invalid. An authorized manager must correct Tax Settings before creating Orders.',
            ];
        }

        return [
            'configuration' => [
                'tax_id' => (int) $row->tax_id,
                'tax_name' => (string) $row->tax_name,
                'tax_rate' => Orders::formatTaxRate($rate),
                'calculation_mode' => (string) $row->calculation_mode,
            ],
            'message' => null,
        ];
    }

    public function save(array $data, ?int $userId): array
    {
        $id = (string) ($data['tax_id'] ?? '');
        $name = trim((string) ($data['tax_name'] ?? ''));
        $rate = (string) ($data['tax_rate'] ?? '');
        $mode = (string) ($data['calculation_mode'] ?? '');
        $status = (string) ($data['status'] ?? '');

        if ($name === '' || strlen($name) > 50) {
            return ['success' => false, 'message' => 'Enter a tax name up to 50 characters.'];
        }
        if (Orders::parseTaxRate($rate) === null) {
            return ['success' => false, 'message' => 'Enter a valid tax rate from 0.00% to 999.99%.'];
        }
        if (!in_array($mode, ['Inclusive', 'Exclusive'], true)) {
            return ['success' => false, 'message' => 'Select Inclusive or Exclusive calculation mode.'];
        }
        if (!in_array($status, ['Active', 'Inactive'], true)) {
            return ['success' => false, 'message' => 'Select Active or Inactive status.'];
        }
        if ($id !== '' && (!ctype_digit($id) || (int) $id < 1)) {
            return ['success' => false, 'message' => 'Select a valid tax configuration.'];
        }

        $lockAcquired = false;
        try {
            // Serialize all writes, including first-row inserts, where row locks alone
            // cannot reliably prevent two concurrent requests from creating VAT.
            $lockAcquired = (int) $this->db
                ->query("SELECT GET_LOCK('northlandia_tax_settings_write', 10)")
                ->fetchColumn() === 1;
            if (!$lockAcquired) {
                return ['success' => false, 'status' => 409, 'message' => 'Tax Settings are being updated by another user. Try again shortly.'];
            }

            $this->db->beginTransaction();
            $this->db->query('SELECT tax_id FROM tax_settings FOR UPDATE')->fetchAll();
            $current = null;
            if ($id !== '') {
                $check = $this->db->prepare('SELECT tax_id, tax_name FROM tax_settings WHERE tax_id = ?');
                $check->execute([$id]);
                $current = $check->fetch();
                if (!$current) {
                    $this->db->rollBack();
                    return ['success' => false, 'status' => 404, 'message' => 'Tax configuration not found.'];
                }
            }

            $isVat = strcasecmp($name, 'VAT') === 0;
            $name = $isVat ? 'VAT' : $name;
            $vatRows = $this->db->query(
                "SELECT tax_id, status FROM tax_settings WHERE UPPER(TRIM(tax_name)) = 'VAT' ORDER BY tax_id"
            )->fetchAll();

            if ($isVat) {
                if ($current && strcasecmp(trim((string) $current->tax_name), 'VAT') !== 0) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'A distinct tax record cannot be converted into VAT. Add or edit the VAT configuration separately.'];
                }
                if ($id === '' && $vatRows) {
                    $activeVat = array_values(array_filter($vatRows, static fn($row) => $row->status === 'Active'));
                    if (count($activeVat) === 1) {
                        $id = (string) $activeVat[0]->tax_id;
                    } elseif (count($vatRows) === 1) {
                        $id = (string) $vatRows[0]->tax_id;
                    } else {
                        $this->db->rollBack();
                        return ['success' => false, 'status' => 409, 'message' => 'More than one VAT record exists. Select the VAT record to edit so historical records remain intact.'];
                    }
                }
            } elseif ($current && strcasecmp(trim((string) $current->tax_name), 'VAT') === 0) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'The VAT configuration must keep the tax name VAT.'];
            }

            if ($status === 'Active') {
                $activeCheck = $this->db->prepare(
                    "SELECT tax_id, tax_name FROM tax_settings WHERE status = 'Active' AND tax_id <> ? FOR UPDATE"
                );
                $activeCheck->execute([$id === '' ? 0 : (int) $id]);
                if ($activeCheck->fetch()) {
                    $this->db->rollBack();
                    return ['success' => false, 'status' => 409, 'message' => 'Another tax configuration is already Active. New Orders use one active tax policy at a time; deactivate the other policy explicitly before activating this one.'];
                }
            }

            if ($id !== '') {
                $stmt = $this->db->prepare(
                    'UPDATE tax_settings SET tax_name = ?, tax_rate = ?, calculation_mode = ?, status = ?, updated_by = ? WHERE tax_id = ?'
                );
                $stmt->execute([$name, $rate, $mode, $status, $userId, $id]);
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO tax_settings (tax_name, tax_rate, calculation_mode, status, updated_by) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$name, $rate, $mode, $status, $userId]);
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Tax Settings saved successfully.'];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if (($e->getCode() ?? '') === '23000') {
                return ['success' => false, 'status' => 409, 'message' => 'A VAT configuration is already Active. Edit the existing VAT record or deactivate the other Active tax policy first.'];
            }
            return ['success' => false, 'status' => 500, 'message' => 'Unable to save Tax Settings.'];
        } finally {
            if ($lockAcquired) {
                $this->db->query("SELECT RELEASE_LOCK('northlandia_tax_settings_write')");
            }
        }
    }
}
