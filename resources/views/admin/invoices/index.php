<?php
    $title = 'Facturi intrare';
    $isPlatform = $isPlatform ?? false;
    $filters = $filters ?? [
        'query' => '',
        'supplier_cui' => '',
        'client_cui' => '',
        'client_status' => '',
        'supplier_status' => '',
        'per_page' => 25,
        'page' => 1,
    ];
    $pagination = $pagination ?? [
        'page' => 1,
        'per_page' => (int) ($filters['per_page'] ?? 25),
        'total' => 0,
        'total_pages' => 1,
        'start' => 0,
        'end' => 0,
    ];
    $supplierOptions = $supplierOptions ?? [];
    $clientOptions = $clientOptions ?? [];
    $hasEmptyClients = $hasEmptyClients ?? false;
    $clientStatusOptions = $clientStatusOptions ?? [];
    $supplierStatusOptions = $supplierStatusOptions ?? [];

    $filterParams = [
        'q' => $filters['query'] ?? '',
        'supplier_cui' => $filters['supplier_cui'] ?? '',
        'client_cui' => $filters['client_cui'] ?? '',
        'client_status' => $filters['client_status'] ?? '',
        'supplier_status' => $filters['supplier_status'] ?? '',
    ];
    $filterParams = array_filter($filterParams, static fn ($value) => $value !== '' && $value !== null);
    $exportUrl = App\Support\Url::to('admin/facturi/export');
    if (!empty($filterParams)) {
        $exportUrl .= '?' . http_build_query($filterParams);
    }
    $paginationParams = $filterParams;
    $paginationParams['per_page'] = (int) ($pagination['per_page'] ?? 25);

    $hasFilters = ($filters['query'] ?? '') !== ''
        || ($filters['supplier_cui'] ?? '') !== ''
        || ($filters['client_cui'] ?? '') !== ''
        || ($filters['client_status'] ?? '') !== ''
        || ($filters['supplier_status'] ?? '') !== ''
        || (int) ($filters['per_page'] ?? 25) !== 25;
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Facturi intrare</h1>
        <p class="mt-1 text-sm text-slate-500">Facturi importate din XML sau adaugate manual.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a
            href="<?= htmlspecialchars($exportUrl) ?>"
            class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow hover:bg-slate-50"
        >
            Export CSV
        </a>
        <a
            href="<?= App\Support\Url::to('admin/facturi/adauga') ?>"
            class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Adauga factura
        </a>
        <a
            href="<?= App\Support\Url::to('admin/facturi/import') ?>"
            class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow hover:bg-slate-50"
        >
            Importa XML
        </a>
    </div>
</div>

