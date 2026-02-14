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
        $type = trim((string) ($_POST['template_type'] ?? ''));
        $html = (string) ($_POST['html_content'] ?? '');

        if ($name === '' || $type === '') {
            Session::flash('error', 'Completeaza numele si tipul template-ului.');
            Response::redirect('/admin/contract-templates');
        }

        if ($id > 0) {
            Database::execute(
                'UPDATE contract_templates SET name = :name, template_type = :type, html_content = :html, updated_at = :updated_at WHERE id = :id',
                [
                    'name' => $name,
                    'type' => $type,
                    'html' => $html,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $id,
                ]
            );
            Session::flash('status', 'Template actualizat.');
            Response::redirect('/admin/contract-templates');
        }

        Database::execute(
            'INSERT INTO contract_templates (name, template_type, html_content, created_by_user_id, created_at)
             VALUES (:name, :type, :html, :user_id, :created_at)',
            [
                'name' => $name,
                'type' => $type,
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
        $type = trim((string) ($_POST['template_type'] ?? ''));
        $html = (string) ($_POST['html_content'] ?? '');

        if ($name === '' || $type === '') {
            Session::flash('error', 'Completeaza numele si tipul modelului.');
            Response::redirect('/admin/contract-templates/edit?id=' . $id);
        }

        Database::execute(
            'UPDATE contract_templates SET name = :name, template_type = :type, html_content = :html, updated_at = :updated_at WHERE id = :id',
            [
                'name' => $name,
                'type' => $type,
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
            'INSERT INTO contract_templates (name, template_type, html_content, created_by_user_id, created_at)
             VALUES (:name, :type, :html, :user_id, :created_at)',
            [
                'name' => (string) ($template['name'] ?? '') . ' (copie)',
                'type' => (string) ($template['template_type'] ?? ''),
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
            ['title' => (string) ($template['name'] ?? ''), 'created_at' => date('Y-m-d')]
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
