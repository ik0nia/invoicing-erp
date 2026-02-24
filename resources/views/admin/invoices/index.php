<?php
    $title = 'Facturi intrare';
    $isPlatform = $isPlatform ?? false;
    $filters = $filters ?? [
        'query' => '',
        'supplier_cui' => '',
        'client_cui' => '',
        'client_status' => [],
        'supplier_status' => [],
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
    $supplierFilterLabel = $supplierFilterLabel ?? '';
    $clientFilterLabel = $clientFilterLabel ?? '';
    $hasEmptyClients = $hasEmptyClients ?? false;
    $clientStatusOptions = $clientStatusOptions ?? [];
    $supplierStatusOptions = $supplierStatusOptions ?? [];
    $canViewPaymentDetails = $canViewPaymentDetails ?? false;
    $normalizeStatusFilter = static function ($value): array {
        if (!is_array($value)) {
            $value = $value !== '' && $value !== null ? [(string) $value] : [];
        }
        $value = array_values(array_filter($value, static fn ($item) => $item !== '' && $item !== null));
        return $value;
    };
    $clientStatusFilter = $normalizeStatusFilter($filters['client_status'] ?? []);
    $supplierStatusFilter = $normalizeStatusFilter($filters['supplier_status'] ?? []);

    $filterParams = [
        'q' => $filters['query'] ?? '',
        'supplier_cui' => $filters['supplier_cui'] ?? '',
        'client_cui' => $filters['client_cui'] ?? '',
        'client_status' => $clientStatusFilter,
        'supplier_status' => $supplierStatusFilter,
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
    ];
    $filterParams = array_filter($filterParams, static function ($value) {
        if (is_array($value)) {
            return count($value) > 0;
        }
        return $value !== '' && $value !== null;
    });
    $exportUrl = App\Support\Url::to('admin/facturi/export');
    $printUrl = App\Support\Url::to('admin/facturi/print-situatie');
    if (!empty($filterParams)) {
        $query = http_build_query($filterParams);
        $exportUrl .= '?' . $query;
        $printUrl .= '?' . $query;
    }
    $paginationParams = $filterParams;
    $paginationParams['per_page'] = (int) ($pagination['per_page'] ?? 25);

?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Facturi intrare</h1>
        <p class="mt-1 text-sm text-slate-500">Facturi importate din XML sau adaugate manual.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a
            href="<?= App\Support\Url::to('admin/facturi/adauga') ?>"
            class="inline-flex items-center gap-2 rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow hover:bg-slate-50"
        >
            <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 5h10l5 5v9a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/>
                <path d="M7 16l6-6"/>
                <path d="M7 16h3v3"/>
            </svg>
            Adauga factura
        </a>
        <a
            href="<?= App\Support\Url::to('admin/facturi/import') ?>"
            class="inline-flex items-center gap-2 rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            <svg class="h-4 w-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M13 2L3 14h7l-1 8 12-16h-8l1-4z"/>
            </svg>
            Importa XML
        </a>
    </div>
</div>

<form method="GET" action="<?= App\Support\Url::to('admin/facturi') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div>
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
    <div class="mt-4 flex flex-wrap items-end gap-4">
        <div
            class="relative min-w-[200px] w-60"
            data-ajax-select
            data-lookup-url="<?= App\Support\Url::to('admin/facturi/lookup-suppliers') ?>"
        >
            <label class="block text-sm font-medium text-slate-700" for="filter-supplier-input">Furnizor</label>
            <div class="relative mt-1">
                <input
                    id="filter-supplier-input"
                    type="text"
                    value="<?= htmlspecialchars((string) $supplierFilterLabel) ?>"
                    placeholder="Toti furnizorii"
                    class="block w-full rounded border border-slate-300 px-3 py-2 pr-9 text-sm"
                    autocomplete="off"
                    data-ajax-input
                >
                <button
                    type="button"
                    class="absolute right-2 top-1/2 hidden -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-600"
                    aria-label="Sterge furnizor"
                    data-ajax-clear
                >
                    &#10005;
                </button>
            </div>
            <input type="hidden" name="supplier_cui" value="<?= htmlspecialchars((string) ($filters['supplier_cui'] ?? '')) ?>" data-ajax-value>
            <div class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-slate-300 bg-white p-1 shadow-xl ring-1 ring-slate-200 divide-y divide-slate-100" data-ajax-list></div>
        </div>
        <div
            class="relative min-w-[200px] w-60"
            data-ajax-select
            data-lookup-url="<?= App\Support\Url::to('admin/facturi/lookup-clients') ?>"
            data-allow-empty="<?= $hasEmptyClients ? '1' : '0' ?>"
            data-empty-label="Fara client"
        >
            <label class="block text-sm font-medium text-slate-700" for="filter-client-input">Client final</label>
            <div class="relative mt-1">
                <input
                    id="filter-client-input"
                    type="text"
                    value="<?= htmlspecialchars((string) $clientFilterLabel) ?>"
                    placeholder="Toti clientii"
                    class="block w-full rounded border border-slate-300 px-3 py-2 pr-9 text-sm"
                    autocomplete="off"
                    data-ajax-input
                >
                <button
                    type="button"
                    class="absolute right-2 top-1/2 hidden -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-600"
                    aria-label="Sterge client"
                    data-ajax-clear
                >
                    &#10005;
                </button>
            </div>
            <input type="hidden" name="client_cui" value="<?= htmlspecialchars((string) ($filters['client_cui'] ?? '')) ?>" data-ajax-value>
            <div class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-slate-300 bg-white p-1 shadow-xl ring-1 ring-slate-200 divide-y divide-slate-100" data-ajax-list></div>
        </div>
        <div class="min-w-[170px]">
            <label class="block text-sm font-medium text-slate-700" for="filter-date-from">Data inceput</label>
            <div class="relative mt-1">
                <input
                    id="filter-date-from"
                    name="date_from"
                    type="date"
                    value="<?= htmlspecialchars((string) ($filters['date_from'] ?? '')) ?>"
                    class="block w-full rounded border border-slate-300 px-3 py-2 pr-9 text-sm"
                >
                <button
                    type="button"
                    class="absolute right-2 top-1/2 hidden -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-600"
                    aria-label="Sterge data inceput"
                    data-date-clear="from"
                >
                    &#10005;
                </button>
            </div>
        </div>
        <div class="min-w-[170px]">
            <label class="block text-sm font-medium text-slate-700" for="filter-date-to">Data final</label>
            <div class="relative mt-1">
                <input
                    id="filter-date-to"
                    name="date_to"
                    type="date"
                    value="<?= htmlspecialchars((string) ($filters['date_to'] ?? '')) ?>"
                    class="block w-full rounded border border-slate-300 px-3 py-2 pr-9 text-sm"
                >
                <button
                    type="button"
                    class="absolute right-2 top-1/2 hidden -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-600"
                    aria-label="Sterge data final"
                    data-date-clear="to"
                >
                    &#10005;
                </button>
            </div>
        </div>
        <div class="relative min-w-[220px]" data-status-dropdown>
            <span class="block text-sm font-medium text-slate-700">Incasare client</span>
            <button
                type="button"
                class="mt-1 flex w-full items-center justify-between rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm hover:border-slate-400"
                data-dropdown-toggle
            >
                <span class="truncate" data-dropdown-label>Toate</span>
                <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M5 7l5 6 5-6" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
            <div
                class="absolute z-20 mt-2 hidden w-full rounded-lg border border-slate-200 bg-white p-3 shadow-xl"
                data-dropdown-panel
            >
                <div class="space-y-1">
                    <?php foreach ($clientStatusOptions as $option): ?>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="client_status[]"
                                value="<?= htmlspecialchars($option) ?>"
                                data-label="<?= htmlspecialchars($option) ?>"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600"
                                data-status-filter="client"
                                <?= in_array($option, $clientStatusFilter, true) ? 'checked' : '' ?>
                            >
                            <span><?= htmlspecialchars($option) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="mt-2 text-[11px] text-slate-400">Nicio selectie = toate</p>
            </div>
        </div>
        <div class="relative min-w-[220px]" data-status-dropdown>
            <span class="block text-sm font-medium text-slate-700">Plata furnizor</span>
            <button
                type="button"
                class="mt-1 flex w-full items-center justify-between rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm hover:border-slate-400"
                data-dropdown-toggle
            >
                <span class="truncate" data-dropdown-label>Toate</span>
                <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M5 7l5 6 5-6" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
            <div
                class="absolute z-20 mt-2 hidden w-full rounded-lg border border-slate-200 bg-white p-3 shadow-xl"
                data-dropdown-panel
            >
                <div class="space-y-1">
                    <?php foreach ($supplierStatusOptions as $option): ?>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="supplier_status[]"
                                value="<?= htmlspecialchars($option) ?>"
                                data-label="<?= htmlspecialchars($option) ?>"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600"
                                data-status-filter="supplier"
                                <?= in_array($option, $supplierStatusFilter, true) ? 'checked' : '' ?>
                            >
                            <span><?= htmlspecialchars($option) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="mt-2 text-[11px] text-slate-400">Nicio selectie = toate</p>
            </div>
        </div>
        <div class="ml-auto flex flex-wrap items-center gap-2">
            <div class="flex flex-col items-start">
                <label class="text-xs font-semibold text-slate-500" for="filter-per-page">Per pagina</label>
                <select
                    id="filter-per-page"
                    name="per_page"
                    class="mt-1 block w-28 rounded border border-slate-300 px-3 py-2 text-sm"
                >
                    <?php foreach ([25, 50, 250, 500] as $option): ?>
                        <option value="<?= $option ?>" <?= (int) ($filters['per_page'] ?? 25) === $option ? 'selected' : '' ?>>
                            <?= $option ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="mt-3 flex justify-end gap-2">
        <a
            href="<?= htmlspecialchars($printUrl) ?>"
            target="_blank"
            rel="noopener"
            class="rounded border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50"
        >
            Print situatie
        </a>
        <a
            href="<?= htmlspecialchars($exportUrl) ?>"
            class="rounded border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50"
        >
            Export CSV
        </a>
    </div>
</form>

<div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm md:table">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-600">
            <tr>
                <th class="px-2 py-2">Creat</th>
                <th class="px-2 py-2">Furnizor</th>
                <th class="px-2 py-2 whitespace-normal">
                    <span class="inline-block leading-tight">Factura<br>furnizor</span>
                </th>
                <th class="px-2 py-2 whitespace-normal">
                    <span class="inline-block leading-tight">Data factura<br>furnizor</span>
                </th>
                <th class="px-2 py-2 whitespace-normal">
                    <span class="inline-block leading-tight">Total factura<br>furnizor</span>
                </th>
                <th class="px-2 py-2 whitespace-normal">
                    <span class="inline-block leading-tight">Client<br>final</span>
                </th>
                <th class="px-2 py-2 whitespace-normal">
                    <span class="inline-block leading-tight">Factura<br>client</span>
                </th>
                <th class="px-2 py-2 whitespace-normal">
                    <span class="inline-block leading-tight">Data factura<br>client</span>
                </th>
                <th class="px-2 py-2 whitespace-normal">
                    <span class="inline-block leading-tight">Total factura<br>client</span>
                </th>
                <?php if (!empty($canViewPaymentDetails)): ?>
                    <th class="px-2 py-2 whitespace-normal">
                        <span class="inline-block leading-tight">Incasare<br>client</span>
                    </th>
                    <th class="px-2 py-2 whitespace-normal">
                        <span class="inline-block leading-tight">Plata<br>furnizor</span>
                    </th>
                <?php endif; ?>
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
    <div class="mt-4 flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center">
        <div>
            Afisezi <?= (int) ($pagination['start'] ?? 0) ?>-<?= (int) ($pagination['end'] ?? 0) ?>
            din <?= (int) ($pagination['total'] ?? 0) ?> facturi
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

<style>
    @media (min-width: 769px) {
        #invoice-table-body td {
            padding-top: 0.65rem !important;
            padding-bottom: 0.65rem !important;
        }
    }

    @media (max-width: 768px) {
        table thead {
            display: none;
        }
        table tbody tr {
            display: block;
            padding: 0.85rem 0.85rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            margin-bottom: 0.75rem;
        }
        table tbody td {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.2rem;
            padding: 0.45rem 0;
        }
        table tbody td::before {
            content: attr(data-label);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #64748b;
            text-transform: uppercase;
        }
        table tbody td[data-label="Furnizor"] {
            font-weight: 700;
            font-size: 0.95rem;
            color: #0f172a;
        }
        table tbody td[data-label="Furnizor"]::before {
            font-size: 0.65rem;
            color: #94a3b8;
        }
    }
