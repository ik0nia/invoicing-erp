<?php

namespace App\Domain\Audit\Http\Controllers;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Url;

class AuditController
{
    public function index(): void
    {
        Auth::requireSuperAdmin();

        if (!Database::tableExists('audit_log')) {
            Response::abort(500, 'Lipseste tabela audit_log.');
        }

        $filters = [
            'action' => trim((string) ($_GET['action'] ?? '')),
            'entity_type' => trim((string) ($_GET['entity_type'] ?? '')),
            'entity_id' => trim((string) ($_GET['entity_id'] ?? '')),
            'actor_user_id' => trim((string) ($_GET['actor_user_id'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'per_page' => (int) ($_GET['per_page'] ?? 50),
            'page' => (int) ($_GET['page'] ?? 1),
        ];

        $allowedPerPage = [25, 50, 100];
        if (!in_array($filters['per_page'], $allowedPerPage, true)) {
            $filters['per_page'] = 50;
        }
        if ($filters['page'] < 1) {
            $filters['page'] = 1;
        }

        $where = [];
        $params = [];

        if ($filters['action'] !== '') {
            $where[] = 'action LIKE :action';
            $params['action'] = '%' . $filters['action'] . '%';
        }
        if ($filters['entity_type'] !== '') {
            $where[] = 'entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }
        if ($filters['entity_id'] !== '' && preg_match('/^\d+$/', $filters['entity_id'])) {
            $where[] = 'entity_id = :entity_id';
            $params['entity_id'] = (int) $filters['entity_id'];
        }
        if ($filters['actor_user_id'] !== '' && preg_match('/^\d+$/', $filters['actor_user_id'])) {
            $where[] = 'actor_user_id = :actor_user_id';
            $params['actor_user_id'] = (int) $filters['actor_user_id'];
        }
        if ($filters['date_from'] !== '') {
            $where[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if ($filters['date_to'] !== '') {
            $where[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $total = (int) (Database::fetchValue('SELECT COUNT(*) FROM audit_log ' . $whereSql, $params) ?? 0);
        $totalPages = (int) max(1, ceil($total / $filters['per_page']));
        if ($filters['page'] > $totalPages) {
            $filters['page'] = $totalPages;
        }
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $offset = max(0, (int) $offset);

        $sql = 'SELECT * FROM audit_log ' . $whereSql . ' ORDER BY created_at DESC, id DESC';
        $sql .= ' LIMIT ' . (int) $filters['per_page'] . ' OFFSET ' . (int) $offset;
        $rows = Database::fetchAll($sql, $params);

        $pagination = [
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'total' => $total,
            'total_pages' => $totalPages,
            'start' => $total > 0 ? ($offset + 1) : 0,
            'end' => $total > 0 ? min($total, $offset + count($rows)) : 0,
        ];

        Response::view('admin/audit/index', [
            'rows' => $rows,
            'filters' => $filters,
            'pagination' => $pagination,
        ]);
    }

    public function show(): void
    {
        Auth::requireSuperAdmin();

        if (!Database::tableExists('audit_log')) {
            Response::abort(500, 'Lipseste tabela audit_log.');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!$id) {
            Response::redirect('/admin/audit');
        }

        $row = Database::fetchOne('SELECT * FROM audit_log WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$row) {
            Response::abort(404, 'Inregistrare inexistenta.');
        }

        $raw = (string) ($row['context_json'] ?? '');
        $pretty = '';
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '';
            } else {
                $pretty = $raw;
            }
        }

        $backParams = $_GET;
        unset($backParams['id']);
        $backUrl = Url::to('admin/audit');
        if (!empty($backParams)) {
            $backUrl .= '?' . http_build_query($backParams);
        }

        Response::view('admin/audit/show', [
            'row' => $row,
            'context_pretty' => $pretty,
            'back_url' => $backUrl,
        ]);
    }
}
