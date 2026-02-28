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

<div class="mt-4 grid gap-3" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
    <div class="rounded border border-slate-300 p-3 text-sm">
        <div class="text-slate-600">Total facturat furnizor</div>
        <div class="mt-1 font-semibold text-slate-900"><?= number_format($totalFurnizor, 2, '.', ' ') ?> RON</div>
        <div class="mt-0.5 text-xs text-slate-500"><?= count($invoices) ?> facturi FGO</div>
    </div>
    <div class="rounded border border-slate-300 p-3 text-sm">
        <div class="text-slate-600">Total incasat clienti</div>
        <div class="mt-1 font-semibold text-slate-900"><?= number_format($totalIncasat, 2, '.', ' ') ?> RON</div>
        <div class="mt-0.5 text-xs text-slate-500">
            Neincasat: <?= number_format(max(0.0, $totalFurnizor - $totalIncasat), 2, '.', ' ') ?> RON
        </div>
    </div>
    <div class="rounded border border-slate-300 p-3 text-sm">
        <div class="text-slate-600">Total platit furnizor</div>
        <div class="mt-1 font-semibold text-slate-900"><?= number_format($totalPlatit, 2, '.', ' ') ?> RON</div>
        <div class="mt-0.5 text-xs text-slate-500">
            Neplatit: <?= number_format(max(0.0, $totalFurnizor - $totalPlatit), 2, '.', ' ') ?> RON
        </div>
    </div>
</div>

<!-- Facturi FGO -->
<div class="mt-6">
    <h2 class="text-sm font-semibold text-slate-900">Facturi emise catre clienti</h2>
    <table class="mt-2 w-full text-left text-sm">
        <thead class="border-b border-slate-400 bg-slate-100 text-slate-700">
            <tr>
                <th class="px-2 py-1">Nr. FGO</th>
                <th class="px-2 py-1">Data FGO</th>
                <th class="px-2 py-1">Nr. fact. furnizor</th>
                <th class="px-2 py-1">Client</th>
                <th class="px-2 py-1 text-right">Total furnizor</th>
                <th class="px-2 py-1 text-right">Comision</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="6" class="px-2 py-2 text-center text-slate-500">Nu exista facturi.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $inv): ?>
                    <tr class="border-b border-slate-200">
                        <td class="px-2 py-1 font-medium">
                            <?= htmlspecialchars(trim(($inv['fgo_series'] ?? '') . ' ' . ($inv['fgo_number'] ?? ''))) ?>
                        </td>
                        <td class="px-2 py-1"><?= htmlspecialchars((string) ($inv['fgo_date'] ?? '')) ?></td>
                        <td class="px-2 py-1"><?= htmlspecialchars((string) ($inv['invoice_number'] ?? '')) ?></td>
                        <td class="px-2 py-1"><?= htmlspecialchars((string) ($inv['client_name'] ?? '')) ?></td>
                        <td class="px-2 py-1 text-right"><?= number_format((float) ($inv['total_with_vat'] ?? 0), 2, '.', ' ') ?></td>
                        <td class="px-2 py-1 text-right">
                            <?php
                            $cp = $inv['commission_percent'] !== null ? (float) $inv['commission_percent'] : null;
                            echo ($cp !== null && $cp >= 0.1) ? number_format($cp, 2, '.', '') . '%' : '—';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($invoices)): ?>
            <tfoot class="border-t border-slate-400 bg-slate-100">
                <tr>
                    <td colspan="4" class="px-2 py-1 text-right font-semibold">Total</td>
                    <td class="px-2 py-1 text-right font-semibold"><?= number_format($totalFurnizor, 2, '.', ' ') ?></td>
                    <td></td>
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
                        <td class="px-2 py-1 text-xs text-slate-500"><?= htmlspecialchars($row['notes'] ?? '') ?></td>
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
