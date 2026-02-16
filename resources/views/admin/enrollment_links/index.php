<?php
    $title = !empty($isPendingPage) ? 'Inrolari in asteptare' : 'Link-uri publice';
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
    $paginationParams = $filterParams;
    $paginationParams['per_page'] = (int) ($pagination['per_page'] ?? 50);
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900"><?= $isPendingPage ? 'Inrolari in asteptare' : 'Link-uri publice' ?></h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= $isPendingPage ? 'Cereri trimise spre activare manuala de staff intern.' : 'Gestioneaza linkurile publice pentru onboarding si documente.' ?>
        </p>
    </div>
</div>

<div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
    <a
        href="<?= App\Support\Url::to('admin/enrollment-links') ?>"
        class="rounded border px-3 py-1.5 font-semibold <?= !$isPendingPage ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"
    >
        Toate link-urile
    </a>
    <?php if ($canApproveOnboarding): ?>
        <a
            href="<?= App\Support\Url::to('admin/inrolari') ?>"
            class="rounded border px-3 py-1.5 font-semibold <?= $isPendingPage ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"
        >
            Inrolari in asteptare
        </a>
    <?php endif; ?>
</div>

<?php if (!$isPendingPage): ?>
    <div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        <div class="font-semibold">Ce este un link public?</div>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>Linkul public permite completarea datelor, contactelor si accesul la contracte.</li>
            <li>Este multi-utilizare si poate fi folosit ori de cate ori este nevoie.</li>
            <li>Pentru furnizor se foloseste la inrolarea unui furnizor.</li>
            <li>Pentru client se foloseste la inrolarea unui client asociat unui furnizor.</li>
            <li>Linkul poate fi dezactivat sau regenerat oricand.</li>
        </ul>
    </div>

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
                Creeaza link public
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
                                            <button
                                                class="text-xs font-semibold text-emerald-600 hover:text-emerald-700"
                                                onclick="return confirm('Confirmati activarea manuala a inrolarii?')"
                                            >
                                                Aproba / Activeaza
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
                                            <button
                                                class="text-xs font-semibold text-emerald-600 hover:text-emerald-700"
                                                onclick="return confirm('Confirmati activarea manuala a inrolarii?')"
                                            >
                                                Aproba / Activeaza
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
