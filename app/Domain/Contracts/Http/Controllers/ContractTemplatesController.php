<?php

namespace App\Domain\Contracts\Http\Controllers;

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

        Response::view('admin/contracts/templates', [
            'templates' => $templates,
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
