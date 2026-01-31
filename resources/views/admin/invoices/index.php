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
    $supplierFilterLabel = $supplierFilterLabel ?? '';
    $clientFilterLabel = $clientFilterLabel ?? '';
    $hasEmptyClients = $hasEmptyClients ?? false;
    $clientStatusOptions = $clientStatusOptions ?? [];
    $supplierStatusOptions = $supplierStatusOptions ?? [];

    $filterParams = [
        'q' => $filters['query'] ?? '',
        'supplier_cui' => $filters['supplier_cui'] ?? '',
        'client_cui' => $filters['client_cui'] ?? '',
        'client_status' => $filters['client_status'] ?? '',
        'supplier_status' => $filters['supplier_status'] ?? '',
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
    ];
    $filterParams = array_filter($filterParams, static fn ($value) => $value !== '' && $value !== null);
    $exportUrl = App\Support\Url::to('admin/facturi/export');
    if (!empty($filterParams)) {
        $exportUrl .= '?' . http_build_query($filterParams);
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
        <div class="min-w-[160px]">
            <label class="block text-sm font-medium text-slate-700" for="filter-date-from">Data inceput</label>
            <input
                id="filter-date-from"
                name="date_from"
                type="date"
                value="<?= htmlspecialchars((string) ($filters['date_from'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div class="min-w-[160px]">
            <label class="block text-sm font-medium text-slate-700" for="filter-date-to">Data final</label>
            <input
                id="filter-date-to"
                name="date_to"
                type="date"
                value="<?= htmlspecialchars((string) ($filters['date_to'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div class="min-w-[180px]">
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
        <div class="min-w-[180px]">
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

                const renderItems = (items, query) => {
                    const results = [];
                    const normalized = (query || '').toLowerCase();

                    if (allowEmpty && (normalized === '' || emptyLabel.toLowerCase().includes(normalized))) {
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

                const fetchItems = (query) => {
                    const current = ++requestId;
                    list.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">Se incarca...</div>';
                    list.classList.remove('hidden');

                    const fetchUrl = new URL(url, window.location.origin);
                    fetchUrl.searchParams.set('q', query || '');

                    fetch(fetchUrl.toString(), { credentials: 'same-origin' })
                        .then((response) => response.json())
                        .then((payload) => {
                            if (current !== requestId) {
                                return;
                            }
                            renderItems(Array.isArray(payload.items) ? payload.items : [], query);
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

            const selectInputs = form.querySelectorAll('#filter-client-status, #filter-supplier-status, #filter-per-page');
            selectInputs.forEach((select) => {
                select.addEventListener('change', () => submitForm());
            });

            const dateInputs = form.querySelectorAll('#filter-date-from, #filter-date-to');
            const isValidDateValue = (value) => {
                if (value === '') {
                    return true;
                }
                return /^\d{4}-\d{2}-\d{2}$/.test(value);
            };
            dateInputs.forEach((input) => {
                input.addEventListener('input', () => {
                    const value = input.value;
                    if (isValidDateValue(value)) {
                        submitWithDebounce(300);
                    }
                });
                input.addEventListener('blur', () => {
                    if (isValidDateValue(input.value)) {
                        submitWithDebounce(0);
                    }
                });
            });
        }

        wireRowClicks();
        initAjaxSelects();
    })();
</script>
