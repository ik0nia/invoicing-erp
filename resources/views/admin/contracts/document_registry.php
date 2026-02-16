<?php
    $title = 'Registru documente';
    $filters = $filters ?? ['doc_type' => ''];
    $activeTab = (string) ($activeTab ?? ($filters['tab'] ?? 'client'));
    if (!in_array($activeTab, ['client', 'supplier'], true)) {
        $activeTab = 'client';
    }
    $docTypeOptions = $docTypeOptions ?? [];
    $documents = $documents ?? [];
    $tabLabels = [
        'client' => 'Clienti',
        'supplier' => 'Furnizori',
    ];
    $activeTabLabel = $tabLabels[$activeTab] ?? 'Clienti';
    $statusLabels = [
        'draft' => 'Ciorna',
        'generated' => 'Generat',
        'sent' => 'Trimis',
        'signed_uploaded' => 'Semnat (incarcat)',
        'approved' => 'Aprobat',
    ];
?>

<div class="mt-4">
    <div class="flex flex-wrap items-center gap-2">
        <?php foreach ($tabLabels as $tabKey => $tabLabel): ?>
            <?php
                $tabUrl = App\Support\Url::to('admin/registru-documente?tab=' . urlencode($tabKey));
                $isActiveTab = $activeTab === $tabKey;
            ?>
            <a
                href="<?= htmlspecialchars($tabUrl) ?>"
                class="rounded-full border px-3 py-1.5 text-sm font-semibold <?= $isActiveTab ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>"
            >
                <?= htmlspecialchars($tabLabel) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <h2 class="mt-4 text-lg font-semibold text-slate-900">Documente din registru - <?= htmlspecialchars($activeTabLabel) ?></h2>
    <p class="mt-1 text-sm text-slate-500">
        Lista afiseaza ultimele 500 documente, cu posibilitate de filtrare dupa tip document.
    </p>
</div>

<form method="GET" action="<?= App\Support\Url::to('admin/registru-documente') ?>" class="mt-4 rounded border border-slate-200 bg-white p-4">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
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
        <a href="<?= App\Support\Url::to('admin/registru-documente?tab=' . urlencode($activeTab)) ?>" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
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
