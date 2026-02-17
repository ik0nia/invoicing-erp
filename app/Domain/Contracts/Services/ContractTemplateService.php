<?php

namespace App\Domain\Contracts\Services;

use App\Support\Database;

class ContractTemplateService
{
    public function getActiveTemplateIdForType(string $type): ?int
    {
        $type = trim($type);
        if ($type === '') {
            return null;
        }
        if (!Database::tableExists('contract_templates')) {
            return null;
        }
        $hasActive = Database::columnExists('contract_templates', 'is_active');
        $where = 'template_type = :type';
        if ($hasActive) {
            $where .= ' AND is_active = 1';
        }
        $row = Database::fetchOne(
            'SELECT id FROM contract_templates WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT 1',
            ['type' => $type]
        );
        if (!$row) {
            return null;
        }

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    public function getAutoTemplatesForEnrollment(string $role): array
    {
        if (!Database::tableExists('contract_templates')) {
            return [];
        }
        $role = $role === 'supplier' ? 'supplier' : 'client';
        $hasActive = Database::columnExists('contract_templates', 'is_active');
        $hasAuto = Database::columnExists('contract_templates', 'auto_on_enrollment');
        $hasApplies = Database::columnExists('contract_templates', 'applies_to');
        if (!$hasAuto || !$hasApplies) {
            return [];
        }

        $where = 'auto_on_enrollment = 1 AND (applies_to = :role OR applies_to = :both)';
        if ($hasActive) {
            $where .= ' AND is_active = 1';
        }

        return Database::fetchAll(
            'SELECT * FROM contract_templates WHERE ' . $where . ' ORDER BY priority ASC, id ASC',
            ['role' => $role, 'both' => 'both']
        );
    }

    public function getRequiredOnboardingTemplates(string $role): array
    {
        if (!Database::tableExists('contract_templates')) {
            return [];
        }

        $role = $role === 'supplier' ? 'supplier' : 'client';
        $hasActive = Database::columnExists('contract_templates', 'is_active');
        $hasAuto = Database::columnExists('contract_templates', 'auto_on_enrollment');
        $hasApplies = Database::columnExists('contract_templates', 'applies_to');
        if (!$hasAuto || !$hasApplies) {
            return [];
        }

        $where = 'auto_on_enrollment = 1 AND (applies_to = :role OR applies_to = :both)';
        if (Database::columnExists('contract_templates', 'required_onboarding')) {
            $where .= ' AND required_onboarding = 1';
        }
        if ($hasActive) {
            $where .= ' AND is_active = 1';
        }

        return Database::fetchAll(
            'SELECT * FROM contract_templates WHERE ' . $where . ' ORDER BY priority ASC, id ASC',
            ['role' => $role, 'both' => 'both']
        );
    }
}
