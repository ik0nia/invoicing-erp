<?php $title = 'Istoric incasari'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Istoric incasari</h1>
        <p class="mt-1 text-sm text-slate-500">Filtreaza incasarile dupa data.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/incasari') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi
    </a>
</div>

<form method="GET" action="<?= App\Support\Url::to('admin/incasari/istoric') ?>" class="mt-4 grid gap-4 md:grid-cols-3">
    <div>
        <label class="block text-sm font-medium text-slate-700" for="date_from">De la</label>
        <input
            id="date_from"
            name="date_from"
            type="date"
            value="<?= htmlspecialchars($dateFrom ?? '') ?>"
            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        >
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700" for="date_to">Pana la</label>
        <input
            id="date_to"
            name="date_to"
            type="date"
            value="<?= htmlspecialchars($dateTo ?? '') ?>"
            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        >
    </div>
    <div class="flex items-end gap-2">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Filtreaza
        </button>
        <a
            href="<?= App\Support\Url::to('admin/incasari/istoric') ?>"
            class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Reseteaza
        </a>
    </div>
</form>

<div class="mt-6 space-y-4">
    <?php if (empty($payments)): ?>
        <div class="rounded border border-slate-200 bg-white p-6 text-sm text-slate-500">
            Nu exista incasari in acest interval.
        </div>
    <?php else: ?>
        <?php foreach ($payments as $payment): ?>
            <div class="rounded border border-slate-200 bg-white p-4 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="font-semibold text-slate-900">
                        <?= htmlspecialchars($payment['client_name'] ?? $payment['client_cui']) ?>
                    </div>
                    <div class="text-xs text-slate-600">
                        <?= htmlspecialchars($payment['paid_at']) ?> · <?= number_format((float) $payment['amount'], 2, '.', ' ') ?> RON
                    </div>
                </div>
                <?php if (!empty($payment['notes'])): ?>
                    <div class="mt-2 text-xs text-slate-600"><?= htmlspecialchars($payment['notes']) ?></div>
                <?php endif; ?>

                <?php $rows = $allocations[$payment['id']] ?? []; ?>
                <?php if (!empty($rows)): ?>
                    <div class="mt-3 overflow-x-auto rounded border border-slate-100">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-3 py-2">Factura</th>
                                    <th class="px-3 py-2">Suma alocata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr class="border-t border-slate-100">
                                        <td class="px-3 py-2"><?= htmlspecialchars($row['invoice_number'] ?? '') ?></td>
                                        <td class="px-3 py-2"><?= number_format((float) $row['amount'], 2, '.', ' ') ?> RON</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