<form method="GET" action="<?= App\Support\Url::to('admin/facturi') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
        <div class="xl:col-span-2">
            <label class="block text-sm font-medium text-slate-700" for="invoice-search">Cauta factura</label>
            <input
                id="invoice-search"
                name="q"
                type="text"
                value="<?= htmlspecialchars((string) ($filters['query'] ?? '')) ?>"
                placeholder="Factura, client, furnizor, CUI, serie, numar"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-supplier">Furnizor</label>
            <select
                id="filter-supplier"
                name="supplier_cui"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
                <option value="">Toti furnizorii</option>
                <?php foreach ($supplierOptions as $option): ?>
                    <option
                        value="<?= htmlspecialchars($option['cui']) ?>"
                        <?= (string) ($filters['supplier_cui'] ?? '') === (string) $option['cui'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($option['name']) ?> (<?= htmlspecialchars($option['cui']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-client">Client final</label>
            <select
                id="filter-client"
                name="client_cui"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
                <option value="">Toti clientii</option>
                <?php if ($hasEmptyClients): ?>
                    <option value="none" <?= ($filters['client_cui'] ?? '') === 'none' ? 'selected' : '' ?>>
                        Fara client
                    </option>
                <?php endif; ?>
                <?php foreach ($clientOptions as $option): ?>
                    <option
                        value="<?= htmlspecialchars($option['cui']) ?>"
                        <?= (string) ($filters['client_cui'] ?? '') === (string) $option['cui'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($option['name']) ?> (<?= htmlspecialchars($option['cui']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-client-status">Incasare client</label>
            <select
                id="filter-client-status"
                name="client_status"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
                <option value="">Toate</option>
                <?php foreach ($clientStatusOptions as $option): ?>
                    <option
                        value="<?= htmlspecialchars($option) ?>"
                        <?= (string) ($filters['client_status'] ?? '') === (string) $option ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($option) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-supplier-status">Plata furnizor</label>
            <select
                id="filter-supplier-status"
                name="supplier_status"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
                <option value="">Toate</option>
                <?php foreach ($supplierStatusOptions as $option): ?>
                    <option
                        value="<?= htmlspecialchars($option) ?>"
                        <?= (string) ($filters['supplier_status'] ?? '') === (string) $option ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($option) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-per-page">Per pagina</label>
            <select
                id="filter-per-page"
                name="per_page"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
                <?php foreach ([25, 50, 250, 500] as $option): ?>
                    <option value="<?= $option ?>" <?= (int) ($filters['per_page'] ?? 25) === $option ? 'selected' : '' ?>>
                        <?= $option ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button
            type="submit"
            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Aplica filtre
        </button>
        <?php if ($hasFilters): ?>
            <a
                href="<?= App\Support\Url::to('admin/facturi') ?>"
                class="rounded border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
            >
                Anuleaza filtrele
            </a>
        <?php endif; ?>
    </div>
</form>

<div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm md:table">
        <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3">Furnizor</th>
                <th class="px-4 py-3">Factura furnizor</th>
                <th class="px-4 py-3">Data factura furnizor</th>
                <th class="px-4 py-3">Total factura furnizor</th>
                <th class="px-4 py-3">Client final</th>
                <th class="px-4 py-3">Factura client</th>
                <th class="px-4 py-3">Data factura client</th>
                <th class="px-4 py-3">Total factura client</th>
                <th class="px-4 py-3">Incasare client</th>
                <th class="px-4 py-3">Plata furnizor</th>
            </tr>
        </thead>
        <tbody id="invoice-table-body">
            <?php include BASE_PATH . '/resources/views/admin/invoices/rows.php'; ?>
        </tbody>
    </table>
</div>

<?php if (($pagination['total'] ?? 0) > 0): ?>
    <?php
        $page = (int) ($pagination['page'] ?? 1);
        $totalPages = (int) ($pagination['total_pages'] ?? 1);
        $perPage = (int) ($pagination['per_page'] ?? 25);
        $baseParams = $paginationParams ?? [];
        $baseParams['per_page'] = $perPage;
        $buildPageUrl = static function (int $targetPage) use ($baseParams): string {
            $params = $baseParams;
            $params['page'] = $targetPage;
            return App\Support\Url::to('admin/facturi') . '?' . http_build_query($params);
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
    <div class="mt-4 flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
        <div>
            Afisezi <?= (int) ($pagination['start'] ?? 0) ?>-<?= (int) ($pagination['end'] ?? 0) ?>
            din <?= (int) ($pagination['total'] ?? 0) ?> facturi
        </div>
        <div class="flex flex-wrap items-center gap-1">
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

<style>
    @media (max-width: 768px) {
        table thead {
            display: none;
        }
        table tbody tr {
            display: block;
            padding: 0.75rem 0.75rem;
        }
        table tbody td {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.4rem 0;
        }
        table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #334155;
        }
    }
</style>

<script>
    (function () {
        const wireRowClicks = () => {
            const rows = Array.from(document.querySelectorAll('.invoice-row'));
            rows.forEach((row) => {
                row.addEventListener('click', (event) => {
                    if (event.target.closest('a, button, input, label')) {
                        return;
                    }
                    const url = row.getAttribute('data-url');
                    if (url) {
                        window.location.href = url;
                    }
                });
            });
        };

        wireRowClicks();
    })();
</script>
