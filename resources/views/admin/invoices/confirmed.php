<?php
    $title = 'Pachete confirmate';
?>

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Pachete confirmate</h1>
        <p class="mt-1 text-sm text-slate-600">
            Pachetele cu toate produsele asociate sunt evidentiate cu violet. Pachetele storno sunt evidentiate cu rosu.
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
                        $isStorno = !empty($row['is_storno']);
                    ?>
                    <?php
                        $rowClass = 'border-b border-slate-100';
                        if ($isStorno) {
                            $rowClass = 'border-b border-rose-100 bg-rose-50';
                        } elseif (!empty($row['all_saga'])) {
                            $rowClass = 'border-b border-slate-100 bg-violet-50';
                        }
                    ?>
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
                            <?php if ($isStorno): ?>
                                <span class="mr-1 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700">
                                    STORNO
                                </span>
                            <?php endif; ?>
                            <?= htmlspecialchars($label) ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= (int) ($stat['line_count'] ?? 0) ?>
                        </td>
                        <td class="px-3 py-2 <?= $isStorno ? 'font-semibold text-rose-700' : 'text-slate-600' ?>">
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
                                <?php elseif ($status === 'imported'): ?>
                                    <div class="text-[11px] font-semibold text-emerald-700">Importat</div>
                                <?php elseif (empty($row['has_fgo_invoice'])): ?>
                                    <div class="text-[11px] font-semibold text-rose-600">Factura FGO lipsa</div>
                                <?php elseif ($status === 'pending' || $status === 'processing'): ?>
                                    <?php if (!empty($sagaToken)): ?>
                                        <a
                                            href="<?= App\Support\Url::to('api/saga/pachet?package_id=' . $packageId . '&token=' . urlencode($sagaToken)) ?>"
                                            class="inline-flex text-[11px] font-semibold text-violet-700 hover:text-violet-800"
                                        >
                                            Json SAGA
                                        </a>
                                    <?php else: ?>
                                        <div class="text-[11px] font-semibold text-rose-600">Token lipsa</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="POST" action="<?= App\Support\Url::to('admin/pachete-confirmate/saga-pending') ?>">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="package_id" value="<?= $packageId ?>">
                                        <button class="inline-flex text-[11px] font-semibold text-violet-700 hover:text-violet-800">
                                            Genereaza SAGA
                                        </button>
                                    </form>
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
