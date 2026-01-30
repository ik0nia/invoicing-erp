<?php $title = 'Plati furnizori'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Plati furnizori</h1>
        <p class="mt-1 text-sm text-slate-500">Furnizori cu sume disponibile de plata.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a
            href="<?= App\Support\Url::to('admin/plati/istoric') ?>"
            class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Istoric
        </a>
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
                <th class="px-4 py-2">Furnizor</th>
                <th class="px-4 py-2">Disponibil</th>
                <th class="px-4 py-2">Platit</th>
                <th class="px-4 py-2">De platit</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($suppliers)): ?>
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">Nu exista furnizori cu plati disponibile.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($suppliers as $supplier): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">
                            <?= htmlspecialchars($supplier['supplier_name'] ?? $supplier['supplier_cui']) ?>
                        </td>
                        <td class="px-4 py-2"><?= number_format((float) $supplier['collected_net'], 2, '.', ' ') ?> RON</td>
                        <td class="px-4 py-2"><?= number_format((float) $supplier['paid'], 2, '.', ' ') ?> RON</td>
                        <td class="px-4 py-2 font-semibold text-slate-900"><?= number_format((float) $supplier['due'], 2, '.', ' ') ?> RON</td>
                        <td class="px-4 py-2 text-right">
                            <a
                                href="<?= App\Support\Url::to('admin/plati/adauga?supplier_cui=' . urlencode((string) $supplier['supplier_cui'])) ?>"
                                class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Adauga plata
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
