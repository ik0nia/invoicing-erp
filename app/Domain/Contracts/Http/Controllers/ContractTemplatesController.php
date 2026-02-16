<?php

namespace App\Domain\Contracts\Http\Controllers;

use App\Domain\Contracts\Services\ContractTemplateVariables;
use App\Domain\Contracts\Services\TemplateRenderer;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class ContractTemplatesController
{
    public function index(): void
    {
        $this->requireTemplateRole();

        $templates = Database::fetchAll('SELECT * FROM contract_templates ORDER BY created_at DESC, id DESC');
        $variables = (new ContractTemplateVariables())->listPlaceholders();

        Response::view('admin/contracts/templates', [
            'templates' => $templates,
            'variables' => $variables,
        ]);
    }

    public function edit(): void
    {
        $this->requireTemplateRole();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!$id) {
            Response::redirect('/admin/contract-templates');
        }

        $template = Database::fetchOne('SELECT * FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$template) {
            Response::abort(404, 'Model inexistent.');
        }

        $variables = (new ContractTemplateVariables())->listPlaceholders();

        Response::view('admin/contracts/template_edit', [
            'template' => $template,
            'variables' => $variables,
        ]);
    }

    public function save(): void
    {
        $user = $this->requireTemplateRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim((string) ($_POST['name'] ?? ''));
        $docType = trim((string) ($_POST['doc_type'] ?? ''));
        $docKind = trim((string) ($_POST['doc_kind'] ?? ''));
        $appliesTo = trim((string) ($_POST['applies_to'] ?? 'both'));
        $auto = !empty($_POST['auto_on_enrollment']) ? 1 : 0;
        $requiredOnboarding = !empty($_POST['required_onboarding']) ? 1 : 0;
        $priority = isset($_POST['priority']) ? (int) $_POST['priority'] : 100;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $html = (string) ($_POST['html_content'] ?? '');

        if ($docType === '') {
            $docType = $docKind;
        }

        if ($name === '' || $docKind === '' || $docType === '') {
            Session::flash('error', 'Completeaza numele si tipul template-ului.');
            Response::redirect('/admin/contract-templates');
        }

        if ($id > 0) {
            Database::execute(
                'UPDATE contract_templates
                 SET name = :name,
                     template_type = :type,
                     doc_type = :doc_type,
                     doc_kind = :doc_kind,
                     applies_to = :applies_to,
                     auto_on_enrollment = :auto_on,
                     required_onboarding = :required_onboarding,
                     priority = :priority,
                     is_active = :is_active,
                     html_content = :html,
                     updated_at = :updated_at
                 WHERE id = :id',
                [
                    'name' => $name,
                    'type' => $docType,
                    'doc_type' => $docType,
                    'doc_kind' => $docKind,
                    'applies_to' => in_array($appliesTo, ['client', 'supplier', 'both'], true) ? $appliesTo : 'both',
                    'auto_on' => $auto,
                    'required_onboarding' => $requiredOnboarding,
                    'priority' => $priority,
                    'is_active' => $isActive,
                    'html' => $html,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $id,
                ]
            );
            Session::flash('status', 'Template actualizat.');
            Response::redirect('/admin/contract-templates');
        }

        Database::execute(
            'INSERT INTO contract_templates (
                name,
                template_type,
                doc_type,
                doc_kind,
                applies_to,
                auto_on_enrollment,
                required_onboarding,
                priority,
                is_active,
                html_content,
                created_by_user_id,
                created_at
            ) VALUES (
                :name,
                :type,
                :doc_type,
                :doc_kind,
                :applies_to,
                :auto_on,
                :required_onboarding,
                :priority,
                :is_active,
                :html,
                :user_id,
                :created_at
            )',
            [
                'name' => $name,
                'type' => $docType,
                'doc_type' => $docType,
                'doc_kind' => $docKind,
                'applies_to' => in_array($appliesTo, ['client', 'supplier', 'both'], true) ? $appliesTo : 'both',
                'auto_on' => $auto,
                'required_onboarding' => $requiredOnboarding,
                'priority' => $priority,
                'is_active' => $isActive,
                'html' => $html,
                'user_id' => $user ? $user->id : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
        Session::flash('status', 'Template creat.');
        Response::redirect('/admin/contract-templates');
    }

    public function update(): void
    {
        $this->requireTemplateRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            Response::redirect('/admin/contract-templates');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $docType = trim((string) ($_POST['doc_type'] ?? ''));
        $docKind = trim((string) ($_POST['doc_kind'] ?? ''));
        $appliesTo = trim((string) ($_POST['applies_to'] ?? 'both'));
        $auto = !empty($_POST['auto_on_enrollment']) ? 1 : 0;
        $requiredOnboarding = !empty($_POST['required_onboarding']) ? 1 : 0;
        $priority = isset($_POST['priority']) ? (int) $_POST['priority'] : 100;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $html = (string) ($_POST['html_content'] ?? '');

        if ($docType === '') {
            $docType = $docKind;
        }

        if ($name === '' || $docKind === '' || $docType === '') {
            Session::flash('error', 'Completeaza numele si tipul documentului.');
            Response::redirect('/admin/contract-templates/edit?id=' . $id);
        }

        Database::execute(
            'UPDATE contract_templates
             SET name = :name,
                 template_type = :type,
                 doc_type = :doc_type,
                 doc_kind = :doc_kind,
                 applies_to = :applies_to,
                 auto_on_enrollment = :auto_on,
                 required_onboarding = :required_onboarding,
                 priority = :priority,
                 is_active = :is_active,
                 html_content = :html,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'name' => $name,
                'type' => $docType,
                'doc_type' => $docType,
                'doc_kind' => $docKind,
                'applies_to' => in_array($appliesTo, ['client', 'supplier', 'both'], true) ? $appliesTo : 'both',
                'auto_on' => $auto,
                'required_onboarding' => $requiredOnboarding,
                'priority' => $priority,
                'is_active' => $isActive,
                'html' => $html,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );

        Session::flash('status', 'Model actualizat.');
        Response::redirect('/admin/contract-templates/edit?id=' . $id);
    }

    public function duplicate(): void
    {
        $user = $this->requireTemplateRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            Response::redirect('/admin/contract-templates');
        }

        $template = Database::fetchOne('SELECT * FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$template) {
            Response::abort(404, 'Model inexistent.');
        }

        Database::execute(
            'INSERT INTO contract_templates (
                name,
                template_type,
                doc_type,
                doc_kind,
                applies_to,
                auto_on_enrollment,
                required_onboarding,
                priority,
                is_active,
                html_content,
                created_by_user_id,
                created_at
            ) VALUES (
                :name,
                :type,
                :doc_type,
                :doc_kind,
                :applies_to,
                :auto_on,
                :required_onboarding,
                :priority,
                :is_active,
                :html,
                :user_id,
                :created_at
            )',
            [
                'name' => (string) ($template['name'] ?? '') . ' (copie)',
                'type' => (string) ($template['doc_type'] ?? $template['template_type'] ?? ''),
                'doc_type' => (string) ($template['doc_type'] ?? $template['template_type'] ?? ''),
                'doc_kind' => (string) ($template['doc_kind'] ?? 'contract'),
                'applies_to' => (string) ($template['applies_to'] ?? 'both'),
                'auto_on' => !empty($template['auto_on_enrollment']) ? 1 : 0,
                'required_onboarding' => !empty($template['required_onboarding']) ? 1 : 0,
                'priority' => (int) ($template['priority'] ?? 100),
                'is_active' => !empty($template['is_active']) ? 1 : 0,
                'html' => (string) ($template['html_content'] ?? ''),
                'user_id' => $user ? $user->id : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        Session::flash('status', 'Model duplicat.');
        Response::redirect('/admin/contract-templates');
    }

    public function preview(): void
    {
        $this->requireTemplateRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $template = null;
        if ($id) {
            $template = Database::fetchOne('SELECT * FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $id]);
        }
        if (!$template) {
            Response::abort(404, 'Model inexistent.');
        }

        $partnerCui = preg_replace('/\D+/', '', (string) ($_POST['partner_cui'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $clientCui = preg_replace('/\D+/', '', (string) ($_POST['client_cui'] ?? ''));

        $variablesService = new ContractTemplateVariables();
        $renderer = new TemplateRenderer();
        $vars = $variablesService->buildVariables(
            $partnerCui !== '' ? $partnerCui : null,
            $supplierCui !== '' ? $supplierCui : null,
            $clientCui !== '' ? $clientCui : null,
            [
                'title' => (string) ($template['name'] ?? ''),
                'created_at' => date('Y-m-d'),
                'contract_date' => date('Y-m-d'),
                'doc_type' => (string) ($template['doc_type'] ?? $template['template_type'] ?? 'contract'),
            ]
        );
        $rendered = $renderer->render((string) ($template['html_content'] ?? ''), $vars);

        Response::view('admin/contracts/template_preview', [
            'template' => $template,
            'rendered' => $rendered,
            'sample' => [
                'partner_cui' => $partnerCui,
                'supplier_cui' => $supplierCui,
                'client_cui' => $clientCui,
            ],
        ]);
    }

    private function requireTemplateRole(): ?\App\Domain\Users\Models\User
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (!$user || !($user->hasRole('super_admin') || $user->hasRole('admin'))) {
            Response::abort(403, 'Acces interzis.');
        }

        return $user;
    }
}
