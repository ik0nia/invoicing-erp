<?php
    $title = 'Registru documente';
    $rows = $rows ?? [];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Registru documente</h1>
        <p class="mt-1 text-sm text-slate-500">Numerotare secventiala per tip document (doc_type), folosita la contracte.</p>
    </div>
</div>

<div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <div class="font-semibold">Cum functioneaza</div>
    <ul class="mt-2 list-disc space-y-1 pl-5">
        <li>Fiecare <span class="font-mono">doc_type</span> are registru separat, cu serie optionala.</li>
        <li><strong>next_no</strong> este urmatorul numar ce va fi alocat.</li>
        <li><strong>Seteaza start</strong> actualizeaza simultan <strong>start_no</strong> si <strong>next_no</strong>.</li>
        <li><strong>Reseteaza la start</strong> seteaza <strong>next_no = start_no</strong>.</li>
    </ul>
</div>

<?php if (empty($rows)): ?>
    <div class="mt-6 rounded border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Nu exista inca tipuri de document in registru. Se vor crea automat dupa definirea template-urilor si/sau generarea contractelor.
    </div>
<?php else: ?>
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Doc type</th>
                    <th class="px-3 py-2">Serie</th>
                    <th class="px-3 py-2">Start</th>
                    <th class="px-3 py-2">Urmatorul nr.</th>
                    <th class="px-3 py-2">Actualizat</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                        $docType = (string) ($row['doc_type'] ?? '');
                        $series = (string) ($row['series'] ?? '');
                        $startNo = (int) ($row['start_no'] ?? 1);
                        $nextNo = (int) ($row['next_no'] ?? 1);
                        $updatedAt = (string) ($row['updated_at'] ?? '');
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 font-mono text-slate-700"><?= htmlspecialchars($docType) ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <form method="POST" action="<?= App\Support\Url::to('admin/registru-documente/save') ?>" class="flex flex-wrap items-center gap-2">
                                <?= App\Support\Csrf::input() ?>
                                <input type="hidden" name="doc_type" value="<?= htmlspecialchars($docType) ?>">
                                <input
                                    type="text"
                                    name="series"
                                    value="<?= htmlspecialchars($series) ?>"
                                    maxlength="16"
                                    placeholder="ex: CTR"
                                    class="w-24 rounded border border-slate-300 px-2 py-1 text-xs"
                                >
                                <input
                                    type="number"
                                    min="1"
                                    name="start_no"
                                    value="<?= $startNo ?>"
                                    class="w-24 rounded border border-slate-300 px-2 py-1 text-xs"
                                >
                                <input
                                    type="number"
                                    min="1"
                                    name="next_no"
                                    value="<?= $nextNo ?>"
                                    class="w-24 rounded border border-slate-300 px-2 py-1 text-xs"
                                >
                                <button class="rounded border border-blue-600 bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                                    Salveaza
                                </button>
                            </form>
                        </td>
                        <td class="px-3 py-2 text-slate-600"><?= $startNo ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= $nextNo ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($updatedAt !== '' ? $updatedAt : '—') ?></td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <form method="POST" action="<?= App\Support\Url::to('admin/registru-documente/set-start') ?>" class="inline-flex items-center gap-2">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="doc_type" value="<?= htmlspecialchars($docType) ?>">
                                    <input type="hidden" name="series" value="<?= htmlspecialchars($series) ?>">
                                    <input
                                        type="number"
                                        min="1"
                                        name="start_no"
                                        value="<?= $startNo ?>"
                                        class="w-20 rounded border border-slate-300 px-2 py-1 text-xs"
                                    >
                                    <button class="text-xs font-semibold text-blue-700 hover:text-blue-800">
                                        Seteaza start
                                    </button>
                                </form>
                                <form method="POST" action="<?= App\Support\Url::to('admin/registru-documente/reset-start') ?>">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="doc_type" value="<?= htmlspecialchars($docType) ?>">
                                    <button
                                        class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                        onclick="return confirm('Resetezi numerotarea la start pentru acest tip de document?')"
                                    >
                                        Reseteaza la start
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
