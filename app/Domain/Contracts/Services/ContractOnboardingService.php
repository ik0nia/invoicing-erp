<?php

namespace App\Domain\Contracts\Services;

use App\Support\Database;
use App\Support\Logger;

class ContractOnboardingService
{
    private ContractTemplateService $templateService;

    public function __construct(?ContractTemplateService $templateService = null)
    {
        $this->templateService = $templateService ?? new ContractTemplateService();
    }

    public function ensureDraftContractForEnrollment(string $linkType, string $partnerCui, ?string $supplierCui, ?string $clientCui): array
    {
        try {
            if (!Database::tableExists('contracts')) {
                Logger::logWarning('contract_table_missing', []);
                return ['created_count' => 0, 'total_templates' => 0, 'has_templates' => false];
            }

            $templates = $this->templateService->getAutoTemplatesForEnrollment($linkType);
            if (empty($templates)) {
                Logger::logWarning('contract_template_missing', ['role' => $linkType]);
                return ['created_count' => 0, 'total_templates' => 0, 'has_templates' => false];
            }

            $created = 0;
            foreach ($templates as $template) {
                $templateId = (int) ($template['id'] ?? 0);
                if (!$templateId) {
                    continue;
                }
                $existing = $this->findExisting($templateId, $partnerCui, $supplierCui, $clientCui, $linkType);
                if ($existing) {
                    continue;
                }

                $title = (string) ($template['name'] ?? '');
                $docKind = (string) ($template['doc_kind'] ?? '');
                $meta = json_encode([
                    'doc_kind' => $docKind,
                ], JSON_UNESCAPED_UNICODE);

                Database::execute(
                    'INSERT INTO contracts (template_id, partner_cui, supplier_cui, client_cui, title, status, metadata_json, created_at)
                     VALUES (:template_id, :partner_cui, :supplier_cui, :client_cui, :title, :status, :meta, :created_at)',
                    [
                        'template_id' => $templateId,
                        'partner_cui' => $partnerCui !== '' ? $partnerCui : null,
                        'supplier_cui' => $supplierCui !== '' ? $supplierCui : null,
                        'client_cui' => $clientCui !== '' ? $clientCui : null,
                        'title' => $title !== '' ? $title : 'Contract',
                        'status' => 'draft',
                        'meta' => $meta,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]
                );
                $created++;
            }

            return [
                'created_count' => $created,
                'total_templates' => count($templates),
                'has_templates' => true,
            ];
        } catch (\Throwable $exception) {
            Logger::logWarning('contract_onboarding_failed', ['error' => $exception->getMessage()]);
            return ['created_count' => 0, 'total_templates' => 0, 'has_templates' => false];
        }
    }

    private function findExisting(int $templateId, string $partnerCui, ?string $supplierCui, ?string $clientCui, string $linkType): ?array
    {
        if ($linkType === 'supplier') {
            return Database::fetchOne(
                'SELECT id FROM contracts WHERE template_id = :template AND (partner_cui = :partner OR supplier_cui = :supplier) LIMIT 1',
                [
                    'template' => $templateId,
                    'partner' => $partnerCui,
                    'supplier' => $partnerCui,
                ]
            );
        }

        $params = [
            'template' => $templateId,
            'partner' => $partnerCui,
            'client' => $clientCui ?? $partnerCui,
        ];
        $sql = 'SELECT id FROM contracts WHERE template_id = :template AND (partner_cui = :partner OR client_cui = :client)';
        if ($supplierCui !== null && $supplierCui !== '') {
            $sql .= ' AND supplier_cui = :supplier';
            $params['supplier'] = $supplierCui;
        }
        $sql .= ' LIMIT 1';

        return Database::fetchOne($sql, $params);
    }
}
