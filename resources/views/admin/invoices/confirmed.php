<?php
    $title = 'Pachete confirmate';
    $canImportSaga = $canImportSaga ?? false;
    $sagaDebug = $sagaDebug ?? null;
?>

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Pachete confirmate</h1>
        <p class="mt-1 text-sm text-slate-600">
            Pachetele cu toate produsele asociate sunt evidentiate cu violet.
        </p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/facturi') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi la facturi
    </a>
</div>

<?php if (empty($packages)): ?>
    <div class="mt-6 rounded border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Nu exista pachete confirmate.
    </div>
<?php else: ?>
    <?php if ($canImportSaga): ?>
        <form
            method="POST"
            action="<?= App\Support\Url::to('admin/pachete-confirmate/import-saga') ?>"
            enctype="multipart/form-data"
            class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
        >
            <?= App\Support\Csrf::input() ?>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-slate-700">Import CSV SAGA</div>
                    <p class="mt-1 text-xs text-slate-500">CSV cu coloane: denumire, pret_vanz (tva optional).</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        type="file"
                        name="saga_csv"
                        accept=".csv"
                        class="text-sm text-slate-600"
                        required
                    >
                    <button class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Importa CSV
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if (!empty($sagaDebug)): ?>
        <details class="mt-4 rounded-lg border border-slate-200 bg-white p-4 text-xs text-slate-600">
            <summary class="cursor-pointer text-sm font-semibold text-slate-700">Debug comparare CSV</summary>
            <div class="mt-2 space-y-2">
                <div>
                    <span class="font-semibold">Coloane detectate:</span>
                    <?= htmlspecialchars(implode(', ', (array) ($sagaDebug['header'] ?? []))) ?>
                </div>
                <div>
                    <span class="font-semibold">Chei CSV (primele <?= count($sagaDebug['saga_keys'] ?? []) ?> din <?= (int) ($sagaDebug['saga_keys_count'] ?? 0) ?>):</span>
                    <?= htmlspecialchars(implode(' | ', (array) ($sagaDebug['saga_keys'] ?? []))) ?>
                </div>
            </div>
            <div class="mt-3 overflow-x-auto rounded border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Pachet</th>
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Cheie comparata</th>
                            <th class="px-3 py-2">Gasit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ((array) ($sagaDebug['packages'] ?? []) as $row): ?>
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2"><?= htmlspecialchars((string) ($row['label'] ?? '')) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars((string) ($row['package_no'] ?? '')) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars((string) ($row['match_key'] ?? '')) ?></td>
                                <td class="px-3 py-2">
                                    <?php if (!empty($row['matched'])): ?>
                                        <span class="font-semibold text-emerald-600">DA</span>
                                    <?php else: ?>
                                        <span class="text-rose-600">NU</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endif; ?>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
        <div>Pachetele gata pentru productie sunt marcate cu violet.</div>
        <label class="flex items-center gap-2">
            <input type="checkbox" id="toggle-saga" class="rounded border-slate-300">
            Afiseaza preturi SAGA
        </label>
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Factura</th>
                    <th class="px-3 py-2">Furnizor</th>
                    <th class="px-3 py-2">Pachet</th>
                    <th class="px-3 py-2">Produse</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2 hidden" data-saga-col>Valoare SAGA</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $row): ?>
                    <?php
                        $packageId = (int) $row['id'];
                        $stat = $totals[$packageId] ?? ['line_count' => 0, 'total_vat' => 0];
                        $labelText = trim((string) ($row['label'] ?? ''));
                        if ($labelText === '') {
                            $labelText = 'Pachet de produse';
                        }
                        $label = $labelText . ' #' . $row['package_no'];
                        $sagaValue = $row['saga_value'] ?? null;
                        $hasSagaMatch = $sagaValue !== null && $sagaValue !== '';
                        $sagaValueNumeric = $hasSagaMatch ? (float) $sagaValue : null;
                        $totalValue = (float) ($stat['total_vat'] ?? 0);
                        $sagaMismatch = $hasSagaMatch && abs($sagaValueNumeric - $totalValue) > 0.01;
                        $sagaClass = $sagaMismatch ? 'text-rose-600 font-semibold' : 'text-slate-600';
                    ?>
                    <?php $rowClass = !empty($row['all_saga']) ? 'border-b border-slate-100 bg-violet-50' : 'border-b border-slate-100'; ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="px-3 py-2">
                            <a
                                href="<?= App\Support\Url::to('admin/facturi?invoice_id=' . (int) $row['invoice_in_id']) ?>"
                                class="text-blue-700 hover:text-blue-800"
                            >
                                <?= htmlspecialchars($row['invoice_number']) ?>
                            </a>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= htmlspecialchars($row['supplier_name'] ?? '') ?>
                        </td>
                        <td class="px-3 py-2 text-slate-700">
                            <?php if (!empty($row['stock_ok'])): ?>
                                <span class="mr-1 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                    ✔
                                </span>
                            <?php endif; ?>
                            <?php if ($hasSagaMatch): ?>
                                <span class="mr-1 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                    BIFA VERDE
                                </span>
                            <?php endif; ?>
                            <?= htmlspecialchars($label) ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= (int) ($stat['line_count'] ?? 0) ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= number_format((float) ($stat['total_vat'] ?? 0), 2, '.', ' ') ?> RON
                        </td>
                        <td class="px-3 py-2 hidden <?= $sagaClass ?>" data-saga-col>
                            <?= $hasSagaMatch ? number_format((float) $sagaValueNumeric, 2, '.', ' ') . ' RON' : '—' ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <?php if (!empty($row['all_saga'])): ?>
                                <?php $status = (string) ($row['saga_status'] ?? ''); ?>
                                <?php if ($status === 'executed'): ?>
                                    <div class="text-[11px] font-semibold text-emerald-700">Executat</div>
                                <?php elseif ($status === 'pending'): ?>
                                    <a
                                        href="<?= App\Support\Url::to('admin/pachete-confirmate/saga-json?package_id=' . $packageId) ?>"
                                        class="inline-flex text-[11px] font-semibold text-violet-700 hover:text-violet-800"
                                    >
                                        Json SAGA
                                    </a>
                                <?php else: ?>
                                    <a
                                        href="<?= App\Support\Url::to('admin/pachete-confirmate/saga-json?package_id=' . $packageId) ?>"
                                        class="inline-flex text-[11px] font-semibold text-violet-700 hover:text-violet-800"
                                    >
                                        Genereaza SAGA
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
    (function () {
        const sagaToggle = document.getElementById('toggle-saga');
        const sagaCols = Array.from(document.querySelectorAll('[data-saga-col]'));
        if (sagaToggle) {
            sagaToggle.addEventListener('change', () => {
                sagaCols.forEach((col) => {
                    col.classList.toggle('hidden', !sagaToggle.checked);
                });
            });
        }
    })();
</script>
