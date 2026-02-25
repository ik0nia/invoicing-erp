<?php $title = 'Extras bancar'; ?>

<?php
$statusLabels = [
    'processed' => 'Procesat',
    'imported'  => 'Importat',
    'ignored'   => 'Ignorat',
    'new'       => 'Nou',
];
$statusClasses = [
    'processed' => 'bg-green-100 text-green-700',
    'imported'  => 'bg-blue-100 text-blue-700',
    'ignored'   => 'bg-slate-100 text-slate-400',
    'new'       => 'bg-amber-100 text-amber-700',
];
$incoming = $incoming ?? [];
$outgoing = $outgoing ?? [];
$clients  = $clients  ?? [];
?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Extras bancar</h1>
        <p class="mt-1 text-sm text-slate-500">Tranzactii importate — incasari si plati.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a
            href="<?= App\Support\Url::to('admin/incasari/extras/brut') ?>"
            class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Vezi brut
        </a>
        <a
            href="<?= App\Support\Url::to('admin/incasari/import-extras') ?>"
            class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Import CSV
        </a>
        <a
            href="<?= App\Support\Url::to('admin/incasari') ?>"
            class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
        >
            Inapoi
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['status'])): ?>
    <div class="mt-4 rounded border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700"><?= htmlspecialchars($_SESSION['status']) ?></div>
    <?php unset($_SESSION['status']); ?>
<?php endif; ?>

<?php if (empty($incoming) && empty($outgoing)): ?>
    <div class="mt-6 rounded border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500">
        Nu exista tranzactii importate.
        <a href="<?= App\Support\Url::to('admin/incasari/import-extras') ?>" class="ml-1 font-semibold text-blue-600 underline hover:text-blue-800">Importa un extras CSV</a>.
    </div>
<?php endif; ?>

