<?php $title = 'Incasari clienti'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Incasari clienti</h1>
        <p class="mt-1 text-sm text-slate-500">Evidenta incasarilor si alocarilor pe facturi.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/incasari/adauga') ?>"
        class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
    >
        Adauga incasare
    </a>
</div>

<div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-2">Data</th>
                <th class="px-4 py-2">Client</th>
                <th class="px-4 py-2">Suma</th>
                <th class="px-4 py-2">Alocat</th>
                <th class="px-4 py-2">Metoda</th>
                <th class="px-4 py-2">Referinta</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-slate-500">Nu exista incasari.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2"><?= htmlspecialchars($payment['paid_at'] ?? '') ?></td>
                        <td class="px-4 py-2">
                            <?= htmlspecialchars($payment['client_name'] ?? $payment['client_cui']) ?>
                        </td>
                        <td class="px-4 py-2"><?= number_format((float) $payment['amount'], 2, '.', ' ') ?> RON</td>
                        <td class="px-4 py-2"><?= number_format((float) $payment['allocated'], 2, '.', ' ') ?> RON</td>
                        <td class="px-4 py-2"><?= htmlspecialchars($payment['method'] ?? '') ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($payment['reference'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
