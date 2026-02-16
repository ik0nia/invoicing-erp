<?php
    $title = 'Registru documente';
    $registry = $registry ?? [];
    $filters = $filters ?? ['doc_type' => ''];
    $docTypeOptions = $docTypeOptions ?? [];
    $documents = $documents ?? [];
    $statusLabels = [
        'draft' => 'Ciorna',
        'generated' => 'Generat',
        'sent' => 'Trimis',
        'signed_uploaded' => 'Semnat (incarcat)',
        'approved' => 'Aprobat',
    ];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Registru documente</h1>
        <p class="mt-1 text-sm text-slate-500">Numerotare secventiala unica pentru toate documentele generate.</p>
    </div>
</div>

<div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <div class="font-semibold">Cum functioneaza</div>
    <ul class="mt-2 list-disc space-y-1 pl-5">
        <li>Exista un singur registru global, comun pentru toate documentele.</li>
        <li><strong>next_no</strong> este urmatorul numar ce va fi alocat.</li>
        <li><strong>Seteaza start</strong> actualizeaza simultan <strong>start_no</strong> si <strong>next_no</strong>.</li>
        <li><strong>Reseteaza la start</strong> seteaza <strong>next_no = start_no</strong>.</li>
    </ul>
</div>

<?php
    $series = (string) ($registry['series'] ?? '');
    $startNo = max(1, (int) ($registry['start_no'] ?? 1));
    $nextNo = max(1, (int) ($registry['next_no'] ?? 1));
    $updatedAt = (string) ($registry['updated_at'] ?? '');
?>
<div class="mt-6 rounded border border-slate-200 bg-white p-5">
    <div class="grid gap-4 md:grid-cols-2">
        <form method="POST" action="<?= App\Support\Url::to('admin/registru-documente/save') ?>" class="space-y-3">
            <?= App\Support\Csrf::input() ?>
            <div class="text-sm font-semibold text-slate-700">Setari registru global</div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Serie</label>
                    <input
                        type="text"
                        name="series"
                        value="<?= htmlspecialchars($series) ?>"
                        maxlength="16"
                        placeholder="ex: CTR"
                        class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-xs"
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Start</label>
                    <input
                        type="number"
                        min="1"
                        name="start_no"
                        value="<?= $startNo ?>"
                        class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-xs"
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Urmatorul nr.</label>
                    <input
                        type="number"
                        min="1"
                        name="next_no"
                        value="<?= $nextNo ?>"
                        class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-xs"
                    >
                </div>
            </div>
            <button class="rounded border border-blue-600 bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                Salveaza
            </button>
        </form>
        <div class="space-y-3">
            <div class="text-sm font-semibold text-slate-700">Operatii rapide</div>
            <div class="text-xs text-slate-600">
                Ultima actualizare: <?= htmlspecialchars($updatedAt !== '' ? $updatedAt : '—') ?>
            </div>
            <form method="POST" action="<?= App\Support\Url::to('admin/registru-documente/set-start') ?>" class="inline-flex items-end gap-2">
                <?= App\Support\Csrf::input() ?>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Start nou</label>
                    <input
                        type="number"
                        min="1"
                        name="start_no"
                        value="<?= $startNo ?>"
                        class="mt-1 w-24 rounded border border-slate-300 px-2 py-1.5 text-xs"
                    >
                    <input type="hidden" name="series" value="<?= htmlspecialchars($series) ?>">
                </div>
                <button class="rounded border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">
                    Seteaza start
                </button>
            </form>
            <form method="POST" action="<?= App\Support\Url::to('admin/registru-documente/reset-start') ?>">
                <?= App\Support\Csrf::input() ?>
                <button
                    class="rounded border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                    onclick="return confirm('Resetezi numerotarea la start pentru registrul global?')"
                >
                    Reseteaza la start
                </button>
            </form>
        </div>
    </div>
</div>

<div class="mt-8">
    <h2 class="text-lg font-semibold text-slate-900">Documente din registru</h2>
    <p class="mt-1 text-sm text-slate-500">
        Lista afiseaza ultimele 500 documente, cu posibilitate de filtrare dupa tip document.
    </p>
</div>

<form method="GET" action="<?= App\Support\Url::to('admin/registru-documente') ?>" class="mt-4 rounded border border-slate-200 bg-white p-4">
    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="filter-doc-type">Tip document (doc_type)</label>
            <select id="filter-doc-type" name="doc_type" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Toate</option>
                <?php foreach ($docTypeOptions as $docTypeOption): ?>
                    <option value="<?= htmlspecialchars((string) $docTypeOption) ?>" <?= (string) ($filters['doc_type'] ?? '') === (string) $docTypeOption ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $docTypeOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        <button class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Filtreaza
        </button>
        <a href="<?= App\Support\Url::to('admin/registru-documente') ?>" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Reseteaza
        </a>
    </div>
</form>

<?php if (empty($documents)): ?>
    <div class="mt-4 rounded border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Nu exista documente pentru filtrul selectat.
    </div>
<?php else: ?>
    <div class="mt-4 overflow-x-auto rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Nr. registru</th>
                    <th class="px-3 py-2">Doc type</th>
                    <th class="px-3 py-2">Data contract</th>
                    <th class="px-3 py-2">Firma</th>
                    <th class="px-3 py-2">CUI</th>
                    <th class="px-3 py-2">Titlu</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Creat</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $document): ?>
                    <?php
                        $docNoDisplay = trim((string) ($document['doc_full_no'] ?? ''));
                        if ($docNoDisplay === '') {
                            $docNo = (int) ($document['doc_no'] ?? 0);
                            if ($docNo > 0) {
                                $docSeries = trim((string) ($document['doc_series'] ?? ''));
                                $docNoDisplay = str_pad((string) $docNo, 6, '0', STR_PAD_LEFT);
                                if ($docSeries !== '') {
                                    $docNoDisplay = $docSeries . '-' . $docNoDisplay;
                                }
                            }
                        }
                        $contractDateRaw = trim((string) ($document['contract_date'] ?? ''));
                        $contractDateDisplay = '—';
                        if ($contractDateRaw !== '') {
                            $timestamp = strtotime($contractDateRaw);
                            $contractDateDisplay = $timestamp !== false ? date('d.m.Y', $timestamp) : $contractDateRaw;
                        }
                        $companyName = trim((string) ($document['registry_company_name'] ?? '—'));
                        $companyCui = trim((string) ($document['registry_company_cui'] ?? ''));
                        $statusKey = (string) ($document['status'] ?? '');
                        $statusLabel = $statusLabels[$statusKey] ?? ($statusKey !== '' ? $statusKey : '—');
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-700">
                            <?= $docNoDisplay !== '' ? '<span class="font-mono">' . htmlspecialchars($docNoDisplay) . '</span>' : '<span class="text-amber-700">Fara numar</span>' ?>
                        </td>
                        <td class="px-3 py-2 font-mono text-slate-600"><?= htmlspecialchars((string) ($document['doc_type'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($contractDateDisplay) ?></td>
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars($companyName !== '' ? $companyName : '—') ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($companyCui !== '' ? $companyCui : '—') ?></td>
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($document['title'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($statusLabel) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($document['created_at'] ?? '—')) ?></td>
                        <td class="px-3 py-2 text-right">
                            <a href="<?= App\Support\Url::to('admin/contracts/download?id=' . (int) ($document['id'] ?? 0)) ?>" class="text-xs font-semibold text-blue-700 hover:text-blue-800">
                                Descarca
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
