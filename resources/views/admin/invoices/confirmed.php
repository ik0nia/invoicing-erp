<?php $title = 'Pachete confirmate'; ?>

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Pachete confirmate</h1>
        <p class="mt-1 text-sm text-slate-600">
            Selecteaza pachetele pentru a genera fisierele Saga.
        </p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/facturi') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi la facturi
    </a>
</div>

<?php if (empty($packages)): ?>
    <div class="mt-6 rounded border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Nu exista pachete confirmate.
    </div>
<?php else: ?>
    <form method="POST" action="<?= App\Support\Url::to('admin/pachete-confirmate/descarca') ?>" class="mt-6 space-y-4">
        <?= App\Support\Csrf::input() ?>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" id="select-all" class="rounded border-slate-300">
                Selecteaza toate
            </label>
            <button class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Descarca Saga pentru selectate
            </button>
        </div>

        <div class="overflow-x-auto rounded border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2">Select</th>
                        <th class="px-3 py-2">Factura</th>
                        <th class="px-3 py-2">Furnizor</th>
                        <th class="px-3 py-2">Pachet</th>
                        <th class="px-3 py-2">Produse</th>
                        <th class="px-3 py-2">Total</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $row): ?>
                        <?php
                            $packageId = (int) $row['id'];
                            $stat = $totals[$packageId] ?? ['line_count' => 0, 'total_vat' => 0];
                            $labelText = trim((string) ($row['label'] ?? ''));
                            if ($labelText === '') {
                                $labelText = 'Pachet de produse';
                            }
                            $label = $labelText . ' #' . $row['package_no'];
                        ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2">
                                <input
                                    type="checkbox"
                                    name="package_ids[]"
                                    value="<?= $packageId ?>"
                                    class="package-checkbox rounded border-slate-300"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <a
                                    href="<?= App\Support\Url::to('admin/facturi?invoice_id=' . (int) $row['invoice_in_id']) ?>"
                                    class="text-blue-700 hover:text-blue-800"
                                >
                                    <?= htmlspecialchars($row['invoice_number']) ?>
                                </a>
                            </td>
                            <td class="px-3 py-2 text-slate-600">
                                <?= htmlspecialchars($row['supplier_name'] ?? '') ?>
                            </td>
                            <td class="px-3 py-2 text-slate-700">
                                <?= htmlspecialchars($label) ?>
                            </td>
                            <td class="px-3 py-2 text-slate-600">
                                <?= (int) ($stat['line_count'] ?? 0) ?>
                            </td>
                            <td class="px-3 py-2 text-slate-600">
                                <?= number_format((float) ($stat['total_vat'] ?? 0), 2, '.', ' ') ?> RON
                            </td>
                            <td class="px-3 py-2 text-right">
                                <form method="POST" action="<?= App\Support\Url::to('admin/facturi/saga/pachet') ?>">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="package_id" value="<?= $packageId ?>">
                                    <button class="text-xs font-semibold text-blue-700 hover:text-blue-800">Saga .ahk</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
<?php endif; ?>

<script>
    (function () {
        const selectAll = document.getElementById('select-all');
        if (!selectAll) {
            return;
        }

        const boxes = Array.from(document.querySelectorAll('.package-checkbox'));
        selectAll.addEventListener('change', () => {
            boxes.forEach((box) => {
                box.checked = selectAll.checked;
            });
        });
    })();
</script>
