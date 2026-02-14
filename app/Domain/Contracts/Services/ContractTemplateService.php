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
        $row = Database::fetchOne(
            'SELECT id FROM contract_templates WHERE template_type = :type ORDER BY created_at DESC, id DESC LIMIT 1',
            ['type' => $type]
        );
        if (!$row) {
            return null;
        }

        return isset($row['id']) ? (int) $row['id'] : null;
    }
}
