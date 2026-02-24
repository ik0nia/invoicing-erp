<?php $title = 'Import extras bancar'; ?>

<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Import extras bancar</h1>
        <p class="mt-1 text-sm text-slate-500">
            Incarca un CSV exportat din ING Business. Sistemul detecteaza incasarile noi si le potriveste cu clientii existenti.
        </p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/incasari') ?>"
        class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
    >
        Inapoi la incasari
    </a>
</div>

<div class="mt-6 rounded border border-slate-200 bg-white p-6">
    <form method="POST" action="<?= App\Support\Url::to('admin/incasari/import-extras') ?>" enctype="multipart/form-data" class="flex flex-wrap items-end gap-4">
        <?= App\Support\Csrf::input() ?>
        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Fisier CSV (ING Business)</label>
            <input
                type="file"
                name="csv_file"
                accept=".csv,text/csv"
                required
                class="block cursor-pointer rounded border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700"
            >
        </div>
        <button
            type="submit"
            class="rounded border border-blue-600 bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700"
        >
            Analizeaza
        </button>
    </form>
</div>

<?php if (!empty($importError)): ?>
    <div class="mt-4 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        <?= htmlspecialchars($importError) ?>
    </div>
<?php endif; ?>

<?php if (!empty($importInfo)): ?>
    <div class="mt-4 rounded border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800">
        <?= htmlspecialchars($importInfo) ?>
    </div>
<?php endif; ?>

<?php if (!empty($proposals)): ?>
    <div class="mt-6">
        <h2 class="text-base font-semibold text-slate-800">Propuneri de incasare</h2>
        <p class="mt-1 text-xs text-slate-500">
            Tranzactiile marcate <span class="font-semibold text-emerald-700">Nou</span> nu au fost inca procesate.
            Cele marcate <span class="font-semibold text-slate-500">Importat</span> au fost vazute anterior.
            Cele marcate <span class="font-semibold text-slate-400">Procesat</span> au deja o incasare creata.
        </p>

        <div class="mt-4 overflow-x-auto rounded border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs text-slate-600">
                    <tr>
                        <th class="px-3 py-2">Data</th>
                        <th class="px-3 py-2">Suma</th>
                        <th class="px-3 py-2">Ordonator</th>
                        <th class="px-3 py-2">CUI</th>
                        <th class="px-3 py-2">Detalii</th>
                        <th class="px-3 py-2">Client potrivit</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proposals as $p): ?>
                        <?php
                            $isNew       = ($p['status'] === 'new');
                            $isProcessed = ($p['status'] === 'processed');
                            $client      = $p['client'] ?? null;
                            $rowClass    = $isNew
                                ? 'border-b border-emerald-100 bg-emerald-50'
                                : 'border-b border-slate-100';
                            $dateFormatted = '';
                            if (!empty($p['processed_at'])) {
                                $ts = strtotime($p['processed_at']);
                                $dateFormatted = $ts ? date('d.m.Y', $ts) : $p['processed_at'];
                            }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="px-3 py-2 text-slate-700 whitespace-nowrap"><?= htmlspecialchars($dateFormatted) ?></td>
                            <td class="px-3 py-2 font-semibold <?= $isNew ? 'text-emerald-700' : 'text-slate-700' ?> whitespace-nowrap">
                                <?= number_format($p['amount'], 2, '.', ' ') ?> <?= htmlspecialchars($p['currency']) ?>
                            </td>
                            <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars($p['counterpart_name']) ?></td>
                            <td class="px-3 py-2 text-slate-500 text-xs"><?= htmlspecialchars($p['counterpart_cui']) ?></td>
                            <td class="px-3 py-2 text-xs text-slate-500 max-w-xs truncate" title="<?= htmlspecialchars($p['details']) ?>">
                                <?= htmlspecialchars(mb_substr($p['details'], 0, 60, 'UTF-8')) ?><?= mb_strlen($p['details'], 'UTF-8') > 60 ? '…' : '' ?>
                            </td>
                            <td class="px-3 py-2">
                                <?php if ($client): ?>
                                    <div class="text-xs font-semibold text-blue-700"><?= htmlspecialchars($client['name']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($client['cui']) ?></div>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Necunoscut</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2">
                                <?php if ($isNew): ?>
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Nou</span>
                                <?php elseif ($isProcessed): ?>
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Procesat</span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Importat</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <?php if ($isNew && !$isProcessed): ?>
                                    <?php
                                        $notesDefault = trim($p['details']);
                                        $formClientCui = $client ? $client['cui'] : $p['counterpart_cui'];
                                    ?>
                                    <form method="POST" action="<?= App\Support\Url::to('admin/incasari/import-extras/executa') ?>">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="row_hash" value="<?= htmlspecialchars($p['row_hash']) ?>">
                                        <input type="hidden" name="paid_at" value="<?= htmlspecialchars($p['processed_at']) ?>">
                                        <input type="hidden" name="amount" value="<?= htmlspecialchars((string) $p['amount']) ?>">
                                        <input type="hidden" name="notes" value="<?= htmlspecialchars($notesDefault) ?>">

                                        <div class="flex flex-wrap items-end gap-2">
                                            <div>
                                                <label class="mb-0.5 block text-[10px] font-semibold text-slate-500">Client CUI</label>
                                                <input
                                                    type="text"
                                                    name="client_cui"
                                                    value="<?= htmlspecialchars($formClientCui) ?>"
                                                    placeholder="CUI client"
                                                    class="w-28 rounded border border-slate-300 px-2 py-1 text-xs text-slate-800"
                                                    required
                                                >
                                            </div>
                                            <button
                                                class="rounded border border-emerald-500 bg-emerald-500 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-600"
                                            >
                                                Creaza incasare
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
