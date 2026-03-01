<?php $title = 'Fisa furnizor'; ?>

<div class="text-center">
    <h1 class="text-xl font-semibold text-slate-900">Fisa furnizor</h1>
    <div class="mt-1 text-base font-medium text-slate-800"><?= htmlspecialchars($supplierName ?: $supplierCui) ?></div>
    <?php if ($dateStart || $dateEnd): ?>
        <div class="mt-1 text-sm text-slate-600">
            Perioada:
            <?= $dateStart ? htmlspecialchars($dateStart) : '—' ?>
            &ndash;
            <?= $dateEnd ? htmlspecialchars($dateEnd) : '—' ?>
        </div>
    <?php endif; ?>
</div>

<?php
$neincasat = max(0.0, $totalFurnizor - $totalIncasat);
$neplatit  = max(0.0, $totalCuvenitFurnizorDinFacturi - $totalPlatit);
?>

<div class="mt-4 grid gap-3" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
    <div class="rounded border border-slate-300 p-3 text-sm">
        <div class="text-slate-600">Total facturat furnizor</div>
        <div class="mt-1 font-semibold text-slate-900"><?= number_format($totalFurnizor, 2, '.', ' ') ?> RON</div>
        <div class="mt-0.5 text-xs text-slate-500"><?= count($invoices) ?> facturi FGO</div>
    </div>
    <div class="rounded border border-slate-300 p-3 text-sm">
        <div class="text-slate-600">Total incasat clienti</div>
        <div class="mt-1 font-semibold text-slate-900"><?= number_format($totalIncasat, 2, '.', ' ') ?> RON</div>
    </div>
    <div class="rounded border border-slate-300 p-3 text-sm">
        <div class="text-slate-600">Total platit furnizor</div>
        <div class="mt-1 font-semibold text-slate-900"><?= number_format($totalPlatit, 2, '.', ' ') ?> RON</div>
    </div>
</div>

<?php $restDePlatitDinIncasat = max(0.0, $totalCuvenitFurnizorDinIncasat - $totalPlatit); ?>

<div class="mt-3 grid gap-3" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
    <div class="rounded border border-slate-400 bg-slate-50 p-3 text-center">
        <div class="text-xs font-medium text-slate-600">De incasat de la clienti</div>
        <div class="mt-1 text-base font-semibold text-slate-900"><?= number_format($neincasat, 2, '.', ' ') ?> RON</div>
        <div class="mt-0.5 text-xs text-slate-500">
            din <?= number_format($totalFurnizor, 2, '.', ' ') ?> RON facturat
        </div>
    </div>
    <div class="rounded border border-slate-400 bg-slate-50 p-3 text-center">
        <div class="text-xs font-medium text-slate-600">De platit catre furnizor</div>
        <div class="mt-1 text-base font-semibold text-slate-900"><?= number_format($neplatit, 2, '.', ' ') ?> RON</div>
        <div class="mt-0.5 text-xs text-slate-500">
            din <?= number_format($totalCuvenitFurnizorDinFacturi, 2, '.', ' ') ?> RON cuvenit furnizorului
        </div>
    </div>
    <div class="rounded border border-slate-400 bg-slate-50 p-3 text-center">
        <div class="text-xs font-medium text-slate-600">De platit din incasat</div>
        <div class="mt-1 text-base font-semibold text-slate-900"><?= number_format($restDePlatitDinIncasat, 2, '.', ' ') ?> RON</div>
        <div class="mt-0.5 text-xs text-slate-500">
            din <?= number_format($totalCuvenitFurnizorDinIncasat, 2, '.', ' ') ?> RON cuvenit furnizorului din incasat
        </div>
    </div>
</div>

