<?php $title = 'Incasari clienti'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Incasari clienti</h1>
        <p class="mt-1 text-sm text-slate-500">Evidenta incasarilor si alocarilor pe facturi.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a
            href="<?= App\Support\Url::to('admin/incasari/istoric') ?>"
            class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Istoric
        </a>
        <a
            href="<?= App\Support\Url::to('admin/incasari/extras') ?>"
            class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Extras bancar
        </a>
        <a
            href="<?= App\Support\Url::to('admin/incasari/import-extras') ?>"
            class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Import extras
        </a>
        <a
            href="<?= App\Support\Url::to('admin/incasari/adauga') ?>"
            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Adauga incasare
        </a>
    </div>
</div>

<?php if (!empty($unprocessedBankCount) && (int) $unprocessedBankCount > 0): ?>
<div class="mt-4 flex items-center gap-3 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
    </svg>
    <span>
        Exista <strong><?= (int) $unprocessedBankCount ?></strong>
        <?= (int) $unprocessedBankCount === 1 ? 'tranzactie bancara neprocesata' : 'tranzactii bancare neprocesate' ?>.
        <a
            href="<?= App\Support\Url::to('admin/incasari/extras') ?>"
            class="ml-1 font-semibold underline hover:text-amber-900"
        >
            Vezi extrasul bancar
        </a>
    </span>
</div>
<?php endif; ?>

<div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-2">Data</th>
                <th class="px-4 py-2">Client</th>
                <th class="px-4 py-2">Suma</th>
                <th class="px-4 py-2">Alocat</th>
                <th class="px-4 py-2">Observatii</th>
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
                        <td class="px-4 py-2"><?= htmlspecialchars($payment['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
