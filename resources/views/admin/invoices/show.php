<?php $title = 'Factura ' . htmlspecialchars($invoice->invoice_number); ?>

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Factura <?= htmlspecialchars($invoice->invoice_number) ?></h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= htmlspecialchars($invoice->supplier_name) ?> → <?= htmlspecialchars($invoice->customer_name) ?>
        </p>
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
            <div class="flex gap-2">
                <form method="POST" action="<?= App\Support\Url::to('admin/facturi/pachete') ?>">
                    <?= App\Support\Csrf::input() ?>
                    <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                    <input type="hidden" name="action" value="create">
                    <button class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900">
                        Adauga pachet
                    </button>
                </form>
                <form method="POST" action="<?= App\Support\Url::to('admin/facturi/pachete') ?>">
                    <?= App\Support\Csrf::input() ?>
                    <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                    <input type="hidden" name="action" value="auto">
                    <button class="rounded bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Auto pe TVA
                    </button>
                </form>
            </div>
        </div>

        <?php if (empty($packages)): ?>
            <p class="mt-4 text-sm text-slate-500">Nu exista pachete. Foloseste butoanele de mai sus.</p>
        <?php else: ?>
            <div class="mt-4 space-y-3">
                <?php foreach ($packages as $package): ?>
                    <?php $stat = $packageStats[$package->id] ?? null; ?>
                    <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                        <div class="font-medium text-slate-900">
                            <?= htmlspecialchars($package->label ?: 'Pachet #' . $package->id) ?>
                        </div>
                        <?php if ($stat): ?>
                            <div class="mt-1 text-slate-500">
                                <?= (int) $stat['line_count'] ?> produse ·
                                <?= number_format($stat['total_vat'], 2, '.', ' ') ?> RON cu TVA
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
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
                            <form method="POST" action="<?= App\Support\Url::to('admin/facturi/muta-linie') ?>" class="flex items-center gap-2">
                                <?= App\Support\Csrf::input() ?>
                                <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                                <input type="hidden" name="line_id" value="<?= (int) $line->id ?>">
                                <select name="package_id" class="rounded border border-slate-300 px-2 py-1 text-sm">
                                    <option value="">Nealocat</option>
                                    <?php foreach ($packages as $package): ?>
                                        <option
                                            value="<?= (int) $package->id ?>"
                                            <?= $line->package_id === $package->id ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($package->label ?: 'Pachet #' . $package->id) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="text-blue-700 hover:text-blue-800">Muta</button>
                            </form>
                        </td>
                        <td class="px-3 py-2 text-slate-600"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
