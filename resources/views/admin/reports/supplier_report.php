<?php $title = 'Raport furnizor'; ?>

<?php
$printUrl = App\Support\Url::to('admin/rapoarte/furnizor/print'
    . '?supplier_cui=' . urlencode($supplierCui)
    . '&date_start=' . urlencode($dateStart)
    . '&date_end=' . urlencode($dateEnd));
?>

<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Raport furnizor</h1>
        <p class="mt-1 text-sm text-slate-500">Fisa pe furnizor: facturi FGO, incasari clienti, plati furnizor.</p>
    </div>
    <?php if ($supplierCui !== ''): ?>
        <a
            href="<?= htmlspecialchars($printUrl) ?>"
            target="_blank"
            class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Printeaza fisa
        </a>
    <?php endif; ?>
</div>

<form method="GET" action="<?= App\Support\Url::to('admin/rapoarte/furnizor') ?>" class="mt-4 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-sm font-medium text-slate-700" for="supplier_cui">Furnizor</label>
        <select
            id="supplier_cui"
            name="supplier_cui"
            class="mt-1 rounded border border-slate-300 px-3 py-2 text-sm"
        >
            <option value="">— Selecteaza furnizor —</option>
            <?php foreach ($suppliers as $s): ?>
                <option
                    value="<?= htmlspecialchars($s['supplier_cui']) ?>"
                    <?= (string) $s['supplier_cui'] === $supplierCui ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($s['supplier_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700" for="date_start">De la</label>
        <input
            type="date"
            id="date_start"
            name="date_start"
            value="<?= htmlspecialchars($dateStart) ?>"
            class="mt-1 rounded border border-slate-300 px-3 py-2 text-sm"
        >
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700" for="date_end">Pana la</label>
        <input
            type="date"
            id="date_end"
            name="date_end"
            value="<?= htmlspecialchars($dateEnd) ?>"
            class="mt-1 rounded border border-slate-300 px-3 py-2 text-sm"
        >
    </div>
    <button
        type="submit"
        class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
    >
        Afiseaza
    </button>
</form>

<?php if ($supplierCui !== ''): ?>

<?php
$neincasat = max(0.0, $totalFurnizor - $totalIncasat);
$neplatit  = max(0.0, $totalCuvenitFurnizorDinFacturi - $totalPlatit);
?>

<div class="mt-6 grid gap-4 md:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Total facturat furnizor</div>
        <div class="mt-1 text-lg font-semibold text-slate-900">
            <?= number_format($totalFurnizor, 2, '.', ' ') ?> RON
        </div>
        <div class="mt-0.5 text-xs text-slate-400"><?= count($invoices) ?> facturi FGO</div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Total incasat clienti</div>
        <div class="mt-1 text-lg font-semibold text-emerald-700">
            <?= number_format($totalIncasat, 2, '.', ' ') ?> RON
        </div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Total platit furnizor</div>
        <div class="mt-1 text-lg font-semibold text-rose-700">
            <?= number_format($totalPlatit, 2, '.', ' ') ?> RON
        </div>
    </div>
</div>

<?php $restDePlatitDinIncasat = max(0.0, $totalCuvenitFurnizorDinIncasat - $totalPlatit); ?>

<div class="mt-4 grid gap-3 md:grid-cols-3">
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-center">
        <div class="text-xs font-medium text-amber-700">De incasat de la clienti</div>
        <div class="mt-1 text-lg font-semibold text-amber-900">
            <?= number_format($neincasat, 2, '.', ' ') ?> RON
        </div>
        <div class="mt-0.5 text-xs text-amber-600">
            din <?= number_format($totalFurnizor, 2, '.', ' ') ?> RON facturat
        </div>
    </div>
    <div class="rounded-lg border border-rose-300 bg-rose-50 p-3 text-center">
        <div class="text-xs font-medium text-rose-700">De platit catre furnizor</div>
        <div class="mt-1 text-lg font-semibold text-rose-900">
            <?= number_format($neplatit, 2, '.', ' ') ?> RON
        </div>
        <div class="mt-0.5 text-xs text-rose-600">
            din <?= number_format($totalCuvenitFurnizorDinFacturi, 2, '.', ' ') ?> RON cuvenit furnizorului
        </div>
    </div>
    <div class="rounded-lg border border-indigo-300 bg-indigo-50 p-3 text-center">
        <div class="text-xs font-medium text-indigo-700">De platit din incasat</div>
        <div class="mt-1 text-lg font-semibold text-indigo-900">
            <?= number_format($restDePlatitDinIncasat, 2, '.', ' ') ?> RON
        </div>
        <div class="mt-0.5 text-xs text-indigo-600">
            din <?= number_format($totalCuvenitFurnizorDinIncasat, 2, '.', ' ') ?> RON cuvenit furnizorului din incasat
        </div>
    </div>
</div>

<!-- Facturi FGO -->
<?php
$fileBaseUrl = App\Support\Url::to('admin/facturi/fisier');
$totalIncasatFacturi   = 0.0;
$totalRestDePlataFacturi = 0.0;
foreach ($invoices as $inv) {
    $totalIncasatFacturi   += (float) ($inv['incasat'] ?? 0);
    $totalRestDePlataFacturi += (float) ($inv['rest_de_plata'] ?? 0);
}
?>
<div class="mt-6 rounded-lg border border-slate-200 bg-white p-4">
    <h2 class="text-base font-semibold text-slate-900">Facturi emise catre clienti</h2>
    <div class="mt-3 overflow-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Factura furnizor</th>
                    <th class="px-3 py-2">Factura client</th>
                    <th class="px-3 py-2">Client</th>
                    <th class="px-3 py-2 text-right">Total furnizor</th>
                    <th class="px-3 py-2 text-right">Comision</th>
                    <th class="px-3 py-2 text-right">Incasat</th>
                    <th class="px-3 py-2 text-right">Rest de plata</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="7" class="px-3 py-4 text-center text-slate-500">
                            Nu exista facturi FGO pentru filtrul selectat.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <?php
                            $supplierLabel = htmlspecialchars((string) ($inv['supplier_invoice_label'] ?? ''));
                            $supplierDate  = htmlspecialchars((string) ($inv['issue_date'] ?? ''));
                            $xmlPath       = (string) ($inv['xml_path'] ?? '');
                            $fileUrl       = $fileBaseUrl . '?invoice_id=' . (int) $inv['id'];

                            $fgoLabel = htmlspecialchars(trim(($inv['fgo_series'] ?? '') . ' ' . ($inv['fgo_number'] ?? '')));
                            $fgoDate  = htmlspecialchars((string) ($inv['fgo_date'] ?? ''));
                            $fgoLink  = (string) ($inv['fgo_link'] ?? '');

                            $cp = $inv['commission_percent'] !== null ? (float) $inv['commission_percent'] : null;
                            $restDePlata = (float) ($inv['rest_de_plata'] ?? 0);
                        ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-2">
                                <?php if ($supplierLabel !== '' && $xmlPath !== ''): ?>
                                    <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" rel="noopener"
                                       class="font-medium text-blue-700 hover:text-blue-800">
                                        <?= $supplierLabel ?>
                                    </a>
                                <?php else: ?>
                                    <span class="font-medium text-slate-900"><?= $supplierLabel !== '' ? $supplierLabel : '—' ?></span>
                                <?php endif; ?>
                                <?php if ($supplierDate !== ''): ?>
                                    <div class="text-xs text-slate-400 mt-0.5"><?= $supplierDate ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2">
                                <?php if ($fgoLabel !== '' && $fgoLink !== ''): ?>
                                    <a href="<?= htmlspecialchars($fgoLink) ?>" target="_blank" rel="noopener"
                                       class="font-medium text-blue-700 hover:text-blue-800">
                                        <?= $fgoLabel ?>
                                    </a>
                                <?php else: ?>
                                    <span class="font-medium text-slate-900"><?= $fgoLabel !== '' ? $fgoLabel : '—' ?></span>
                                <?php endif; ?>
                                <?php if ($fgoDate !== ''): ?>
                                    <div class="text-xs text-slate-400 mt-0.5"><?= $fgoDate ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2"><?= htmlspecialchars((string) ($inv['client_name'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-right font-medium text-slate-900">
                                <?= number_format((float) ($inv['total_with_vat'] ?? 0), 2, '.', ' ') ?>
                            </td>
                            <td class="px-3 py-2 text-right text-slate-600">
                                <?= ($cp !== null && $cp >= 0.1) ? number_format($cp, 2, '.', '') . '%' : '—' ?>
                            </td>
                            <td class="px-3 py-2 text-right text-emerald-700 font-medium">
                                <?= number_format((float) ($inv['incasat'] ?? 0), 2, '.', ' ') ?>
                            </td>
                            <td class="px-3 py-2 text-right font-medium <?= $restDePlata > 0 ? 'text-rose-700' : 'text-slate-400' ?>">
                                <?= number_format($restDePlata, 2, '.', ' ') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($invoices)): ?>
                <tfoot class="border-t border-slate-200 bg-slate-50">
                    <tr>
                        <td colspan="3" class="px-3 py-2 text-right text-sm font-semibold text-slate-700">Total</td>
                        <td class="px-3 py-2 text-right text-sm font-semibold text-slate-900">
                            <?= number_format($totalFurnizor, 2, '.', ' ') ?>
                        </td>
                        <td></td>
                        <td class="px-3 py-2 text-right text-sm font-semibold text-emerald-700">
                            <?= number_format($totalIncasatFacturi, 2, '.', ' ') ?>
                        </td>
                        <td class="px-3 py-2 text-right text-sm font-semibold text-rose-700">
                            <?= number_format($totalRestDePlataFacturi, 2, '.', ' ') ?>
                        </td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">

    <!-- Incasari de la clienti -->
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="text-base font-semibold text-slate-900">Incasari de la clienti</h2>
        <div class="mt-3 overflow-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2">Data</th>
                        <th class="px-3 py-2">Client</th>
                        <th class="px-3 py-2 text-right">Suma</th>
                        <th class="px-3 py-2">Observatii</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paymentsIn)): ?>
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-slate-500">
                                Nu exista incasari pentru filtrul selectat.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($paymentsIn as $row): ?>
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2"><?= htmlspecialchars($row['paid_at'] ?? '') ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($row['client_name'] ?? $row['client_cui'] ?? '') ?></td>
                                <td class="px-3 py-2 text-right font-medium text-slate-900">
                                    <?= number_format((float) ($row['allocated_amount'] ?? 0), 2, '.', ' ') ?>
                                </td>
                                <td class="px-3 py-2 text-slate-500 text-xs"><?= htmlspecialchars($row['notes'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($paymentsIn)): ?>
                    <tfoot class="border-t border-slate-200 bg-slate-50">
                        <tr>
                            <td colspan="2" class="px-3 py-2 text-right text-sm font-semibold text-slate-700">Total</td>
                            <td class="px-3 py-2 text-right text-sm font-semibold text-slate-900">
                                <?= number_format($totalIncasat, 2, '.', ' ') ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Plati catre furnizor -->
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="text-base font-semibold text-slate-900">Plati catre furnizor</h2>
        <div class="mt-3 overflow-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2">Data</th>
                        <th class="px-3 py-2 text-right">Suma</th>
                        <th class="px-3 py-2">Observatii</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paymentsOut)): ?>
                        <tr>
                            <td colspan="3" class="px-3 py-4 text-center text-slate-500">
                                Nu exista plati pentru filtrul selectat.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($paymentsOut as $row): ?>
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2"><?= htmlspecialchars($row['paid_at'] ?? '') ?></td>
                                <td class="px-3 py-2 text-right font-medium text-slate-900">
                                    <?= number_format((float) ($row['amount'] ?? 0), 2, '.', ' ') ?>
                                </td>
                                <td class="px-3 py-2 text-slate-500 text-xs"><?= htmlspecialchars($row['notes'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($paymentsOut)): ?>
                    <tfoot class="border-t border-slate-200 bg-slate-50">
                        <tr>
                            <td class="px-3 py-2 text-right text-sm font-semibold text-slate-700">Total</td>
                            <td class="px-3 py-2 text-right text-sm font-semibold text-slate-900">
                                <?= number_format($totalPlatit, 2, '.', ' ') ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

</div>

<?php endif; ?>
