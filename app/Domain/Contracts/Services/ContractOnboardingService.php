<?php

namespace App\Domain\Contracts\Services;

use App\Support\Audit;
use App\Support\Database;
use App\Support\Logger;

class ContractOnboardingService
{
    private ContractTemplateService $templateService;
    private DocumentNumberService $numberService;

    public function __construct(?ContractTemplateService $templateService = null, ?DocumentNumberService $numberService = null)
    {
        $this->templateService = $templateService ?? new ContractTemplateService();
        $this->numberService = $numberService ?? new DocumentNumberService();
    }

    public function ensureDraftContractForEnrollment(string $linkType, string $partnerCui, ?string $supplierCui, ?string $clientCui): array
    {
        try {
            if (!Database::tableExists('contracts')) {
                Logger::logWarning('contract_table_missing', []);
                return ['created_count' => 0, 'total_templates' => 0, 'has_templates' => false];
            }

            $templates = $this->templateService->getRequiredOnboardingTemplates($linkType);
            if (empty($templates)) {
                Logger::logWarning('contract_template_missing', ['role' => $linkType]);
                return ['created_count' => 0, 'total_templates' => 0, 'has_templates' => false];
            }

            $created = 0;
            $titles = [];
            $warnings = [];
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
                $docType = trim((string) ($template['doc_type'] ?? $template['template_type'] ?? $docKind));
                if ($docType === '') {
                    $docType = 'contract';
                }
                $meta = json_encode([
                    'doc_kind' => $docKind,
                ], JSON_UNESCAPED_UNICODE);
                $number = null;
                try {
                    $number = $this->numberService->allocateNumber($docType);
                } catch (\Throwable $exception) {
                    Logger::logWarning('document_number_allocate_failed', [
                        'doc_type' => $docType,
                        'error' => $exception->getMessage(),
                    ]);
                    $warnings[] = 'Numerotarea automata nu este disponibila pentru doc_type "' . $docType . '".';
                }

                Database::execute(
                    'INSERT INTO contracts (
                        template_id,
                        partner_cui,
                        supplier_cui,
                        client_cui,
                        title,
                        doc_type,
                        contract_date,
                        doc_no,
                        doc_series,
                        doc_full_no,
                        doc_assigned_at,
                        status,
                        required_onboarding,
                        metadata_json,
                        created_at
                    ) VALUES (
                        :template_id,
                        :partner_cui,
                        :supplier_cui,
                        :client_cui,
                        :title,
                        :doc_type,
                        :contract_date,
                        :doc_no,
                        :doc_series,
                        :doc_full_no,
                        :doc_assigned_at,
                        :status,
                        :required_onboarding,
                        :meta,
                        :created_at
                    )',
                    [
                        'template_id' => $templateId,
                        'partner_cui' => $partnerCui !== '' ? $partnerCui : null,
                        'supplier_cui' => $supplierCui !== '' ? $supplierCui : null,
                        'client_cui' => $clientCui !== '' ? $clientCui : null,
                        'title' => $title !== '' ? $title : 'Contract',
                        'doc_type' => $docType,
                        'contract_date' => date('Y-m-d'),
                        'doc_no' => isset($number['no']) ? (int) $number['no'] : null,
                        'doc_series' => isset($number['series']) && $number['series'] !== '' ? (string) $number['series'] : null,
                        'doc_full_no' => isset($number['full_no']) && $number['full_no'] !== '' ? (string) $number['full_no'] : null,
                        'doc_assigned_at' => isset($number['no']) ? date('Y-m-d H:i:s') : null,
                        'status' => 'draft',
                        'required_onboarding' => 1,
                        'meta' => $meta,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]
                );
                $contractId = (int) Database::lastInsertId();
                if ($contractId > 0 && isset($number['no'])) {
                    Audit::record('contract.number_assigned', 'contract', $contractId, [
                        'doc_type' => $docType,
                        'doc_full_no' => (string) ($number['full_no'] ?? ''),
                        'rows_count' => 1,
                    ]);
                }
                $created++;
                $titles[] = [
                    'title' => $title !== '' ? $title : 'Contract',
                    'doc_kind' => $docKind !== '' ? $docKind : 'contract',
                    'doc_type' => $docType,
                ];
            }

            return [
                'created_count' => $created,
                'total_templates' => count($templates),
                'has_templates' => true,
                'created_titles' => $titles,
                'warnings' => array_values(array_unique($warnings)),
            ];
        } catch (\Throwable $exception) {
            Logger::logWarning('contract_onboarding_failed', ['error' => $exception->getMessage()]);
            return [
                'created_count' => 0,
                'total_templates' => 0,
                'has_templates' => false,
                'created_titles' => [],
                'warnings' => [],
            ];
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
