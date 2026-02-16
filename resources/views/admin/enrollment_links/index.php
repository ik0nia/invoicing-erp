<?php
    $title = !empty($isPendingPage) ? 'Inrolari in asteptare' : 'Adauga partener';
    $rows = $rows ?? [];
    $canApproveOnboarding = !empty($canApproveOnboarding);
    $isPendingPage = !empty($isPendingPage);
    $filters = $filters ?? [
        'status' => '',
        'type' => '',
        'onboarding_status' => '',
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
    $singleUserSupplierCui = count($userSuppliers) === 1 ? (string) $userSuppliers[0] : '';
    $listPath = $isPendingPage ? 'admin/inrolari' : 'admin/enrollment-links';

    $filterParams = [
        'status' => $filters['status'] ?? '',
        'type' => $filters['type'] ?? '',
        'onboarding_status' => $filters['onboarding_status'] ?? '',
        'supplier_cui' => $filters['supplier_cui'] ?? '',
        'partner_cui' => $filters['partner_cui'] ?? '',
        'relation_supplier_cui' => $filters['relation_supplier_cui'] ?? '',
        'relation_client_cui' => $filters['relation_client_cui'] ?? '',
    ];
    $filterParams = array_filter($filterParams, static fn ($value) => $value !== '' && $value !== null);
    $returnToPath = '/' . ltrim($listPath, '/');
    if (!empty($filterParams)) {
        $returnToPath .= '?' . http_build_query($filterParams);
    }
    $paginationParams = $filterParams;
    $paginationParams['per_page'] = (int) ($pagination['per_page'] ?? 50);
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900"><?= $isPendingPage ? 'Inrolari in asteptare' : 'Adauga partener' ?></h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= $isPendingPage ? 'Cereri trimise spre activare manuala de staff intern.' : 'Gestioneaza linkurile publice pentru onboarding si documente.' ?>
        </p>
    </div>
</div>

<?php if (!$isPendingPage): ?>
    <?php if (!empty($newLink)): ?>
        <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            Link public (afisat o singura data):
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <input
                    type="text"
                    readonly
                    id="enroll-link-output"
                    value="<?= htmlspecialchars((string) ($newLink['url'] ?? '')) ?>"
                    class="w-full max-w-2xl rounded border border-emerald-200 bg-white px-3 py-2 text-xs text-emerald-900"
                >
                <button
                    type="button"
                    id="enroll-copy"
                    class="rounded border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                >
                    Copiaza in clipboard
                </button>
                <span id="enroll-copy-status" class="text-xs text-emerald-700"></span>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/create') ?>" class="mt-4 rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 via-sky-50 to-emerald-50 p-5 shadow-sm ring-1 ring-blue-100">
        <?= App\Support\Csrf::input() ?>
        <div class="mb-4 rounded-lg border border-blue-200 bg-white/70 px-4 py-3">
            <div class="text-sm font-semibold text-blue-900">Creeaza rapid un link nou pentru onboarding</div>
            <p class="mt-1 text-xs text-blue-800">Alege tipul inrolarii, completeaza precompletarile si trimite linkul partenerului.</p>
        </div>

        <div>
            <label class="block text-base font-semibold text-slate-800">Tip inrolare</label>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <label class="group cursor-pointer">
                    <input type="radio" name="type" value="supplier" checked class="peer sr-only">
                    <span class="flex items-start gap-3 rounded-xl border border-blue-200 bg-white px-4 py-3 shadow-sm transition group-hover:border-blue-300 peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:ring-2 peer-checked:ring-blue-200">
                        <span class="mt-1 inline-flex h-4 w-4 shrink-0 rounded-full border-2 border-slate-400 bg-white"></span>
                        <span>
                            <span class="block text-base font-semibold text-slate-900">Furnizor</span>
                            <span class="block text-sm text-slate-600">Link pentru inrolarea unei companii furnizor.</span>
                        </span>
                    </span>
                </label>
                <label class="group cursor-pointer">
                    <input type="radio" name="type" value="client" class="peer sr-only">
                    <span class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-white px-4 py-3 shadow-sm transition group-hover:border-emerald-300 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:ring-2 peer-checked:ring-emerald-200">
                        <span class="mt-1 inline-flex h-4 w-4 shrink-0 rounded-full border-2 border-slate-400 bg-white"></span>
                        <span>
                            <span class="block text-base font-semibold text-slate-900">Client</span>
                            <span class="block text-sm text-slate-600">Link pentru inrolarea unui client asociat unui furnizor.</span>
                        </span>
                    </span>
                </label>
            </div>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="supplier-cui">Furnizor (doar pentru client)</label>
                <div
                    data-supplier-wrapper
                    class="relative"
                    data-supplier-picker
                    data-search-url="<?= App\Support\Url::to('admin/enrollment-links/supplier-search') ?>"
                    data-info-url="<?= App\Support\Url::to('admin/enrollment-links/supplier-info') ?>"
                >
                    <input
                        id="supplier-cui"
                        type="text"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        placeholder="Cauta dupa denumire sau CUI"
                        autocomplete="off"
                        data-supplier-display
                    >
                    <input
                        type="hidden"
                        name="supplier_cui"
                        value="<?= htmlspecialchars($singleUserSupplierCui) ?>"
                        data-supplier-value
                    >
                    <div class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-slate-300 bg-white p-1 shadow-xl ring-1 ring-slate-200 divide-y divide-slate-100" data-supplier-list></div>
                    <p class="mt-1 text-xs text-slate-500" data-supplier-hint>
                        Selectia afisata include denumirea firmei; la salvare se transmite doar CUI-ul.
                    </p>
                </div>
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
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="16"
                    data-lookup-url="<?= App\Support\Url::to('admin/enrollment-links/lookup') ?>"
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
        <p id="prefill-lookup-status" class="mt-2 text-xs text-slate-500"></p>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Creeaza link public
            </button>
            <p class="text-xs text-slate-500">La creare, datele de precompletare se completeaza automat din OpenAPI pe baza CUI-ului.</p>
        </div>
    </form>
<?php endif; ?>

<form method="GET" action="<?= App\Support\Url::to($listPath) ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
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
            <label class="block text-sm font-medium text-slate-700" for="filter-onboarding-status">Status onboarding</label>
            <select id="filter-onboarding-status" name="onboarding_status" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Toate</option>
                <option value="draft" <?= ($filters['onboarding_status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="waiting_signature" <?= ($filters['onboarding_status'] ?? '') === 'waiting_signature' ? 'selected' : '' ?>>In asteptare semnaturi</option>
                <option value="submitted" <?= ($filters['onboarding_status'] ?? '') === 'submitted' ? 'selected' : '' ?>>Trimis spre activare</option>
                <option value="approved" <?= ($filters['onboarding_status'] ?? '') === 'approved' ? 'selected' : '' ?>>Aprobat</option>
                <option value="rejected" <?= ($filters['onboarding_status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Respins</option>
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
            <label class="block text-sm font-medium text-slate-700" for="filter-partner">Partner CUI</label>
            <input
                id="filter-partner"
                name="partner_cui"
                type="text"
                value="<?= htmlspecialchars((string) ($filters['partner_cui'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="CUI partener"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-relation-supplier">Relatie furnizor</label>
            <input
                id="filter-relation-supplier"
                name="relation_supplier_cui"
                type="text"
                value="<?= htmlspecialchars((string) ($filters['relation_supplier_cui'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="CUI furnizor relatie"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-relation-client">Relatie client</label>
            <input
                id="filter-relation-client"
                name="relation_client_cui"
                type="text"
                value="<?= htmlspecialchars((string) ($filters['relation_client_cui'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="CUI client relatie"
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
            href="<?= App\Support\Url::to($listPath) ?>"
            class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Reseteaza
        </a>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="mt-6 rounded border border-slate-200 bg-white p-6 text-sm text-slate-600">
        <?= $isPendingPage
            ? 'Nu exista inrolari in asteptare.'
            : 'Nu exista linkuri publice. Dupa creare, acestea pot fi trimise partenerilor pentru completarea datelor.' ?>
    </div>
<?php else: ?>
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Creat la</th>
                    <th class="px-3 py-2">Tip</th>
                    <th class="px-3 py-2">Partner CUI</th>
                    <th class="px-3 py-2">Relatie</th>
                    <th class="px-3 py-2">Pas curent</th>
                    <th class="px-3 py-2">Status onboarding</th>
                    <th class="px-3 py-2">Ultima accesare</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-700">
                            <?= htmlspecialchars((string) (($row['type'] ?? '') === 'supplier' ? 'Furnizor' : 'Client')) ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($row['partner_cui'] ?? '—')) ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <?php if (!empty($row['relation_supplier_cui']) || !empty($row['relation_client_cui'])): ?>
                                <?= htmlspecialchars((string) ($row['relation_supplier_cui'] ?? '')) ?> /
                                <?= htmlspecialchars((string) ($row['relation_client_cui'] ?? '')) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= (int) ($row['current_step'] ?? 1) ?>/3
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?php
                                $onboardingStatus = (string) ($row['onboarding_status'] ?? 'draft');
                                $onboardingLabel = $onboardingStatus === 'waiting_signature'
                                    ? 'In asteptare semnaturi'
                                    : ($onboardingStatus === 'submitted'
                                        ? 'Trimis spre activare'
                                        : ($onboardingStatus === 'approved'
                                            ? 'Aprobat'
                                            : ($onboardingStatus === 'rejected' ? 'Respins' : 'Draft')));
                            ?>
                            <div><?= htmlspecialchars($onboardingLabel) ?></div>
                            <?php if (!empty($row['submitted_at'])): ?>
                                <div class="text-xs text-slate-500">Trimis: <?= htmlspecialchars((string) $row['submitted_at']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['approved_at'])): ?>
                                <div class="text-xs text-slate-500">Aprobat: <?= htmlspecialchars((string) $row['approved_at']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($row['last_used_at'] ?? '—')) ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= htmlspecialchars((string) (($row['status'] ?? '') === 'active' ? 'Activ' : 'Dezactivat')) ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <?php if (($row['status'] ?? '') === 'active'): ?>
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/disable') ?>">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button
                                            class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                            onclick="return confirm('Sigur doriti sa dezactivati acest link?')"
                                        >
                                            Dezactiveaza
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/regenerate') ?>">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button
                                            class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                                            onclick="return confirm('Regenerati linkul? Linkul anterior nu va mai functiona.')"
                                        >
                                            Regenereaza
                                        </button>
                                    </form>
                                    <?php if ($canApproveOnboarding && ($row['onboarding_status'] ?? '') === 'submitted'): ?>
                                        <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/approve-onboarding') ?>">
                                            <?= App\Support\Csrf::input() ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToPath) ?>">
                                            <button
                                                class="text-xs font-semibold text-emerald-600 hover:text-emerald-700"
                                                onclick="return confirm('Confirmati activarea manuala a inrolarii?')"
                                            >
                                                Aproba / Activeaza
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canApproveOnboarding): ?>
                                        <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/reset-onboarding') ?>">
                                            <?= App\Support\Csrf::input() ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToPath) ?>">
                                            <button
                                                class="text-xs font-semibold text-amber-700 hover:text-amber-800"
                                                onclick="return confirm('Resetezi onboarding-ul la Pasul 1? Se vor sterge documentele de onboarding existente.')"
                                            >
                                                Reset onboarding
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <span class="text-xs text-slate-400">Dezactivat</span>
                                    <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/regenerate') ?>">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button
                                            class="text-xs font-semibold text-blue-600 hover:text-blue-700"
                                            onclick="return confirm('Regenerati linkul? Linkul anterior nu va mai functiona.')"
                                        >
                                            Regenereaza
                                        </button>
                                    </form>
                                    <?php if ($canApproveOnboarding && ($row['onboarding_status'] ?? '') === 'submitted'): ?>
                                        <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/approve-onboarding') ?>">
                                            <?= App\Support\Csrf::input() ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToPath) ?>">
                                            <button
                                                class="text-xs font-semibold text-emerald-600 hover:text-emerald-700"
                                                onclick="return confirm('Confirmati activarea manuala a inrolarii?')"
                                            >
                                                Aproba / Activeaza
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canApproveOnboarding): ?>
                                        <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/reset-onboarding') ?>">
                                            <?= App\Support\Csrf::input() ?>
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToPath) ?>">
                                            <button
                                                class="text-xs font-semibold text-amber-700 hover:text-amber-800"
                                                onclick="return confirm('Resetezi onboarding-ul la Pasul 1? Se vor sterge documentele de onboarding existente.')"
                                            >
                                                Reset onboarding
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
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
            $buildPageUrl = static function (int $targetPage) use ($baseParams, $listPath): string {
                $params = $baseParams;
                $params['page'] = $targetPage;
                return App\Support\Url::to($listPath) . '?' . http_build_query($params);
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
                din <?= (int) ($pagination['total'] ?? 0) ?> <?= $isPendingPage ? 'inrolari' : 'linkuri publice' ?>
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
        const escapeHtml = (value) => {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const supplierPicker = document.querySelector('[data-supplier-picker]');
        const commissionInput = document.getElementById('commission');
        if (supplierPicker) {
            const displayInput = supplierPicker.querySelector('[data-supplier-display]');
            const hiddenInput = supplierPicker.querySelector('[data-supplier-value]');
            const list = supplierPicker.querySelector('[data-supplier-list]');
            const hint = supplierPicker.querySelector('[data-supplier-hint]');
            const supplierWrapper = document.querySelector('[data-supplier-wrapper]');
            const typeInputs = Array.from(document.querySelectorAll('input[name="type"]'));
            const searchUrl = supplierPicker.getAttribute('data-search-url') || '';
            const infoUrl = supplierPicker.getAttribute('data-info-url') || '';
            let requestId = 0;
            let timer = null;
            const defaultSupplierHint = 'Selectia afisata include denumirea firmei; la salvare se transmite doar CUI-ul.';

            const setHint = (text) => {
                if (hint) {
                    hint.textContent = text;
                }
            };

            const formatCommission = (value) => {
                const num = Number(value);
                if (!Number.isFinite(num)) {
                    return '';
                }
                return num.toFixed(4).replace(/\.?0+$/, '');
            };

            const extractCuiCandidate = (value) => {
                const raw = String(value || '').trim();
                if (raw === '') {
                    return '';
                }
                if (/^\d+$/.test(raw)) {
                    return raw;
                }
                const compact = raw.replace(/\s+/g, '').toUpperCase();
                if (/^RO\d+$/.test(compact)) {
                    return compact.replace(/^RO/, '');
                }

                return '';
            };

            const clearList = () => {
                if (!list) {
                    return;
                }
                list.innerHTML = '';
                list.classList.add('hidden');
            };

            const applySelection = (item, applyCommission) => {
                if (!displayInput || !hiddenInput || !item) {
                    return;
                }
                const cui = String(item.cui || '').replace(/\D+/g, '');
                const name = String(item.name || '').trim();
                const label = name && cui ? `${name} - ${cui}` : (String(item.label || cui).trim());
                hiddenInput.value = cui;
                displayInput.value = label;
                if (applyCommission && commissionInput && Object.prototype.hasOwnProperty.call(item, 'default_commission')) {
                    const commission = formatCommission(item.default_commission);
                    if (commission !== '') {
                        commissionInput.value = commission;
                    }
                }
                if (cui !== '') {
                    setHint(`Selectat: ${label}. Se transmite CUI: ${cui}.`);
                } else {
                    setHint(defaultSupplierHint);
                }
                clearList();
            };

            const renderItems = (items) => {
                if (!list) {
                    return;
                }
                if (!Array.isArray(items) || items.length === 0) {
                    list.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">Nu exista rezultate.</div>';
                    list.classList.remove('hidden');
                    return;
                }
                list.innerHTML = items
                    .map((item) => {
                        const name = escapeHtml(item.name || item.cui || '');
                        const cui = escapeHtml(item.cui || '');
                        const commission = item.default_commission !== null && item.default_commission !== undefined
                            ? formatCommission(item.default_commission)
                            : '';
                        const commissionHtml = commission !== ''
                            ? `<div class="text-[11px] text-emerald-700">Comision implicit: ${escapeHtml(commission)}%</div>`
                            : '';
                        return `
                            <button
                                type="button"
                                class="block w-full rounded px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700"
                                data-supplier-item
                                data-cui="${cui}"
                                data-name="${escapeHtml(item.name || '')}"
                                data-commission="${commission !== '' ? escapeHtml(commission) : ''}"
                            >
                                <div class="font-medium text-slate-900">${name}</div>
                                <div class="text-xs text-slate-500">${cui}</div>
                                ${commissionHtml}
                            </button>
                        `;
                    })
                    .join('');
                list.classList.remove('hidden');
            };

            const fetchSuppliers = (term) => {
                if (!searchUrl) {
                    return;
                }
                const currentRequestId = ++requestId;
                const url = new URL(searchUrl, window.location.origin);
                url.searchParams.set('term', term);
                url.searchParams.set('limit', '15');
                fetch(url.toString(), { credentials: 'same-origin' })
                    .then((response) => response.json())
                    .then((data) => {
                        if (currentRequestId !== requestId) {
                            return;
                        }
                        if (!data || data.success !== true) {
                            renderItems([]);
                            return;
                        }
                        renderItems(data.items || []);
                    })
                    .catch(() => {
                        if (currentRequestId !== requestId) {
                            return;
                        }
                        renderItems([]);
                    });
            };

            const resolveSupplierByCui = (cui, applyCommission) => {
                const normalized = String(cui || '').replace(/\D+/g, '');
                if (!normalized || !infoUrl) {
                    return;
                }
                const url = new URL(infoUrl, window.location.origin);
                url.searchParams.set('cui', normalized);
                fetch(url.toString(), { credentials: 'same-origin' })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data || data.success !== true || !data.item) {
                            return;
                        }
                        applySelection(data.item, applyCommission);
                    })
                    .catch(() => {});
            };

            const selectedLinkType = () => {
                const checked = typeInputs.find((input) => input.checked);
                return checked ? String(checked.value || 'supplier') : 'supplier';
            };

            const syncSupplierState = () => {
                const requiresSupplier = selectedLinkType() === 'client';
                if (supplierWrapper) {
                    supplierWrapper.classList.toggle('opacity-70', !requiresSupplier);
                }
                if (displayInput) {
                    displayInput.disabled = !requiresSupplier;
                    displayInput.classList.toggle('bg-slate-100', !requiresSupplier);
                    displayInput.classList.toggle('cursor-not-allowed', !requiresSupplier);
                    if (!requiresSupplier) {
                        displayInput.value = '';
                    }
                }
                if (!requiresSupplier) {
                    if (hiddenInput) {
                        hiddenInput.value = '';
                    }
                    clearList();
                    setHint('Pentru link de tip furnizor, acest camp nu este obligatoriu.');
                    return;
                }
                if (hiddenInput && hiddenInput.value.trim() === '') {
                    setHint(defaultSupplierHint);
                }
            };

            if (displayInput && hiddenInput) {
                const parentForm = displayInput.closest('form');
                if (parentForm) {
                    parentForm.addEventListener('submit', () => {
                        if (selectedLinkType() !== 'client') {
                            hiddenInput.value = '';
                            return;
                        }
                        if (hiddenInput.value.trim() !== '') {
                            return;
                        }
                        const maybeCui = extractCuiCandidate(displayInput.value);
                        if (maybeCui !== '') {
                            hiddenInput.value = maybeCui;
                        }
                    });
                }
                displayInput.addEventListener('focus', () => {
                    if (selectedLinkType() !== 'client') {
                        return;
                    }
                    fetchSuppliers(displayInput.value.trim());
                });
                displayInput.addEventListener('input', () => {
                    if (selectedLinkType() !== 'client') {
                        return;
                    }
                    hiddenInput.value = '';
                    setHint(defaultSupplierHint);
                    const query = displayInput.value.trim();
                    if (timer) {
                        clearTimeout(timer);
                    }
                    timer = setTimeout(() => {
                        fetchSuppliers(query);
                    }, 200);
                });
                displayInput.addEventListener('blur', () => {
                    window.setTimeout(() => {
                        clearList();
                        if (selectedLinkType() !== 'client') {
                            return;
                        }
                        if (hiddenInput.value.trim() !== '') {
                            return;
                        }
                        const maybeCui = extractCuiCandidate(displayInput.value);
                        if (maybeCui !== '') {
                            resolveSupplierByCui(maybeCui, true);
                        }
                    }, 150);
                });
            }
            if (list) {
                list.addEventListener('click', (event) => {
                    const target = event.target.closest('[data-supplier-item]');
                    if (!target) {
                        return;
                    }
                    const commissionRaw = target.getAttribute('data-commission');
                    const item = {
                        cui: target.getAttribute('data-cui') || '',
                        name: target.getAttribute('data-name') || '',
                        default_commission: commissionRaw !== null && commissionRaw !== '' ? commissionRaw : null,
                    };
                    applySelection(item, true);
                });
            }
            if (typeInputs.length > 0) {
                typeInputs.forEach((input) => {
                    input.addEventListener('change', syncSupplierState);
                });
            }
            syncSupplierState();
            if (selectedLinkType() === 'client' && hiddenInput && hiddenInput.value.trim() !== '') {
                resolveSupplierByCui(hiddenInput.value.trim(), true);
            }
        }

        const prefillCuiInput = document.getElementById('prefill-cui');
        if (prefillCuiInput) {
            const prefillStatus = document.getElementById('prefill-lookup-status');
            const lookupUrl = prefillCuiInput.getAttribute('data-lookup-url') || '';
            const form = prefillCuiInput.closest('form');
            const csrfInput = form ? form.querySelector('input[name="_token"]') : null;
            const getField = (id) => document.getElementById(id);
            const prefillFields = {
                denumire: getField('prefill-denumire'),
                nr_reg_comertului: getField('prefill-nr'),
                adresa: getField('prefill-adresa'),
                localitate: getField('prefill-localitate'),
                judet: getField('prefill-judet'),
                telefon: getField('prefill-telefon'),
            };
            let lastLookupCui = '';
            let activeLookup = 0;

            const setPrefillStatus = (text, tone = 'neutral') => {
                if (!prefillStatus) {
                    return;
                }
                prefillStatus.textContent = text;
                prefillStatus.className = 'mt-2 text-xs';
                if (tone === 'error') {
                    prefillStatus.classList.add('text-rose-600');
                    return;
                }
                if (tone === 'success') {
                    prefillStatus.classList.add('text-emerald-700');
                    return;
                }
                prefillStatus.classList.add('text-slate-500');
            };

            const digitsOnly = (value) => String(value || '').replace(/\D+/g, '');

            const applyPrefillData = (data) => {
                Object.keys(prefillFields).forEach((key) => {
                    const field = prefillFields[key];
                    if (!field) {
                        return;
                    }
                    const value = Object.prototype.hasOwnProperty.call(data, key) ? String(data[key] ?? '') : '';
                    field.value = value;
                });
            };

            const lookupCompanyByCui = (cui) => {
                if (!lookupUrl || !csrfInput || !csrfInput.value) {
                    return;
                }
                const normalizedCui = digitsOnly(cui);
                if (normalizedCui === '') {
                    setPrefillStatus('');
                    return;
                }
                if (normalizedCui === lastLookupCui) {
                    return;
                }

                const requestId = ++activeLookup;
                setPrefillStatus('Se cauta datele firmei in OpenAPI...');
                const payload = new URLSearchParams();
                payload.set('_token', csrfInput.value);
                payload.set('cui', normalizedCui);

                fetch(lookupUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: payload.toString(),
                })
                    .then((response) => response.json())
                    .then((json) => {
                        if (requestId !== activeLookup) {
                            return;
                        }
                        if (!json || json.success !== true || typeof json.data !== 'object' || json.data === null) {
                            const message = json && typeof json.message === 'string' && json.message !== ''
                                ? json.message
                                : 'Nu am putut prelua datele firmei pentru acest CUI.';
                            setPrefillStatus(message, 'error');
                            return;
                        }
                        applyPrefillData(json.data);
                        lastLookupCui = normalizedCui;
                        setPrefillStatus('Datele firmei au fost preluate automat.', 'success');
                    })
                    .catch(() => {
                        if (requestId !== activeLookup) {
                            return;
                        }
                        setPrefillStatus('Eroare la interogarea OpenAPI.', 'error');
                    });
            };

            prefillCuiInput.addEventListener('input', () => {
                const sanitized = digitsOnly(prefillCuiInput.value);
                if (prefillCuiInput.value !== sanitized) {
                    prefillCuiInput.value = sanitized;
                }
                if (sanitized === '') {
                    lastLookupCui = '';
                    setPrefillStatus('');
                }
            });

            prefillCuiInput.addEventListener('blur', () => {
                const sanitized = digitsOnly(prefillCuiInput.value);
                prefillCuiInput.value = sanitized;
                lookupCompanyByCui(sanitized);
            });
        }

        const copyButton = document.getElementById('enroll-copy');
        const output = document.getElementById('enroll-link-output');
        const status = document.getElementById('enroll-copy-status');
        if (copyButton && output) {
            const doCopy = () => {
                const value = output.value;
                if (!value) {
                    return;
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(() => {
                        if (status) {
                            status.textContent = 'Copiat.';
                        }
                    });
                } else {
                    output.select();
                    document.execCommand('copy');
                    if (status) {
                        status.textContent = 'Copiat.';
                    }
                }
            };
            output.addEventListener('click', doCopy);
            copyButton.addEventListener('click', doCopy);
        }
    })();
</script>