</style>

<script>
    (function () {
        const escapeHtml = (value) => {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const initAjaxSelects = () => {
            const selects = Array.from(document.querySelectorAll('[data-ajax-select]'));
            if (!selects.length) {
                return;
            }

            selects.forEach((root) => {
                const input = root.querySelector('[data-ajax-input]');
                const hidden = root.querySelector('[data-ajax-value]');
                const list = root.querySelector('[data-ajax-list]');
                const clearButton = root.querySelector('[data-ajax-clear]');
                const url = root.getAttribute('data-lookup-url');
                const allowEmpty = root.getAttribute('data-allow-empty') === '1';
                const emptyLabel = root.getAttribute('data-empty-label') || 'Fara client';
                let timer = null;
                let requestId = 0;

                if (!input || !hidden || !list || !url) {
                    return;
                }

                const updateClear = () => {
                    if (!clearButton) {
                        return;
                    }
                    if (input.value.trim() !== '' || hidden.value.trim() !== '') {
                        clearButton.classList.remove('hidden');
                    } else {
                        clearButton.classList.add('hidden');
                    }
                };

                const clearList = () => {
                    list.innerHTML = '';
                    list.classList.add('hidden');
                };

                const renderItems = (items, query, allowEmptyOverride) => {
                    const results = [];
                    const normalized = (query || '').toLowerCase();
                    const shouldAllowEmpty = typeof allowEmptyOverride === 'boolean' ? allowEmptyOverride : allowEmpty;

                    if (shouldAllowEmpty && (normalized === '' || emptyLabel.toLowerCase().includes(normalized))) {
                        results.push({
                            cui: 'none',
                            name: emptyLabel,
                        });
                    }

                    items.forEach((item) => results.push(item));

                    if (!results.length) {
                        list.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">Nu exista rezultate.</div>';
                        list.classList.remove('hidden');
                        return;
                    }

                    list.innerHTML = results
                        .map((item) => {
                            const name = escapeHtml(item.name || item.cui || '');
                            const cui = escapeHtml(item.cui || '');
                            const label = item.cui === 'none' ? name : `${name} - ${cui}`;
                            return `
                                <button
                                    type="button"
                                    class="block w-full rounded px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700"
                                    data-ajax-item
                                    data-value="${cui}"
                                    data-label="${escapeHtml(label)}"
                                >
                                    <div class="font-medium text-slate-900">${name}</div>
                                    ${item.cui === 'none' ? '' : `<div class="text-xs text-slate-500">${cui}</div>`}
                                </button>
                            `;
                        })
                        .join('');

                    list.classList.remove('hidden');
                };

                const appendFilterParams = (fetchUrl) => {
                    const form = root.closest('form');
                    if (!form) {
                        return;
                    }
                    const searchValue = form.querySelector('#invoice-search')?.value ?? '';
                    if (searchValue) {
                        fetchUrl.searchParams.set('q', searchValue);
                    }
                    const supplierValue = form.querySelector('input[name="supplier_cui"]')?.value ?? '';
                    if (supplierValue) {
                        fetchUrl.searchParams.set('supplier_cui', supplierValue);
                    }
                    const clientValue = form.querySelector('input[name="client_cui"]')?.value ?? '';
                    if (clientValue) {
                        fetchUrl.searchParams.set('client_cui', clientValue);
                    }
                    const clientStatusValues = Array.from(
                        form.querySelectorAll('input[name="client_status[]"]:checked')
                    ).map((input) => input.value);
                    clientStatusValues.forEach((value) => {
                        fetchUrl.searchParams.append('client_status[]', value);
                    });
                    const supplierStatusValues = Array.from(
                        form.querySelectorAll('input[name="supplier_status[]"]:checked')
                    ).map((input) => input.value);
                    supplierStatusValues.forEach((value) => {
                        fetchUrl.searchParams.append('supplier_status[]', value);
                    });
                    const dateFrom = form.querySelector('#filter-date-from')?.value ?? '';
                    if (dateFrom) {
                        fetchUrl.searchParams.set('date_from', dateFrom);
                    }
                    const dateTo = form.querySelector('#filter-date-to')?.value ?? '';
                    if (dateTo) {
                        fetchUrl.searchParams.set('date_to', dateTo);
                    }
                };

                const fetchItems = (query) => {
                    const current = ++requestId;
                    list.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">Se incarca...</div>';
                    list.classList.remove('hidden');

                    const fetchUrl = new URL(url, window.location.origin);
                    fetchUrl.searchParams.set('term', query || '');
                    appendFilterParams(fetchUrl);

                    fetch(fetchUrl.toString(), { credentials: 'same-origin' })
                        .then((response) => response.json())
                        .then((payload) => {
                            if (current !== requestId) {
                                return;
                            }
                            renderItems(
                                Array.isArray(payload.items) ? payload.items : [],
                                query,
                                typeof payload.allow_empty === 'boolean' ? payload.allow_empty : undefined
                            );
                        })
                        .catch(() => {
                            if (current !== requestId) {
                                return;
                            }
                            list.innerHTML = '<div class="px-3 py-2 text-xs text-rose-600">Eroare la incarcare.</div>';
                            list.classList.remove('hidden');
                        });
                };

                const scheduleFetch = () => {
                    if (timer) {
                        clearTimeout(timer);
                    }
                    timer = setTimeout(() => {
                        fetchItems(input.value.trim());
                    }, 200);
                };

                input.addEventListener('input', () => {
                    hidden.value = '';
                    scheduleFetch();
                    updateClear();
                });

                input.addEventListener('focus', () => {
                    fetchItems(input.value.trim());
                });

                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        clearList();
                    }
                });

                input.addEventListener('blur', () => {
                    setTimeout(() => {
                        clearList();
                        if (!hidden.value) {
                            const digits = input.value.replace(/\D+/g, '');
                            if (digits) {
                                hidden.value = digits;
                            }
                        }
                        updateClear();
                    }, 150);
                });

                list.addEventListener('click', (event) => {
                    const item = event.target.closest('[data-ajax-item]');
                    if (!item) {
                        return;
                    }
                    hidden.value = item.getAttribute('data-value') || '';
                    input.value = item.getAttribute('data-label') || '';
                    clearList();
                    updateClear();
                    submitWithDebounce(0);
                });

                document.addEventListener('click', (event) => {
                    if (!root.contains(event.target)) {
                        clearList();
                    }
                });

                if (clearButton) {
                    clearButton.addEventListener('click', () => {
                        input.value = '';
                        hidden.value = '';
                        updateClear();
                        clearList();
                        submitWithDebounce(0);
                    });
                }

                updateClear();
            });
        };

        const initStatusDropdowns = () => {
            const dropdowns = Array.from(document.querySelectorAll('[data-status-dropdown]'));
            if (!dropdowns.length) {
                return;
            }

            dropdowns.forEach((root) => {
                const toggle = root.querySelector('[data-dropdown-toggle]');
                const panel = root.querySelector('[data-dropdown-panel]');
                const label = root.querySelector('[data-dropdown-label]');
                const checkboxes = Array.from(root.querySelectorAll('input[type="checkbox"]'));

                if (!toggle || !panel || !label) {
                    return;
                }

                const updateLabel = () => {
                    const selected = checkboxes
                        .filter((input) => input.checked)
                        .map((input) => input.getAttribute('data-label') || input.value);

                    if (!selected.length) {
                        label.textContent = 'Toate';
                        return;
                    }
                    if (selected.length === 1) {
                        label.textContent = selected[0];
                        return;
                    }
                    label.textContent = `${selected.length} selectate`;
                };

                updateLabel();

                toggle.addEventListener('click', (event) => {
                    event.preventDefault();
                    panel.classList.toggle('hidden');
                });

                checkboxes.forEach((input) => {
                    input.addEventListener('change', updateLabel);
                });

                document.addEventListener('click', (event) => {
                    if (!root.contains(event.target)) {
                        panel.classList.add('hidden');
                    }
                });
            });
        };

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

        const form = document.querySelector('form[action$="/admin/facturi"]');
        let submitTimer = null;

        const submitForm = () => {
            if (!form) {
                return;
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        };

        const submitWithDebounce = (delay = 350) => {
            if (submitTimer) {
                clearTimeout(submitTimer);
            }
            submitTimer = setTimeout(submitForm, delay);
        };

        if (form) {
            const searchInput = form.querySelector('#invoice-search');
            if (searchInput) {
                let lastValue = searchInput.value;
                searchInput.addEventListener('input', () => {
                    const value = searchInput.value;
                    if (value === lastValue) {
                        return;
                    }
                    lastValue = value;
                    submitWithDebounce(400);
                });
            }

            const selectInputs = form.querySelectorAll('#filter-per-page');
            selectInputs.forEach((select) => {
                select.addEventListener('change', () => submitForm());
            });
            const statusInputs = form.querySelectorAll('input[name="client_status[]"], input[name="supplier_status[]"]');
            statusInputs.forEach((input) => {
                input.addEventListener('change', () => submitForm());
            });

            const dateInputs = form.querySelectorAll('#filter-date-from, #filter-date-to');
            const dateClearButtons = form.querySelectorAll('[data-date-clear]');
            const isValidDateValue = (value) => {
                if (value === '') {
                    return true;
                }
                return /^\d{4}-\d{2}-\d{2}$/.test(value);
            };
            let dateTimer = null;
            const scheduleDateSubmit = () => {
                if (dateTimer) {
                    clearTimeout(dateTimer);
                }
                dateTimer = setTimeout(() => {
                    const fromValue = form.querySelector('#filter-date-from')?.value ?? '';
                    const toValue = form.querySelector('#filter-date-to')?.value ?? '';
                    if (isValidDateValue(fromValue) && isValidDateValue(toValue)) {
                        submitForm();
                    }
                }, 1200);
            };
            const updateDateClear = () => {
                const fromValue = form.querySelector('#filter-date-from')?.value ?? '';
                const toValue = form.querySelector('#filter-date-to')?.value ?? '';
                dateClearButtons.forEach((button) => {
                    const key = button.getAttribute('data-date-clear');
                    if (key === 'from') {
                        button.classList.toggle('hidden', fromValue === '');
                    }
                    if (key === 'to') {
                        button.classList.toggle('hidden', toValue === '');
                    }
                });
            };
            dateInputs.forEach((input) => {
                input.addEventListener('input', () => {
                    scheduleDateSubmit();
                    updateDateClear();
                });
                input.addEventListener('change', () => {
                    scheduleDateSubmit();
                    updateDateClear();
                });
            });
            dateClearButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const key = button.getAttribute('data-date-clear');
                    const target = form.querySelector(key === 'from' ? '#filter-date-from' : '#filter-date-to');
                    if (target) {
                        target.value = '';
                    }
                    updateDateClear();
                    submitForm();
                });
            });
            updateDateClear();
        }

        wireRowClicks();
        initAjaxSelects();
        initStatusDropdowns();
    })();
</script>