<?php if (!empty($incoming)): ?>
<div class="mt-6">
    <h2 class="text-base font-semibold text-slate-800">Incasari — intrari <span class="ml-1 text-sm font-normal text-slate-500">(<?= count($incoming) ?>)</span></h2>
    <div class="mt-2 overflow-x-auto rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-600">
                <tr>
                    <th class="px-3 py-2 whitespace-nowrap">Data</th>
                    <th class="px-3 py-2 whitespace-nowrap">Suma</th>
                    <th class="px-3 py-2">Contraparte</th>
                    <th class="px-3 py-2">Detalii</th>
                    <th class="px-3 py-2">Client</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right">Actiuni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incoming as $p): ?>
                    <?php
                        $status      = $p['status'] ?? 'new';
                        $bankTxId    = (int) ($p['id'] ?? 0);
                        $paymentInId = (int) ($p['payment_in_id'] ?? 0);
                        $client      = $p['client'] ?? null;
                        $isIgnored   = ($status === 'ignored');
                        $isProcessed = ($status === 'processed');
                        $dateFormatted = '';
                        if (!empty($p['processed_at'])) {
                            $ts = strtotime($p['processed_at']);
                            $dateFormatted = $ts ? date('d.m.Y', $ts) : $p['processed_at'];
                        }
                    ?>
                    <tr class="border-t border-slate-100<?= $isIgnored ? ' opacity-50' : '' ?>">
                        <td class="px-3 py-2 whitespace-nowrap text-slate-600"><?= htmlspecialchars($dateFormatted) ?></td>
                        <td class="px-3 py-2 whitespace-nowrap font-semibold text-green-700">
                            +<?= number_format((float) ($p['amount'] ?? 0), 2, '.', ' ') ?> <?= htmlspecialchars($p['currency'] ?? 'RON') ?>
                        </td>
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars($p['counterpart_name'] ?? '') ?></td>
                        <td class="px-3 py-2 max-w-xs">
                            <span class="block truncate text-xs text-slate-500" title="<?= htmlspecialchars($p['details'] ?? '') ?>">
                                <?= htmlspecialchars(mb_substr($p['details'] ?? '', 0, 60, 'UTF-8')) ?><?= mb_strlen($p['details'] ?? '', 'UTF-8') > 60 ? '…' : '' ?>
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <?php if ($client): ?>
                                <div class="text-xs font-semibold text-blue-700"><?= htmlspecialchars($client['name'] ?? '') ?></div>
                                <div class="text-[10px] text-slate-400"><?= htmlspecialchars($client['cui'] ?? '') ?></div>
                            <?php elseif (!$isIgnored && !$isProcessed): ?>
                                <select
                                    name="manual_client_cui_<?= $bankTxId ?>"
                                    class="w-44 rounded border border-slate-300 px-2 py-1 text-xs text-slate-700"
                                >
                                    <option value="">Alege client...</option>
                                    <?php foreach ($clients as $cl): ?>
                                        <option value="<?= htmlspecialchars($cl['cui']) ?>">
                                            <?= htmlspecialchars($cl['name']) ?> · <?= htmlspecialchars($cl['cui']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <?php if ($isProcessed && $paymentInId > 0): ?>
                                <a
                                    href="<?= App\Support\Url::to('admin/incasari/istoric?payment_id=' . $paymentInId) ?>"
                                    class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold <?= $statusClasses[$status] ?? '' ?> hover:underline"
                                    title="Deschide incasarea #<?= $paymentInId ?>"
                                >
                                    <?= $statusLabels[$status] ?? $status ?> #<?= $paymentInId ?>
                                </a>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold <?= $statusClasses[$status] ?? '' ?>">
                                    <?= $statusLabels[$status] ?? $status ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap space-x-1">
                            <?php if (!$isIgnored && !$isProcessed): ?>
                                <form
                                    method="POST"
                                    action="<?= App\Support\Url::to('admin/incasari/import-extras/executa') ?>"
                                    class="inline js-create-form-<?= $bankTxId ?>"
                                >
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="row_hash"          value="<?= htmlspecialchars($p['row_hash'] ?? '') ?>">
                                    <input type="hidden" name="paid_at"           value="<?= htmlspecialchars($p['processed_at'] ?? '') ?>">
                                    <input type="hidden" name="amount"            value="<?= htmlspecialchars((string) ($p['amount'] ?? '')) ?>">
                                    <input type="hidden" name="notes"             value="<?= htmlspecialchars($p['details'] ?? '') ?>">
                                    <input type="hidden" name="client_cui"        value="<?= htmlspecialchars($client['cui'] ?? '') ?>">
                                    <input type="hidden" name="manual_client_cui" class="js-manual-cui-<?= $bankTxId ?>" value="">
                                    <button
                                        type="submit"
                                        class="rounded border border-blue-500 bg-blue-500 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-600"
                                    >
                                        Creaza incasare
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($bankTxId > 0 && !$isProcessed): ?>
                                <form
                                    method="POST"
                                    action="<?= App\Support\Url::to('admin/incasari/import-extras/ignora') ?>"
                                    class="inline"
                                >
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="bank_tx_id" value="<?= $bankTxId ?>">
                                    <input type="hidden" name="action"     value="<?= $isIgnored ? 'unignore' : 'ignore' ?>">
                                    <button
                                        type="submit"
                                        class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                    >
                                        <?= $isIgnored ? 'Reactiveaza' : 'Ignora' ?>
                                    </button>
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

<?php if (!empty($outgoing)): ?>
<div class="mt-6">
    <h2 class="text-base font-semibold text-slate-800">Plati — iesiri <span class="ml-1 text-sm font-normal text-slate-500">(<?= count($outgoing) ?>)</span></h2>
    <div class="mt-2 overflow-x-auto rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-600">
                <tr>
                    <th class="px-3 py-2 whitespace-nowrap">Data</th>
                    <th class="px-3 py-2 whitespace-nowrap">Suma</th>
                    <th class="px-3 py-2">Contraparte</th>
                    <th class="px-3 py-2">Detalii</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2 text-right">Actiuni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($outgoing as $p): ?>
                    <?php
                        $status    = $p['status'] ?? 'new';
                        $bankTxId  = (int) ($p['id'] ?? 0);
                        $isIgnored = ($status === 'ignored');
                        $dateFormatted = '';
                        if (!empty($p['processed_at'])) {
                            $ts = strtotime($p['processed_at']);
                            $dateFormatted = $ts ? date('d.m.Y', $ts) : $p['processed_at'];
                        }
                    ?>
                    <tr class="border-t border-slate-100<?= $isIgnored ? ' opacity-50' : '' ?>">
                        <td class="px-3 py-2 whitespace-nowrap text-slate-600"><?= htmlspecialchars($dateFormatted) ?></td>
                        <td class="px-3 py-2 whitespace-nowrap font-semibold text-red-700">
                            <?= number_format((float) ($p['amount'] ?? 0), 2, '.', ' ') ?> <?= htmlspecialchars($p['currency'] ?? 'RON') ?>
                        </td>
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars($p['counterpart_name'] ?? '') ?></td>
                        <td class="px-3 py-2 max-w-xs">
                            <span class="block truncate text-xs text-slate-500" title="<?= htmlspecialchars($p['details'] ?? '') ?>">
                                <?= htmlspecialchars(mb_substr($p['details'] ?? '', 0, 60, 'UTF-8')) ?><?= mb_strlen($p['details'] ?? '', 'UTF-8') > 60 ? '…' : '' ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold <?= $statusClasses[$status] ?? '' ?>">
                                <?= $statusLabels[$status] ?? $status ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <?php if ($bankTxId > 0 && $status !== 'processed'): ?>
                                <form
                                    method="POST"
                                    action="<?= App\Support\Url::to('admin/incasari/import-extras/ignora') ?>"
                                    class="inline"
                                >
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="bank_tx_id" value="<?= $bankTxId ?>">
                                    <input type="hidden" name="action"     value="<?= $isIgnored ? 'unignore' : 'ignore' ?>">
                                    <button
                                        type="submit"
                                        class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                    >
                                        <?= $isIgnored ? 'Reactiveaza' : 'Ignora' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    document.querySelectorAll('select[name^="manual_client_cui_"]').forEach(function (sel) {
        var txId   = sel.getAttribute('name').replace('manual_client_cui_', '');
        var hidden = document.querySelector('.js-manual-cui-' + txId);
        if (!hidden) return;
        sel.addEventListener('change', function () {
            hidden.value = sel.value;
        });
    });
}());
</script>
