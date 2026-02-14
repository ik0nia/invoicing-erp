<?php
    $title = 'Link-uri de inrolare';
    $rows = $rows ?? [];
    $filters = $filters ?? [
        'status' => '',
        'type' => '',
        'supplier_cui' => '',
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
        'type' => $filters['type'] ?? '',
        'supplier_cui' => $filters['supplier_cui'] ?? '',
    ];
    $filterParams = array_filter($filterParams, static fn ($value) => $value !== '' && $value !== null);
    $paginationParams = $filterParams;
    $paginationParams['per_page'] = (int) ($pagination['per_page'] ?? 50);
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Link-uri de inrolare</h1>
        <p class="mt-1 text-sm text-slate-500">Gestioneaza linkurile publice de inrolare.</p>
    </div>
</div>

<div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <div class="font-semibold">Ce este un link de inrolare?</div>
    <ul class="mt-2 list-disc space-y-1 pl-5">
        <li>Linkul de inrolare permite completarea datelor fara cont si parola.</li>
        <li>Pentru furnizor se foloseste cand se inroleaza un partener furnizor.</li>
        <li>Pentru client se foloseste cand se inroleaza un client al unui furnizor.</li>
        <li>Dupa confirmare se creeaza firma si un contract in status <strong>Ciorna</strong>.</li>
        <li>Linkul este limitat ca utilizari si poate fi dezactivat oricand.</li>
    </ul>
</div>

<?php if (!empty($newLink)): ?>
    <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        Link nou creat (afisat o singura data):
        <div class="mt-2 break-all rounded border border-emerald-200 bg-white px-3 py-2 text-xs text-emerald-900">
            <?= htmlspecialchars((string) ($newLink['url'] ?? '')) ?>
        </div>
    </div>
<?php endif; ?>

<form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/create') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <?= App\Support\Csrf::input() ?>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="link-type">Tip link</label>
            <select id="link-type" name="type" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="supplier">Furnizor</option>
                <option value="client">Client</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="supplier-cui">Furnizor (pentru client)</label>
            <?php if (!empty($userSuppliers)): ?>
                <select id="supplier-cui" name="supplier_cui" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Selecteaza furnizor</option>
                    <?php foreach ($userSuppliers as $supplier): ?>
                        <option value="<?= htmlspecialchars((string) $supplier) ?>"><?= htmlspecialchars((string) $supplier) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input
                    id="supplier-cui"
                    name="supplier_cui"
                    type="text"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    placeholder="CUI furnizor"
                >
            <?php endif; ?>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="commission">Comision (%)</label>
            <input
                id="commission"
                name="commission_percent"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="ex: 5"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="max-uses">Numar utilizari</label>
            <input
                id="max-uses"
                name="max_uses"
                type="number"
                min="1"
                value="1"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
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

    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="prefill-cui">Precompletare CUI</label>
            <input
                id="prefill-cui"
                name="prefill_cui"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="CUI companie"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="prefill-denumire">Precompletare denumire</label>
            <input
                id="prefill-denumire"
                name="prefill_denumire"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="prefill-nr">Precompletare nr. reg. comertului</label>
            <input
                id="prefill-nr"
                name="prefill_nr_reg_comertului"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="prefill-email">Precompletare email</label>
            <input
                id="prefill-email"
                name="prefill_email"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="prefill-adresa">Precompletare adresa</label>
            <input
                id="prefill-adresa"
                name="prefill_adresa"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="prefill-localitate">Precompletare localitate</label>
            <input
                id="prefill-localitate"
                name="prefill_localitate"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="prefill-judet">Precompletare judet</label>
            <input
                id="prefill-judet"
                name="prefill_judet"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="prefill-telefon">Precompletare telefon</label>
            <input
                id="prefill-telefon"
                name="prefill_telefon"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Creeaza link de inrolare
        </button>
        <button
            type="button"
            id="openapi-fetch"
            class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Precompletare OpenAPI
        </button>
    </div>
</form>

<form method="GET" action="<?= App\Support\Url::to('admin/enrollment-links') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
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
            <label class="block text-sm font-medium text-slate-700" for="filter-type">Tip</label>
            <select id="filter-type" name="type" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Toate</option>
                <option value="supplier" <?= ($filters['type'] ?? '') === 'supplier' ? 'selected' : '' ?>>Furnizor</option>
                <option value="client" <?= ($filters['type'] ?? '') === 'client' ? 'selected' : '' ?>>Client</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-supplier">Furnizor</label>
            <input
                id="filter-supplier"
                name="supplier_cui"
                type="text"
                value="<?= htmlspecialchars((string) ($filters['supplier_cui'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="CUI furnizor"
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
            href="<?= App\Support\Url::to('admin/enrollment-links') ?>"
            class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Reseteaza
        </a>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="mt-6 rounded border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Nu exista linkuri de inrolare. Dupa creare, acestea pot fi trimise partenerilor pentru completarea datelor.
    </div>
<?php else: ?>
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Data</th>
                    <th class="px-3 py-2">Tip</th>
                    <th class="px-3 py-2">Furnizor</th>
                    <th class="px-3 py-2">Utilizari</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Expira</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($row['type'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($row['supplier_cui'] ?? '—')) ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= (int) ($row['uses'] ?? 0) ?> / <?= (int) ($row['max_uses'] ?? 1) ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($row['expires_at'] ?? '—')) ?></td>
                        <td class="px-3 py-2 text-right">
                            <?php if (($row['status'] ?? '') === 'active'): ?>
                                <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/disable') ?>">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button
                                        class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                        onclick="return confirm('Sigur doriti sa dezactivati acest link?')"
                                    >
                                        Dezactiveaza link
                                    </button>
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
                return App\Support\Url::to('admin/enrollment-links') . '?' . http_build_query($params);
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

<script>
    (function () {
        const button = document.getElementById('openapi-fetch');
        const cuiInput = document.getElementById('prefill-cui');
        const tokenInput = document.querySelector('input[name="_token"]');
        if (!button || !cuiInput || !tokenInput) {
            return;
        }

        const setValue = (id, value) => {
            const input = document.getElementById(id);
            if (!input || value === null || value === undefined || value === '') {
                return;
            }
            input.value = value;
        };

        button.addEventListener('click', () => {
            const cui = cuiInput.value.trim();
            if (!cui) {
                alert('Completeaza CUI-ul pentru prefill.');
                return;
            }
            const body = new URLSearchParams();
            body.append('_token', tokenInput.value);
            body.append('cui', cui);
            fetch('<?= App\Support\Url::to('admin/enrollment-links/lookup') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data || !data.success) {
                        alert(data && data.message ? data.message : 'Eroare la OpenAPI.');
                        return;
                    }
                    const payload = data.data || {};
                    setValue('prefill-denumire', payload.denumire || '');
                    setValue('prefill-nr', payload.nr_reg_comertului || '');
                    setValue('prefill-adresa', payload.adresa || '');
                    setValue('prefill-localitate', payload.localitate || '');
                    setValue('prefill-judet', payload.judet || '');
                    setValue('prefill-telefon', payload.telefon || '');
                })
                .catch(() => alert('Eroare la OpenAPI.'));
        });
    })();
</script>
