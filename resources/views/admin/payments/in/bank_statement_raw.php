<?php $title = 'Extras bancar — brut'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Extras bancar <span class="ml-1 text-base font-normal text-slate-400">brut</span></h1>
        <p class="mt-1 text-sm text-slate-500">Toate tranzactiile importate, in ordine cronologica.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/incasari/extras') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi la extras
    </a>
</div>

<?php if (empty($rows)): ?>
    <div class="mt-6 rounded border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500">
        Nu exista tranzactii importate.
        <a href="<?= App\Support\Url::to('admin/incasari/import-extras') ?>" class="ml-1 font-semibold text-blue-600 underline hover:text-blue-800">Importa un extras CSV</a>.
    </div>
<?php else: ?>

<div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white">
    <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 font-semibold text-slate-600 border-b border-slate-200">
            <tr>
                <th class="px-3 py-2 whitespace-nowrap">Data</th>
                <th class="px-3 py-2 whitespace-nowrap">Suma</th>
                <th class="px-3 py-2 whitespace-nowrap">Valuta</th>
                <th class="px-3 py-2 whitespace-nowrap">Sold</th>
                <th class="px-3 py-2 whitespace-nowrap">Tip tranzactie</th>
                <th class="px-3 py-2">Contraparte</th>
                <th class="px-3 py-2">Cont contraparte</th>
                <th class="px-3 py-2">Banca contraparte</th>
                <th class="px-3 py-2 max-w-xs">Detalii</th>
                <th class="px-3 py-2 whitespace-nowrap">CUI contraparte</th>
                <th class="px-3 py-2 whitespace-nowrap">Cont propriu</th>
                <th class="px-3 py-2 whitespace-nowrap">Importat la</th>
                <th class="px-3 py-2 whitespace-nowrap">Legatura</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    $amount = (float) $row['amount'];
                    $isPositive = $amount > 0;
                    $dateFormatted = '';
                    if (!empty($row['processed_at'])) {
                        $ts = strtotime($row['processed_at']);
                        $dateFormatted = $ts ? date('d.m.Y', $ts) : $row['processed_at'];
                    }
                    $importedFormatted = '';
                    if (!empty($row['imported_at'])) {
                        $ts = strtotime($row['imported_at']);
                        $importedFormatted = $ts ? date('d.m.Y H:i', $ts) : $row['imported_at'];
                    }
                    $paymentInId  = !empty($row['payment_in_id']) ? (int) $row['payment_in_id'] : null;
                    $isIgnored    = (int) ($row['ignored'] ?? 0) === 1;
                    $paymentOutIds = array_values(array_filter(
                        array_map('intval', explode(',', (string) ($row['payment_out_ids'] ?? '')))
                    ));
                ?>
                <tr class="border-t border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-1.5 whitespace-nowrap text-slate-600"><?= htmlspecialchars($dateFormatted) ?></td>
                    <td class="px-3 py-1.5 whitespace-nowrap font-semibold <?= $isPositive ? 'text-green-700' : 'text-red-700' ?>">
                        <?= $isPositive ? '+' : '' ?><?= number_format($amount, 2, '.', ' ') ?>
                    </td>
                    <td class="px-3 py-1.5 whitespace-nowrap text-slate-500"><?= htmlspecialchars($row['currency'] ?? 'RON') ?></td>
                    <td class="px-3 py-1.5 whitespace-nowrap text-slate-500">
                        <?= $row['balance'] !== null ? number_format((float) $row['balance'], 2, '.', ' ') : '—' ?>
                    </td>
                    <td class="px-3 py-1.5 whitespace-nowrap text-slate-500"><?= htmlspecialchars($row['transaction_type'] ?? '') ?></td>
                    <td class="px-3 py-1.5 text-slate-700"><?= htmlspecialchars($row['counterpart_name'] ?? '') ?></td>
                    <td class="px-3 py-1.5 whitespace-nowrap text-slate-400 font-mono text-[10px]"><?= htmlspecialchars($row['counterpart_account'] ?? '') ?></td>
                    <td class="px-3 py-1.5 text-slate-500"><?= htmlspecialchars($row['counterpart_bank'] ?? '') ?></td>
                    <td class="px-3 py-1.5 max-w-xs">
                        <span class="block truncate text-slate-500" title="<?= htmlspecialchars($row['details'] ?? '') ?>">
                            <?= htmlspecialchars(mb_substr($row['details'] ?? '', 0, 80, 'UTF-8')) ?><?= mb_strlen($row['details'] ?? '', 'UTF-8') > 80 ? '…' : '' ?>
                        </span>
                    </td>
                    <td class="px-3 py-1.5 whitespace-nowrap text-slate-400"><?= htmlspecialchars($row['counterpart_cui'] ?? '') ?></td>
                    <td class="px-3 py-1.5 whitespace-nowrap text-slate-400 font-mono text-[10px]"><?= htmlspecialchars($row['account_no'] ?? '') ?></td>
                    <td class="px-3 py-1.5 whitespace-nowrap text-slate-400"><?= htmlspecialchars($importedFormatted) ?></td>
                    <td class="px-3 py-1.5 whitespace-nowrap">
                        <?php if (!empty($paymentOutIds)): ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($paymentOutIds as $pid): ?>
                                    <a
                                        href="<?= App\Support\Url::to('admin/plati/print?payment_id=' . $pid) ?>"
                                        class="inline-flex items-center gap-1 rounded bg-orange-100 px-2 py-0.5 text-[10px] font-semibold text-orange-700 hover:bg-orange-200"
                                        title="Plata #<?= $pid ?>"
                                        target="_blank"
                                    >
                                        Plata #<?= $pid ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($paymentInId): ?>
                            <a
                                href="<?= App\Support\Url::to('admin/incasari/istoric?payment_id=' . $paymentInId) ?>"
                                class="inline-flex items-center gap-1 rounded bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700 hover:bg-green-200"
                                title="Incasare #<?= $paymentInId ?>"
                            >
                                Incasare #<?= $paymentInId ?>
                            </a>
                        <?php elseif ($isIgnored): ?>
                            <span class="text-[10px] text-slate-400">Ignorat</span>
                        <?php else: ?>
                            <span class="text-[10px] text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="mt-2 text-xs text-slate-400"><?= count($rows) ?> tranzactii totale.</p>

<?php endif; ?>