<!-- Facturi FGO -->
<?php
$fileBaseUrl = App\Support\Url::to('admin/facturi/fisier');
$totalIncasatFacturi       = 0.0;
$totalRestDeIncasatFacturi = 0.0;
$totalRestDePlataFacturi   = 0.0;
foreach ($invoices as $inv) {
    $totalIncasatFacturi       += (float) ($inv['incasat'] ?? 0);
    $totalRestDeIncasatFacturi += (float) ($inv['rest_de_incasat'] ?? 0);
    $totalRestDePlataFacturi   += (float) ($inv['rest_de_plata'] ?? 0);
}
?>
<div class="mt-6">
    <h2 class="text-sm font-semibold text-slate-900">Facturi emise catre clienti</h2>
    <table class="mt-2 w-full text-left text-sm">
        <thead class="border-b border-slate-400 bg-slate-100 text-slate-700">
            <tr>
                <th class="px-2 py-1">Factura furnizor</th>
                <th class="px-2 py-1">Factura client</th>
                <th class="px-2 py-1">Client</th>
                <th class="px-2 py-1 text-right">Total furnizor</th>
                <th class="px-2 py-1 text-right">Comision</th>
                <th class="px-2 py-1 text-right">Incasat</th>
                <th class="px-2 py-1 text-right">Rest de incasat</th>
                <th class="px-2 py-1 text-right">Rest de plata</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="8" class="px-2 py-2 text-center text-slate-500">Nu exista facturi.</td>
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
                        $restDeIncasat = (float) ($inv['rest_de_incasat'] ?? 0);
                        $restDePlata   = (float) ($inv['rest_de_plata'] ?? 0);
                    ?>
                    <tr class="border-b border-slate-200">
                        <td class="px-2 py-1">
                            <?php if ($supplierLabel !== '' && $xmlPath !== ''): ?>
                                <a href="<?= htmlspecialchars($fileUrl) ?>" class="font-medium text-blue-700">
                                    <?= $supplierLabel ?>
                                </a>
                            <?php else: ?>
                                <span class="font-medium"><?= $supplierLabel !== '' ? $supplierLabel : '—' ?></span>
                            <?php endif; ?>
                            <?php if ($supplierDate !== ''): ?>
                                <div class="text-xs text-slate-400"><?= $supplierDate ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-1">
                            <?php if ($fgoLabel !== '' && $fgoLink !== ''): ?>
                                <a href="<?= htmlspecialchars($fgoLink) ?>" class="font-medium text-blue-700">
                                    <?= $fgoLabel ?>
                                </a>
                            <?php else: ?>
                                <span class="font-medium"><?= $fgoLabel !== '' ? $fgoLabel : '—' ?></span>
                            <?php endif; ?>
                            <?php if ($fgoDate !== ''): ?>
                                <div class="text-xs text-slate-400"><?= $fgoDate ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-1"><?= htmlspecialchars((string) ($inv['client_name'] ?? '')) ?></td>
                        <td class="px-2 py-1 text-right"><?= number_format((float) ($inv['total_with_vat'] ?? 0), 2, '.', ' ') ?></td>
                        <td class="px-2 py-1 text-right text-slate-600">
                            <?= ($cp !== null && $cp >= 0.1) ? number_format($cp, 2, '.', '') . '%' : '—' ?>
                        </td>
                        <td class="px-2 py-1 text-right"><?= number_format((float) ($inv['incasat'] ?? 0), 2, '.', ' ') ?></td>
                        <td class="px-2 py-1 text-right"><?= number_format($restDeIncasat, 2, '.', ' ') ?></td>
                        <td class="px-2 py-1 text-right"><?= number_format($restDePlata, 2, '.', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($invoices)): ?>
            <tfoot class="border-t border-slate-400 bg-slate-100">
                <tr>
                    <td colspan="3" class="px-2 py-1 text-right font-semibold">Total</td>
                    <td class="px-2 py-1 text-right font-semibold"><?= number_format($totalFurnizor, 2, '.', ' ') ?></td>
                    <td></td>
                    <td class="px-2 py-1 text-right font-semibold"><?= number_format($totalIncasatFacturi, 2, '.', ' ') ?></td>
                    <td class="px-2 py-1 text-right font-semibold"><?= number_format($totalRestDeIncasatFacturi, 2, '.', ' ') ?></td>
                    <td class="px-2 py-1 text-right font-semibold"><?= number_format($totalRestDePlataFacturi, 2, '.', ' ') ?></td>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>

<!-- Incasari -->
<div class="mt-6">
    <h2 class="text-sm font-semibold text-slate-900">Incasari de la clienti</h2>
    <table class="mt-2 w-full text-left text-sm">
        <thead class="border-b border-slate-400 bg-slate-100 text-slate-700">
            <tr>
                <th class="px-2 py-1">Data</th>
                <th class="px-2 py-1">Client</th>
                <th class="px-2 py-1 text-right">Suma</th>
                <th class="px-2 py-1">Observatii</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($paymentsIn)): ?>
                <tr>
                    <td colspan="4" class="px-2 py-2 text-center text-slate-500">Nu exista incasari.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($paymentsIn as $row): ?>
                    <tr class="border-b border-slate-200">
                        <td class="px-2 py-1"><?= htmlspecialchars($row['paid_at'] ?? '') ?></td>
                        <td class="px-2 py-1"><?= htmlspecialchars($row['client_name'] ?? $row['client_cui'] ?? '') ?></td>
                        <td class="px-2 py-1 text-right"><?= number_format((float) ($row['allocated_amount'] ?? 0), 2, '.', ' ') ?></td>
                        <td class="px-2 py-1 text-xs text-slate-500"><?= htmlspecialchars($row['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($paymentsIn)): ?>
            <tfoot class="border-t border-slate-400 bg-slate-100">
                <tr>
                    <td colspan="2" class="px-2 py-1 text-right font-semibold">Total</td>
                    <td class="px-2 py-1 text-right font-semibold"><?= number_format($totalIncasat, 2, '.', ' ') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>

<!-- Plati furnizor -->
<div class="mt-6">
    <h2 class="text-sm font-semibold text-slate-900">Plati catre furnizor</h2>
    <table class="mt-2 w-full text-left text-sm">
        <thead class="border-b border-slate-400 bg-slate-100 text-slate-700">
            <tr>
                <th class="px-2 py-1">Data</th>
                <th class="px-2 py-1 text-right">Suma</th>
                <th class="px-2 py-1">Observatii</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($paymentsOut)): ?>
                <tr>
                    <td colspan="3" class="px-2 py-2 text-center text-slate-500">Nu exista plati.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($paymentsOut as $row): ?>
                    <tr class="border-b border-slate-200">
                        <td class="px-2 py-1"><?= htmlspecialchars($row['paid_at'] ?? '') ?></td>
                        <td class="px-2 py-1 text-right"><?= number_format((float) ($row['amount'] ?? 0), 2, '.', ' ') ?></td>
                        <td class="px-2 py-1 text-xs text-slate-500">
                            <?php $opId = (int) ($row['id'] ?? 0); ?>
                            <?php if ($opId > 0): ?>
                                Plata cu OP #<?= $opId ?>
                            <?php endif; ?>
                            <?php if (!empty($row['notes'])): ?>
                                <?= $opId > 0 ? ' — ' : '' ?><?= htmlspecialchars($row['notes']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($paymentsOut)): ?>
            <tfoot class="border-t border-slate-400 bg-slate-100">
                <tr>
                    <td class="px-2 py-1 text-right font-semibold">Total</td>
                    <td class="px-2 py-1 text-right font-semibold"><?= number_format($totalPlatit, 2, '.', ' ') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>

<script>
    window.addEventListener('load', () => {
        window.print();
    });
</script>
