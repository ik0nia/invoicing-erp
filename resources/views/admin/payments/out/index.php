<?php $title = 'Plati furnizori'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Plati furnizori</h1>
        <p class="mt-1 text-sm text-slate-500">Evidenta platilor catre furnizori.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <form method="POST" action="<?= App\Support\Url::to('admin/plati/email-azi') ?>">
            <?= App\Support\Csrf::input() ?>
            <button class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Trimite emailuri azi
            </button>
        </form>
        <a
            href="<?= App\Support\Url::to('admin/plati/adauga') ?>"
            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Adauga plata
        </a>
    </div>
</div>

<div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-2">Data</th>
                <th class="px-4 py-2">Furnizor</th>
                <th class="px-4 py-2">Suma</th>
                <th class="px-4 py-2">Alocat</th>
                <th class="px-4 py-2">Metoda</th>
                <th class="px-4 py-2">Referinta</th>
                <th class="px-4 py-2">Email</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-slate-500">Nu exista plati.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2"><?= htmlspecialchars($payment['paid_at'] ?? '') ?></td>
                        <td class="px-4 py-2">
                            <?= htmlspecialchars($payment['supplier_name'] ?? $payment['supplier_cui']) ?>
                        </td>
                        <td class="px-4 py-2"><?= number_format((float) $payment['amount'], 2, '.', ' ') ?> RON</td>
                        <td class="px-4 py-2"><?= number_format((float) $payment['allocated'], 2, '.', ' ') ?> RON</td>
                        <td class="px-4 py-2"><?= htmlspecialchars($payment['method'] ?? '') ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($payment['reference'] ?? '') ?></td>
                        <td class="px-4 py-2 text-xs">
                            <?= htmlspecialchars($payment['email_status'] ?? '') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
