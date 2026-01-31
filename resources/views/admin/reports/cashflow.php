<?php $title = 'Raport cashflow'; ?>

<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Raport cashflow</h1>
        <p class="mt-1 text-sm text-slate-500">Incasari si plati pe luna selectata.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a
            href="<?= App\Support\Url::to('admin/rapoarte/cashflow/export?month=' . urlencode($month)) ?>"
            class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow hover:bg-slate-50"
        >
            Export CSV
        </a>
        <a
            href="<?= App\Support\Url::to('admin/rapoarte/cashflow/pdf?month=' . urlencode($month)) ?>"
            target="_blank"
            class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Export PDF
        </a>
    </div>
</div>

<form method="GET" action="<?= App\Support\Url::to('admin/rapoarte/cashflow') ?>" class="mt-4 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-sm font-medium text-slate-700" for="month">Luna</label>
        <input
            type="month"
            id="month"
            name="month"
            value="<?= htmlspecialchars($month) ?>"
            class="mt-1 rounded border border-slate-300 px-3 py-2 text-sm"
        >
    </div>
    <button
        type="submit"
        class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
    >
        Afiseaza
    </button>
    <div class="text-xs text-slate-500">
        Perioada: <?= htmlspecialchars($start) ?> - <?= htmlspecialchars($end) ?>
    </div>
</form>

<div class="mt-6 grid gap-4 md:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Incasari</div>
        <div class="mt-1 text-lg font-semibold text-emerald-700">
            <?= number_format($totalIn, 2, '.', ' ') ?> RON
        </div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Plati</div>
        <div class="mt-1 text-lg font-semibold text-rose-700">
            <?= number_format($totalOut, 2, '.', ' ') ?> RON
        </div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Net</div>
        <div class="mt-1 text-lg font-semibold text-slate-900">
            <?= number_format($net, 2, '.', ' ') ?> RON
        </div>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="text-base font-semibold text-slate-900">Incasari clienti</h2>
        <div class="mt-3 overflow-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2">Data</th>
                        <th class="px-3 py-2">Client</th>
                        <th class="px-3 py-2">CUI</th>
                        <th class="px-3 py-2 text-right">Suma</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paymentsIn)): ?>
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-sm text-slate-500">
                                Nu exista incasari in aceasta luna.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($paymentsIn as $row): ?>
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2"><?= htmlspecialchars($row['paid_at']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($row['partner_name'] ?? '') ?></td>
                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($row['partner_cui'] ?? '') ?></td>
                                <td class="px-3 py-2 text-right font-medium text-slate-900">
                                    <?= number_format((float) ($row['amount'] ?? 0), 2, '.', ' ') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($paymentsIn)): ?>
                    <tfoot class="border-t border-slate-200 bg-slate-50">
                        <tr>
                            <td colspan="3" class="px-3 py-2 text-right text-sm font-semibold text-slate-700">Total</td>
                            <td class="px-3 py-2 text-right text-sm font-semibold text-slate-900">
                                <?= number_format($totalIn, 2, '.', ' ') ?>
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="text-base font-semibold text-slate-900">Plati furnizori</h2>
        <div class="mt-3 overflow-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2">Data</th>
                        <th class="px-3 py-2">Furnizor</th>
                        <th class="px-3 py-2">CUI</th>
                        <th class="px-3 py-2 text-right">Suma</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paymentsOut)): ?>
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-sm text-slate-500">
                                Nu exista plati in aceasta luna.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($paymentsOut as $row): ?>
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2"><?= htmlspecialchars($row['paid_at']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($row['partner_name'] ?? '') ?></td>
                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($row['partner_cui'] ?? '') ?></td>
                                <td class="px-3 py-2 text-right font-medium text-slate-900">
                                    <?= number_format((float) ($row['amount'] ?? 0), 2, '.', ' ') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($paymentsOut)): ?>
                    <tfoot class="border-t border-slate-200 bg-slate-50">
                        <tr>
                            <td colspan="3" class="px-3 py-2 text-right text-sm font-semibold text-slate-700">Total</td>
                            <td class="px-3 py-2 text-right text-sm font-semibold text-slate-900">
                                <?= number_format($totalOut, 2, '.', ' ') ?>
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
