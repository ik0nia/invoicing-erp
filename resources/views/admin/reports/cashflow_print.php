<?php $title = 'Raport cashflow'; ?>

<div class="text-center">
    <h1 class="text-xl font-semibold text-slate-900">Raport cashflow</h1>
    <div class="mt-1 text-sm text-slate-600">
        Perioada: <?= htmlspecialchars($start) ?> - <?= htmlspecialchars($end) ?>
    </div>
</div>

<div class="mt-4 grid gap-4" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
    <div class="rounded border border-slate-300 p-3 text-sm">
        <div class="text-slate-600">Incasari</div>
        <div class="mt-1 font-semibold text-slate-900"><?= number_format($totalIn, 2, '.', ' ') ?> RON</div>
    </div>
    <div class="rounded border border-slate-300 p-3 text-sm">
        <div class="text-slate-600">Plati</div>
        <div class="mt-1 font-semibold text-slate-900"><?= number_format($totalOut, 2, '.', ' ') ?> RON</div>
    </div>
    <div class="rounded border border-slate-300 p-3 text-sm">
        <div class="text-slate-600">Net</div>
        <div class="mt-1 font-semibold text-slate-900"><?= number_format($net, 2, '.', ' ') ?> RON</div>
    </div>
</div>

<div class="mt-6">
    <h2 class="text-sm font-semibold text-slate-900">Incasari clienti</h2>
    <table class="mt-2 w-full text-left text-sm">
        <thead class="border-b border-slate-400 bg-slate-100 text-slate-700">
            <tr>
                <th class="px-2 py-1">Data</th>
                <th class="px-2 py-1">Client</th>
                <th class="px-2 py-1">CUI</th>
                <th class="px-2 py-1 text-right">Suma</th>
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
                        <td class="px-2 py-1"><?= htmlspecialchars($row['paid_at']) ?></td>
                        <td class="px-2 py-1"><?= htmlspecialchars($row['partner_name'] ?? '') ?></td>
                        <td class="px-2 py-1"><?= htmlspecialchars($row['partner_cui'] ?? '') ?></td>
                        <td class="px-2 py-1 text-right"><?= number_format((float) ($row['amount'] ?? 0), 2, '.', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($paymentsIn)): ?>
            <tfoot class="border-t border-slate-400 bg-slate-100">
                <tr>
                    <td colspan="3" class="px-2 py-1 text-right font-semibold">Total</td>
                    <td class="px-2 py-1 text-right font-semibold"><?= number_format($totalIn, 2, '.', ' ') ?></td>
                </tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>

<div class="mt-6">
    <h2 class="text-sm font-semibold text-slate-900">Plati furnizori</h2>
    <table class="mt-2 w-full text-left text-sm">
        <thead class="border-b border-slate-400 bg-slate-100 text-slate-700">
            <tr>
                <th class="px-2 py-1">Data</th>
                <th class="px-2 py-1">Furnizor</th>
                <th class="px-2 py-1">CUI</th>
                <th class="px-2 py-1 text-right">Suma</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($paymentsOut)): ?>
                <tr>
                    <td colspan="4" class="px-2 py-2 text-center text-slate-500">Nu exista plati.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($paymentsOut as $row): ?>
                    <tr class="border-b border-slate-200">
                        <td class="px-2 py-1"><?= htmlspecialchars($row['paid_at']) ?></td>
                        <td class="px-2 py-1"><?= htmlspecialchars($row['partner_name'] ?? '') ?></td>
                        <td class="px-2 py-1"><?= htmlspecialchars($row['partner_cui'] ?? '') ?></td>
                        <td class="px-2 py-1 text-right"><?= number_format((float) ($row['amount'] ?? 0), 2, '.', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($paymentsOut)): ?>
            <tfoot class="border-t border-slate-400 bg-slate-100">
                <tr>
                    <td colspan="3" class="px-2 py-1 text-right font-semibold">Total</td>
                    <td class="px-2 py-1 text-right font-semibold"><?= number_format($totalOut, 2, '.', ' ') ?></td>
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
