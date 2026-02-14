<?php
    $title = 'Portal Links';
    $rows = $rows ?? [];
    $filters = $filters ?? [
        'status' => '',
        'owner_type' => '',
        'owner_cui' => '',
        'page' => 1,
        'per_page' => 50,
    ];
    $pagination = $pagination ?? [
        'page' => 1,
        'per_page' => (int) ($filters['per_page'] ?? 50),
        'total' => 0,
        'total_pages' => 1,
        'start' => 0,
        'end' => 0,
    ];
    $newLink = $newLink ?? null;
    $userSuppliers = $userSuppliers ?? [];

    $filterParams = [
        'status' => $filters['status'] ?? '',
        'owner_type' => $filters['owner_type'] ?? '',
        'owner_cui' => $filters['owner_cui'] ?? '',
    ];
    $filterParams = array_filter($filterParams, static fn ($value) => $value !== '' && $value !== null);
    $paginationParams = $filterParams;
    $paginationParams['per_page'] = (int) ($pagination['per_page'] ?? 50);
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Portal Links</h1>
        <p class="mt-1 text-sm text-slate-500">Linkuri publice pentru acces documente.</p>
    </div>
</div>

<?php if (!empty($newLink)): ?>
    <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        Link nou creat (afisat o singura data):
        <div class="mt-2 break-all rounded border border-emerald-200 bg-white px-3 py-2 text-xs text-emerald-900">
            <?= htmlspecialchars((string) ($newLink['url'] ?? '')) ?>
        </div>
    </div>
<?php endif; ?>

<form method="POST" action="<?= App\Support\Url::to('admin/portal-links/create') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <?= App\Support\Csrf::input() ?>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="owner-type">Owner tip</label>
            <select id="owner-type" name="owner_type" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="supplier">Furnizor</option>
                <option value="client">Client</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="owner-cui">Owner CUI</label>
            <?php if (!empty($userSuppliers)): ?>
                <select id="owner-cui" name="owner_cui" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Selecteaza</option>
                    <?php foreach ($userSuppliers as $supplier): ?>
                        <option value="<?= htmlspecialchars((string) $supplier) ?>"><?= htmlspecialchars((string) $supplier) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input
                    id="owner-cui"
                    name="owner_cui"
                    type="text"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    placeholder="CUI"
                >
            <?php endif; ?>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="relation-supplier">Relatie furnizor CUI</label>
            <input
                id="relation-supplier"
                name="relation_supplier_cui"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="Optional"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="relation-client">Relatie client CUI</label>
            <input
                id="relation-client"
                name="relation_client_cui"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="Optional"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="expires-at">Expira la</label>
            <input
                id="expires-at"
                name="expires_at"
                type="date"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-4 text-sm text-slate-700">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="can_view" class="rounded border-slate-300" checked>
            Poate vedea
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="can_upload_signed" class="rounded border-slate-300">
            Upload semnat
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="can_upload_custom" class="rounded border-slate-300">
            Upload custom
        </label>
    </div>

    <div class="mt-4">
        <button class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Creeaza link
        </button>
    </div>
</form>

<form method="GET" action="<?= App\Support\Url::to('admin/portal-links') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-status">Status</label>
            <select id="filter-status" name="status" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Toate</option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="disabled" <?= ($filters['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Dezactivate</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-owner-type">Owner tip</label>
            <select id="filter-owner-type" name="owner_type" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Toate</option>
                <option value="supplier" <?= ($filters['owner_type'] ?? '') === 'supplier' ? 'selected' : '' ?>>Furnizor</option>
                <option value="client" <?= ($filters['owner_type'] ?? '') === 'client' ? 'selected' : '' ?>>Client</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-owner-cui">Owner CUI</label>
            <input
                id="filter-owner-cui"
                name="owner_cui"
                type="text"
                value="<?= htmlspecialchars((string) ($filters['owner_cui'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-per-page">Per pagina</label>
            <select id="filter-per-page" name="per_page" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
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
            href="<?= App\Support\Url::to('admin/portal-links') ?>"
            class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Reseteaza
        </a>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="mt-6 rounded border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Nu exista linkuri portal.
    </div>
<?php else: ?>
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Owner</th>
                    <th class="px-3 py-2">Relatie</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Expira</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-700">
                            <?= htmlspecialchars((string) ($row['owner_type'] ?? '')) ?>
                            <?= htmlspecialchars((string) ($row['owner_cui'] ?? '')) ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?php if (!empty($row['relation_supplier_cui']) || !empty($row['relation_client_cui'])): ?>
                                <?= htmlspecialchars((string) ($row['relation_supplier_cui'] ?? '')) ?>
                                /
                                <?= htmlspecialchars((string) ($row['relation_client_cui'] ?? '')) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($row['expires_at'] ?? '—')) ?></td>
                        <td class="px-3 py-2 text-right">
                            <?php if (($row['status'] ?? '') === 'active'): ?>
                                <form method="POST" action="<?= App\Support\Url::to('admin/portal-links/disable') ?>">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="text-xs font-semibold text-rose-600 hover:text-rose-700">Dezactiveaza</button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Dezactivat</span>
                            <?php endif; ?>
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
                return App\Support\Url::to('admin/portal-links') . '?' . http_build_query($params);
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
                din <?= (int) ($pagination['total'] ?? 0) ?> linkuri
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
