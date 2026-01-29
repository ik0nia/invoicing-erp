<?php $title = 'Factura ' . htmlspecialchars($invoice->invoice_number); ?>

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Factura <?= htmlspecialchars($invoice->invoice_number) ?></h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= htmlspecialchars($invoice->supplier_name) ?> → <?= htmlspecialchars($invoice->customer_name) ?>
        </p>
        <?php if (!empty($isConfirmed)): ?>
            <div class="mt-2 inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                Pachete confirmate
            </div>
        <?php else: ?>
            <div class="mt-2 inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                Pachete neconfirmate
            </div>
        <?php endif; ?>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/facturi') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi la lista
    </a>
</div>

<div class="mt-6 grid gap-4 md:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Furnizor</div>
        <div class="mt-1 font-medium text-slate-900"><?= htmlspecialchars($invoice->supplier_name) ?></div>
        <div class="text-slate-500">CUI: <?= htmlspecialchars($invoice->supplier_cui) ?></div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Client initial</div>
        <div class="mt-1 font-medium text-slate-900"><?= htmlspecialchars($invoice->customer_name) ?></div>
        <div class="text-slate-500">CUI: <?= htmlspecialchars($invoice->customer_cui) ?></div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Total factura</div>
        <div class="mt-1 text-lg font-semibold text-slate-900">
            <?= number_format($invoice->total_with_vat, 2, '.', ' ') ?> RON
        </div>
        <div class="text-slate-500">Fara TVA: <?= number_format($invoice->total_without_vat, 2, '.', ' ') ?> RON</div>
    </div>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-[1.2fr_1fr]">
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">Pachete</h2>
        </div>

        <form method="POST" action="<?= App\Support\Url::to('admin/facturi/pachete') ?>" class="mt-4 rounded border border-slate-200 bg-slate-50 p-4">
            <?= App\Support\Csrf::input() ?>
            <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
            <input type="hidden" name="action" value="generate">

            <?php if (empty($vatRates)): ?>
                <div class="text-sm text-slate-500">Nu exista produse pentru a genera pachete.</div>
            <?php else: ?>
                <div class="grid gap-3 md:grid-cols-2">
                    <?php foreach ($vatRates as $vatRate): ?>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="vat_<?= htmlspecialchars($vatRate) ?>">
                                Numar pachete pentru TVA <?= htmlspecialchars($vatRate) ?>%
                            </label>
                            <input
                                id="vat_<?= htmlspecialchars($vatRate) ?>"
                                name="package_counts[<?= htmlspecialchars($vatRate) ?>]"
                                type="number"
                                min="1"
                                value="<?= (int) ($packageDefaults[$vatRate] ?? 1) ?>"
                                class="mt-1 w-24 rounded border border-slate-300 px-2 py-1 text-sm"
                            >
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 text-xs text-slate-500">
                    Impartirea se face automat in functie de cotele TVA. Poti muta manual produse intre pachete cu aceeasi TVA.
                </div>
                <div class="mt-4 flex flex-wrap gap-3">
                    <?php if (empty($isConfirmed)): ?>
                        <button class="rounded bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Genereaza pachete
                        </button>
                    <?php else: ?>
                        <div class="text-sm text-slate-500">Pachetele sunt confirmate si nu pot fi regenerate.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </form>

        <?php if (empty($isConfirmed) && !empty($packages)): ?>
            <form method="POST" action="<?= App\Support\Url::to('admin/facturi/pachete') ?>" class="mt-4">
                <?= App\Support\Csrf::input() ?>
                <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                <input type="hidden" name="action" value="confirm">
                <button class="rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">
                    Confirma pachetele
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Clienti disponibili</h2>
        <p class="mt-2 text-sm text-slate-500">
            Comisioane asociate furnizorului <?= htmlspecialchars($invoice->supplier_cui) ?>.
        </p>
        <?php if (empty($clients)): ?>
            <p class="mt-4 text-sm text-slate-500">Nu exista comisioane importate pentru acest furnizor.</p>
        <?php else: ?>
            <div class="mt-4 max-h-64 overflow-auto rounded border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Client CUI</th>
                            <th class="px-3 py-2">Comision (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2"><?= htmlspecialchars($client->client_cui) ?></td>
                                <td class="px-3 py-2"><?= number_format($client->commission, 2, '.', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-8 rounded-lg border border-slate-300 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-900">Organizare produse (drag & drop)</h2>
    <p class="mt-2 text-sm text-slate-600">
        Poti muta produsele intre pachete doar daca au aceeasi cota TVA.
    </p>
    <?php if (!empty($isConfirmed)): ?>
        <div class="mt-3 text-sm font-semibold text-amber-700">
            Pachetele sunt confirmate si nu mai pot fi mutate.
        </div>
    <?php endif; ?>

    <?php
        $unassigned = $linesByPackage[0] ?? [];
        $unassignedCount = count($unassigned);
    ?>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <?php foreach ($packages as $package): ?>
            <?php $packageLines = $linesByPackage[$package->id] ?? []; ?>
            <?php $stat = $packageStats[$package->id] ?? null; ?>
            <div
                class="rounded border border-slate-300 bg-white p-4 shadow-sm"
                data-drop-zone
                data-package-id="<?= (int) $package->id ?>"
                data-vat="<?= htmlspecialchars(number_format($package->vat_percent, 2, '.', '')) ?>"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="text-sm font-semibold text-slate-900">
                        <?= htmlspecialchars($package->label ?: 'Pachet de produse #' . $package->package_no) ?>
                    </div>
                    <?php if (empty($isConfirmed)): ?>
                        <form method="POST" action="<?= App\Support\Url::to('admin/facturi/pachete') ?>">
                            <?= App\Support\Csrf::input() ?>
                            <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="package_id" value="<?= (int) $package->id ?>">
                            <button class="text-xs font-semibold text-red-600 hover:text-red-700">Sterge</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="text-xs font-semibold text-slate-600">Cota TVA <?= number_format($package->vat_percent, 2, '.', ' ') ?>%</div>
                <?php if ($stat): ?>
                    <div class="mt-1 text-xs text-slate-600">
                        <?= (int) $stat['line_count'] ?> produse ·
                        <?= number_format($stat['total_vat'], 2, '.', ' ') ?> RON cu TVA
                    </div>
                <?php endif; ?>
                <div class="mt-3 space-y-2 min-h-[40px]">
                    <?php if (empty($packageLines)): ?>
                        <div class="text-xs font-semibold text-slate-500">Fara produse</div>
                    <?php else: ?>
                        <?php foreach ($packageLines as $line): ?>
                            <div
                                class="cursor-move rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm"
                                draggable="true"
                                data-line-draggable
                                data-line-id="<?= (int) $line->id ?>"
                                data-vat="<?= htmlspecialchars(number_format($line->tax_percent, 2, '.', '')) ?>"
                            >
                                <?= htmlspecialchars($line->product_name) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($unassignedCount > 0): ?>
            <div
                class="rounded border-2 border-dashed border-slate-400 bg-slate-50 p-4"
                data-drop-zone
                data-package-id="0"
                data-vat=""
            >
                <div class="text-sm font-semibold text-slate-900">Nealocat</div>
                <div class="text-xs font-semibold text-slate-600">Produse fara pachet · <?= (int) $unassignedCount ?></div>
                <div class="mt-3 space-y-2 min-h-[40px]">
                    <?php foreach ($unassigned as $line): ?>
                        <div
                            class="cursor-move rounded border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm"
                            draggable="true"
                            data-line-draggable
                            data-line-id="<?= (int) $line->id ?>"
                            data-vat="<?= htmlspecialchars(number_format($line->tax_percent, 2, '.', '')) ?>"
                        >
                            <?= htmlspecialchars($line->product_name) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <form id="move-line-form" method="POST" action="<?= App\Support\Url::to('admin/facturi/muta-linie') ?>" class="hidden">
        <?= App\Support\Csrf::input() ?>
        <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
        <input type="hidden" name="line_id" value="">
        <input type="hidden" name="package_id" value="">
    </form>
</div>

<div class="mt-8 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-900">Produse din factura</h2>
    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Produs</th>
                    <th class="px-3 py-2">Cantitate</th>
                    <th class="px-3 py-2">Pret</th>
                    <th class="px-3 py-2">TVA</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Pachet</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                    <tr class="border-b border-slate-100">
                        <td class="px-3 py-2">
                            <?= htmlspecialchars($line->product_name) ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= number_format($line->quantity, 2, '.', ' ') ?> <?= htmlspecialchars($line->unit_code) ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= number_format($line->unit_price, 2, '.', ' ') ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= number_format($line->tax_percent, 2, '.', ' ') ?>%
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?= number_format($line->line_total_vat, 2, '.', ' ') ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?php if (!empty($isConfirmed)): ?>
                                <span class="text-xs text-slate-400">Confirmat</span>
                            <?php else: ?>
                                <form method="POST" action="<?= App\Support\Url::to('admin/facturi/muta-linie') ?>" class="flex items-center gap-2">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                                    <input type="hidden" name="line_id" value="<?= (int) $line->id ?>">
                                    <select name="package_id" class="rounded border border-slate-300 px-2 py-1 text-sm">
                                        <option value="">Nealocat</option>
                                        <?php foreach ($packages as $package): ?>
                                            <?php
                                                $allowed = abs($package->vat_percent - $line->tax_percent) < 0.01 || $package->vat_percent <= 0;
                                            ?>
                                            <?php if (!$allowed): ?>
                                                <?php continue; ?>
                                            <?php endif; ?>
                                            <option
                                                value="<?= (int) $package->id ?>"
                                                <?= $line->package_id === $package->id ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars($package->label ?: 'Pachet de produse #' . $package->package_no) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="text-blue-700 hover:text-blue-800">Muta</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    (function () {
        const isLocked = <?= !empty($isConfirmed) ? 'true' : 'false' ?>;
        if (isLocked) {
            return;
        }

        const form = document.getElementById('move-line-form');
        if (!form) {
            return;
        }

        let draggedLineId = null;
        let draggedVat = null;

        document.querySelectorAll('[data-line-draggable]').forEach((item) => {
            item.addEventListener('dragstart', (event) => {
                draggedLineId = item.dataset.lineId || null;
                draggedVat = item.dataset.vat || null;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', draggedLineId || '');
            });
        });

        document.querySelectorAll('[data-drop-zone]').forEach((zone) => {
            zone.addEventListener('dragover', (event) => {
                if (!draggedLineId) {
                    return;
                }
                const zoneVat = zone.dataset.vat || '';
                const isEmptyVat = zoneVat === '' || zoneVat === '0' || zoneVat === '0.00';
                if (!isEmptyVat && draggedVat !== '' && zoneVat !== draggedVat) {
                    return;
                }
                event.preventDefault();
                zone.classList.add('ring-2', 'ring-blue-400');
            });

            zone.addEventListener('dragleave', () => {
                zone.classList.remove('ring-2', 'ring-blue-400');
            });

            zone.addEventListener('drop', (event) => {
                event.preventDefault();
                zone.classList.remove('ring-2', 'ring-blue-400');
                if (!draggedLineId) {
                    return;
                }

                const zoneVat = zone.dataset.vat || '';
                const isEmptyVat = zoneVat === '' || zoneVat === '0' || zoneVat === '0.00';
                if (!isEmptyVat && draggedVat !== '' && zoneVat !== draggedVat) {
                    alert('Poti muta doar produse cu aceeasi cota TVA.');
                    return;
                }

                form.querySelector('input[name="line_id"]').value = draggedLineId;
                form.querySelector('input[name="package_id"]').value = zone.dataset.packageId || '';
                form.submit();
            });
        });
    })();
</script>
