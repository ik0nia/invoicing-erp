<?php
    $title = 'Audit Log';
    $filters = $filters ?? [
        'action' => '',
        'entity_type' => '',
        'entity_id' => '',
        'actor_user_id' => '',
        'date_from' => '',
        'date_to' => '',
        'per_page' => 50,
        'page' => 1,
    ];
    $pagination = $pagination ?? [
        'page' => 1,
        'per_page' => (int) ($filters['per_page'] ?? 50),
        'total' => 0,
        'total_pages' => 1,
        'start' => 0,
        'end' => 0,
    ];
    $filterParams = [
        'action' => $filters['action'] ?? '',
        'entity_type' => $filters['entity_type'] ?? '',
        'entity_id' => $filters['entity_id'] ?? '',
        'actor_user_id' => $filters['actor_user_id'] ?? '',
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
    ];
    $filterParams = array_filter($filterParams, static function ($value) {
        return $value !== '' && $value !== null;
    });
    $paginationParams = $filterParams;
    $paginationParams['per_page'] = (int) ($pagination['per_page'] ?? 50);
    $viewQuery = $paginationParams;
    if (!empty($filters['page'])) {
        $viewQuery['page'] = (int) $filters['page'];
    }
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Audit Log</h1>
        <p class="mt-1 text-sm text-slate-500">Actiuni critice inregistrate in sistem.</p>
    </div>
</div>

<form method="GET" action="<?= App\Support\Url::to('admin/audit') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-action">Actiune</label>
            <input
                id="filter-action"
                name="action"
                type="text"
                value="<?= htmlspecialchars((string) ($filters['action'] ?? '')) ?>"
                placeholder="invoice.import_xml"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-entity">Entity type</label>
            <input
                id="filter-entity"
                name="entity_type"
                type="text"
                value="<?= htmlspecialchars((string) ($filters['entity_type'] ?? '')) ?>"
                placeholder="invoice_in"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-entity-id">Entity ID</label>
            <input
                id="filter-entity-id"
                name="entity_id"
                type="text"
                value="<?= htmlspecialchars((string) ($filters['entity_id'] ?? '')) ?>"
                placeholder="10091"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-actor">Actor user ID</label>
            <input
                id="filter-actor"
                name="actor_user_id"
                type="text"
                value="<?= htmlspecialchars((string) ($filters['actor_user_id'] ?? '')) ?>"
                placeholder="1"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-date-from">Data inceput</label>
            <input
                id="filter-date-from"
                name="date_from"
                type="date"
                value="<?= htmlspecialchars((string) ($filters['date_from'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-date-to">Data final</label>
            <input
                id="filter-date-to"
                name="date_to"
                type="date"
                value="<?= htmlspecialchars((string) ($filters['date_to'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-per-page">Per pagina</label>
            <select
                id="filter-per-page"
                name="per_page"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
                <?php foreach ([25, 50, 100] as $option): ?>
                    <option value="<?= $option ?>" <?= (int) ($filters['per_page'] ?? 50) === $option ? 'selected' : '' ?>>
                        <?= $option ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Filtreaza
        </button>
        <a
            href="<?= App\Support\Url::to('admin/audit') ?>"
            class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Reseteaza
        </a>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="mt-6 rounded border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Nu exista inregistrari in audit.
    </div>
<?php else: ?>
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Actor</th>
                    <th class="px-3 py-2">Actiune</th>
                    <th class="px-3 py-2">Entity</th>
                    <th class="px-3 py-2">ID</th>
                    <th class="px-3 py-2">IP</th>
                    <th class="px-3 py-2">User agent</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                        $actorId = $row['actor_user_id'] ?? null;
                        $actorRole = trim((string) ($row['actor_role'] ?? ''));
                        $actorLabel = $actorId !== null ? ('#' . (int) $actorId) : '—';
                        if ($actorRole !== '') {
                            $actorLabel .= ' (' . $actorRole . ')';
                        }
                        $userAgent = trim((string) ($row['user_agent'] ?? ''));
                        $userAgentShort = $userAgent;
                        if (strlen($userAgentShort) > 48) {
                            $userAgentShort = substr($userAgentShort, 0, 48) . '...';
                        }
                        $viewUrl = App\Support\Url::to('admin/audit/view?id=' . (int) $row['id']);
                        if (!empty($viewQuery)) {
                            $viewUrl .= '&' . http_build_query($viewQuery);
                        }
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars($actorLabel) ?></td>
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($row['action'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($row['entity_type'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($row['entity_id'] ?? '—')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($row['ip'] ?? '—')) ?></td>
                        <td class="px-3 py-2 text-slate-600" title="<?= htmlspecialchars($userAgent) ?>">
                            <?= htmlspecialchars($userAgentShort !== '' ? $userAgentShort : '—') ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a
                                href="<?= htmlspecialchars($viewUrl) ?>"
                                class="text-xs font-semibold text-blue-700 hover:text-blue-800"
                            >
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pagination['total'] ?? 0) > 0): ?>
        <?php
            $page = (int) ($pagination['page'] ?? 1);
            $totalPages = (int) ($pagination['total_pages'] ?? 1);
            $perPage = (int) ($pagination['per_page'] ?? 50);
            $baseParams = $paginationParams ?? [];
            $baseParams['per_page'] = $perPage;
            $buildPageUrl = static function (int $targetPage) use ($baseParams): string {
                $params = $baseParams;
                $params['page'] = $targetPage;
                return App\Support\Url::to('admin/audit') . '?' . http_build_query($params);
            };
            $pages = [];
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            if ($start > 1) {
                $pages[] = 1;
                if ($start > 2) {
                    $pages[] = '...';
                }
            }
            for ($i = $start; $i <= $end; $i++) {
                $pages[] = $i;
            }
            if ($end < $totalPages) {
                if ($end < $totalPages - 1) {
                    $pages[] = '...';
                }
                $pages[] = $totalPages;
            }
        ?>
        <div class="mt-4 flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center">
            <div>
                Afisezi <?= (int) ($pagination['start'] ?? 0) ?>-<?= (int) ($pagination['end'] ?? 0) ?>
                din <?= (int) ($pagination['total'] ?? 0) ?> inregistrari
            </div>
            <div class="ml-auto inline-flex flex-wrap items-center gap-1">
                <a
                    href="<?= htmlspecialchars($buildPageUrl(max(1, $page - 1))) ?>"
                    class="rounded border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50 <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
                >
                    &laquo; Inapoi
                </a>
                <?php foreach ($pages as $item): ?>
                    <?php if ($item === '...'): ?>
                        <span class="px-2 text-xs text-slate-400">...</span>
                    <?php else: ?>
                        <a
                            href="<?= htmlspecialchars($buildPageUrl((int) $item)) ?>"
                            class="rounded border px-2 py-1 text-xs font-semibold <?= (int) $item === $page ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"
                        >
                            <?= (int) $item ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a
                    href="<?= htmlspecialchars($buildPageUrl(min($totalPages, $page + 1))) ?>"
                    class="rounded border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50 <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>"
                >
                    Inainte &raquo;
                </a>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
