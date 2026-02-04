<?php
    $title = 'Factura ' . htmlspecialchars($invoice->invoice_number);
    $isPlatform = $isPlatform ?? false;
?>

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

<?php if (!empty($invoice->fgo_storno_number)): ?>
    <?php
        $stornoDate = (string) ($invoice->fgo_storno_at ?? '');
        $stornoTs = $stornoDate !== '' ? strtotime($stornoDate) : false;
        $stornoLabel = $stornoTs ? date('d.m.Y H:i', $stornoTs) : ($invoice->fgo_date ? date('d.m.Y', strtotime((string) $invoice->fgo_date)) : '');
        $stornoLabel = $stornoLabel !== '' ? $stornoLabel : '-';
    ?>
    <div class="mt-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        Factura a fost stornata la data <strong><?= htmlspecialchars($stornoLabel) ?></strong>.
    </div>
<?php elseif (!empty($invoice->fgo_number)): ?>
    <?php
        $generatedDate = (string) ($invoice->fgo_generated_at ?? '');
        $generatedTs = $generatedDate !== '' ? strtotime($generatedDate) : false;
        $generatedLabel = $generatedTs ? date('d.m.Y H:i', $generatedTs) : ($invoice->fgo_date ? date('d.m.Y', strtotime((string) $invoice->fgo_date)) : '');
        $generatedLabel = $generatedLabel !== '' ? $generatedLabel : '-';
    ?>
    <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        Factura a fost generata la data <strong><?= htmlspecialchars($generatedLabel) ?></strong>.
    </div>
<?php elseif (!empty($invoice->supplier_request_at)): ?>
    <?php
        $requestTs = strtotime((string) $invoice->supplier_request_at);
        $requestLabel = $requestTs ? date('d.m.Y H:i', $requestTs) : (string) $invoice->supplier_request_at;
    ?>
    <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Facturare solicitata la data <strong><?= htmlspecialchars($requestLabel) ?></strong>.
    </div>
<?php endif; ?>

<div class="mt-6 grid gap-4 lg:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm lg:col-span-1">
        <div class="text-slate-500">Furnizor</div>
        <div class="mt-1 font-medium text-slate-900"><?= htmlspecialchars($invoice->supplier_name) ?></div>
        <div class="mt-2 space-y-1 text-slate-600">
            <div><span class="text-slate-500">CUI:</span> <?= htmlspecialchars($invoice->supplier_cui) ?></div>
            <div>
                <span class="text-slate-500">Nr factura:</span>
                <?= htmlspecialchars(trim($invoice->invoice_series . ' ' . $invoice->invoice_no) ?: $invoice->invoice_number) ?>
            </div>
            <div><span class="text-slate-500">Data emitere:</span> <?= htmlspecialchars($invoice->issue_date) ?></div>
            <div><span class="text-slate-500">Scadenta:</span> <?= htmlspecialchars($invoice->due_date ?: '—') ?></div>
            <div><span class="text-slate-500">Moneda:</span> <?= htmlspecialchars($invoice->currency) ?></div>
            <div><span class="text-slate-500">Total factura:</span> <?= number_format($invoice->total_with_vat, 2, '.', ' ') ?> RON</div>
            <div><span class="text-slate-500">Fara TVA:</span> <?= number_format($invoice->total_without_vat, 2, '.', ' ') ?> RON</div>
            <div><span class="text-slate-500">TVA:</span> <?= number_format($invoice->total_vat, 2, '.', ' ') ?> RON</div>
        </div>
    </div>
    <div id="client-select" class="rounded-lg border border-slate-200 bg-white p-4 text-sm lg:col-span-2">
        <h2 class="text-base font-semibold text-slate-900">Client de facturat</h2>
        <p class="mt-2 text-sm text-slate-600">
            Alege clientul pentru a calcula comisionul pe pachete.
        </p>

        <?php if (!empty($invoice->xml_path)): ?>
            <div class="mt-3 text-xs text-slate-600">
                Fisier furnizor:
                <a
                    href="<?= App\Support\Url::to('admin/facturi/fisier') ?>?invoice_id=<?= (int) $invoice->id ?>"
                    target="_blank"
                    rel="noopener"
                    class="text-blue-700 hover:text-blue-800"
                >
                    Deschide
                </a>
            </div>
        <?php endif; ?>

        <?php if (!empty($clientLocked)): ?>
            <div class="mt-3 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                Clientul este blocat deoarece pachetele sunt confirmate.
                <?php if (!empty($isAdmin)): ?>
                    <div class="mt-2">
                        <form method="POST" action="<?= App\Support\Url::to('admin/facturi/deblocheaza-client') ?>">
                            <?= App\Support\Csrf::input() ?>
                            <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                            <button
                                type="submit"
                                class="rounded border border-amber-300 bg-white px-3 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                                onclick="return confirm('Sigur doresti deblocarea clientului pentru aceasta factura confirmata?')"
                            >
                                Deblocheaza client
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($clientLocked) && !empty($selectedClientCui)): ?>
            <div class="mt-4 rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <div>Client selectat: <strong><?= htmlspecialchars($selectedClientName ?: $selectedClientCui) ?></strong></div>
                <?php if ($commissionPercent !== null): ?>
                    <div class="mt-1">Comision: <strong><?= number_format($commissionPercent, 2, '.', ' ') ?>%</strong></div>
                <?php endif; ?>
            </div>
        <?php elseif (empty($clients)): ?>
            <p class="mt-4 text-sm text-slate-600">Nu exista comisioane importate pentru acest furnizor.</p>
        <?php else: ?>
            <form method="GET" action="<?= App\Support\Url::to('admin/facturi') ?>" class="mt-4 space-y-3" id="client-form" data-client-locked="<?= !empty($clientLocked) ? '1' : '0' ?>">
                <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                <input type="hidden" name="anchor" value="client-select">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="client-search">Cauta client</label>
                    <input
                        id="client-search"
                        type="text"
                        placeholder="Cauta dupa nume sau CUI"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm <?= !empty($clientLocked) ? 'bg-slate-100 text-slate-400' : '' ?>"
                        <?= !empty($clientLocked) ? 'disabled' : '' ?>
                    >
                </div>

                <div class="max-h-64 overflow-auto rounded border border-slate-200 <?= !empty($clientLocked) ? 'opacity-60' : '' ?>">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600 sticky top-0">
                            <tr>
                                <th class="px-3 py-2">Select</th>
                                <th class="px-3 py-2">Client</th>
                                <th class="px-3 py-2">CUI</th>
                                <th class="px-3 py-2">Comision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <?php
                                    $clientCui = (string) $client['client_cui'];
                                    $clientName = (string) ($client['client_name'] ?? '');
                                    $isSelected = ($selectedClientCui ?? '') === $clientCui;
                                ?>
                                <tr
                                    class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer <?= $isSelected ? 'bg-blue-50' : '' ?>"
                                    data-client-row
                                    data-search="<?= htmlspecialchars(strtolower($clientName . ' ' . $clientCui)) ?>"
                                >
                                    <td class="px-3 py-2">
                                        <input
                                            type="radio"
                                            name="client_cui"
                                            value="<?= htmlspecialchars($clientCui) ?>"
                                            <?= $isSelected ? 'checked' : '' ?>
                                            <?= !empty($clientLocked) ? 'disabled' : '' ?>
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-slate-800">
                                        <?= htmlspecialchars($clientName ?: $clientCui) ?>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($clientCui) ?></td>
                                    <td class="px-3 py-2 text-slate-600"><?= number_format((float) $client['commission'], 2, '.', ' ') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <?php if (!empty($selectedClientCui)): ?>
                <div class="mt-4 rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    <div>Client selectat: <strong><?= htmlspecialchars($selectedClientName ?: $selectedClientCui) ?></strong></div>
                    <?php if ($commissionPercent !== null): ?>
                        <div class="mt-1">Comision: <strong><?= number_format($commissionPercent, 2, '.', ' ') ?>%</strong></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4 grid gap-4 md:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Incasat client</div>
        <?php if ($clientTotal !== null): ?>
            <div class="mt-1 font-semibold text-slate-900">
                <?= number_format($collectedTotal ?? 0, 2, '.', ' ') ?> / <?= number_format($clientTotal, 2, '.', ' ') ?> RON
            </div>
            <div class="text-xs text-slate-600">
                <?php
                    $collectedValue = (float) ($collectedTotal ?? 0);
                    if ($collectedValue <= 0.009) {
                        echo 'Neincasat';
                    } elseif ($collectedValue + 0.01 < $clientTotal) {
                        echo 'Incasat partial';
                    } else {
                        echo 'Incasat integral';
                    }
                ?>
            </div>
            <?php if (!empty($isPlatform) && !empty($paymentInRows)): ?>
                <div class="mt-3 space-y-1 text-xs text-slate-600">
                    <?php foreach ($paymentInRows as $row): ?>
                        <a
                            href="<?= App\Support\Url::to('admin/incasari/istoric') ?>?payment_id=<?= (int) $row['id'] ?>#payment-in-<?= (int) $row['id'] ?>"
                            class="block text-blue-700 hover:text-blue-800"
                        >
                            Incasare #<?= (int) $row['id'] ?> · <?= htmlspecialchars($row['paid_at'] ?? '') ?> ·
                            Alocat <?= number_format((float) ($row['alloc_amount'] ?? 0), 2, '.', ' ') ?> RON
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="mt-1 text-sm text-slate-500">Selecteaza clientul pentru total.</div>
        <?php endif; ?>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
        <div class="text-slate-500">Platit furnizor</div>
        <div class="mt-1 font-semibold text-slate-900">
            <?= number_format($paidTotal ?? 0, 2, '.', ' ') ?> / <?= number_format($invoice->total_with_vat, 2, '.', ' ') ?> RON
        </div>
        <div class="text-xs text-slate-600">
            <?php
                $paidValue = (float) ($paidTotal ?? 0);
                $supplierTotal = (float) $invoice->total_with_vat;
                if ($paidValue <= 0.009) {
                    echo 'Neplatit';
                } elseif ($paidValue + 0.01 < $supplierTotal) {
                    echo 'Platit partial';
                } else {
                    echo 'Platit integral';
                }
            ?>
        </div>
        <?php if (!empty($isPlatform) && !empty($paymentOutRows)): ?>
            <div class="mt-3 space-y-1 text-xs text-slate-600">
                <?php foreach ($paymentOutRows as $row): ?>
                    <a
                        href="<?= App\Support\Url::to('admin/plati/istoric') ?>?payment_id=<?= (int) $row['id'] ?>#payment-out-<?= (int) $row['id'] ?>"
                        class="block text-blue-700 hover:text-blue-800"
                    >
                        Plata #<?= (int) $row['id'] ?> · <?= htmlspecialchars($row['paid_at'] ?? '') ?> ·
                        Alocat <?= number_format((float) ($row['alloc_amount'] ?? 0), 2, '.', ' ') ?> RON
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($isPlatform)): ?>
        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
            <div class="text-slate-500">Actiuni rapide</div>
            <div class="mt-2 flex flex-wrap gap-2">
                <a
                    href="<?= App\Support\Url::to('admin/incasari/adauga?client_cui=' . urlencode((string) ($selectedClientCui ?? ''))) ?>"
                    class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Adauga incasare
                </a>
                <a
                    href="<?= App\Support\Url::to('admin/plati/adauga?supplier_cui=' . urlencode((string) ($invoice->supplier_cui ?? ''))) ?>"
                    class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Adauga plata
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="drag-drop" class="mt-8 rounded-lg border border-slate-300 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-900">Configurare pachete</h2>
    <p class="mt-2 text-sm text-slate-600">
        Seteaza numarul de pachete pe fiecare cota TVA si muta produsele prin drag &amp; drop.
    </p>
    <?php if (!empty($isConfirmed)): ?>
        <div class="mt-3 text-sm font-semibold text-amber-700">
            Pachetele sunt confirmate si nu mai pot fi mutate.
        </div>
    <?php endif; ?>

    <?php if (empty($vatRates)): ?>
        <div class="mt-4 text-sm text-slate-500">Nu exista produse pentru a genera pachete.</div>
    <?php else: ?>
        <?php if (empty($isConfirmed)): ?>
            <form
                method="POST"
                action="<?= App\Support\Url::to('admin/facturi/pachete') ?>"
                class="mt-4 flex flex-wrap items-center gap-3"
                id="packages-form"
            >
                <?= App\Support\Csrf::input() ?>
                <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                <input type="hidden" name="action" value="generate">

                <?php foreach ($vatRates as $vatRate): ?>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-slate-600">TVA <?= htmlspecialchars($vatRate) ?>%</span>
                        <div class="inline-flex items-center gap-2 rounded border border-slate-300 bg-white px-2 py-1">
                            <button
                                type="button"
                                class="rounded border border-slate-200 px-2 py-1 text-sm text-slate-700 hover:bg-slate-100"
                                data-counter-decrement
                                data-target="vat_<?= htmlspecialchars($vatRate) ?>"
                            >−</button>
                            <input
                                id="vat_<?= htmlspecialchars($vatRate) ?>"
                                name="package_counts[<?= htmlspecialchars($vatRate) ?>]"
                                type="number"
                                min="1"
                                value="<?= (int) ($packageDefaults[$vatRate] ?? 1) ?>"
                                class="w-12 border-none text-center text-sm"
                                data-counter-input
                            >
                            <button
                                type="button"
                                class="rounded border border-slate-200 px-2 py-1 text-sm text-slate-700 hover:bg-slate-100"
                                data-counter-increment
                                data-target="vat_<?= htmlspecialchars($vatRate) ?>"
                            >+</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </form>

            <div class="mt-4 text-xs text-slate-500">
                Modificarea numarului de pachete aplica automat reorganizarea.
            </div>
        <?php else: ?>
            <div class="mt-4 text-sm text-slate-500">Pachetele sunt confirmate si nu pot fi regenerate.</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php
        $unassigned = $linesByPackage[0] ?? [];
        $unassignedCount = count($unassigned);
        $hasCommission = $commissionPercent !== null;
        $applyCommission = function (float $amount) use ($commissionPercent): ?float {
            if ($commissionPercent === null) {
                return null;
            }
            $factor = 1 + (abs($commissionPercent) / 100);
            return $commissionPercent >= 0
                ? round($amount * $factor, 2)
                : round($amount / $factor, 2);
        };
    ?>

    <div class="mt-4 grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
        <?php foreach ($packages as $package): ?>
            <?php $packageLines = $linesByPackage[$package->id] ?? []; ?>
            <?php $stat = $packageStats[$package->id] ?? null; ?>
            <?php $commissionTotal = $packageTotalsWithCommission['packages'][$package->id] ?? null; ?>
            <?php
                $packageLabelText = trim((string) ($package->label ?? ''));
                if ($packageLabelText === '') {
                    $packageLabelText = 'Pachet de produse';
                }
                $packageLabel = $packageLabelText . ' #' . $package->package_no;
            ?>
            <div
                class="rounded border border-slate-300 bg-white p-4 shadow-sm"
                data-drop-zone
                data-package-id="<?= (int) $package->id ?>"
                data-vat="<?= htmlspecialchars(number_format($package->vat_percent, 2, '.', '')) ?>"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="text-sm font-semibold text-slate-900">
                        <?= htmlspecialchars($packageLabel) ?>
                    </div>
                    <?php if (!empty($isConfirmed)): ?>
                        <form method="POST" action="<?= App\Support\Url::to('admin/facturi/saga/pachet') ?>">
                            <?= App\Support\Csrf::input() ?>
                            <input type="hidden" name="package_id" value="<?= (int) $package->id ?>">
                            <button class="text-xs font-semibold text-blue-700 hover:text-blue-800">Saga .ahk</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= App\Support\Url::to('admin/facturi/pachete') ?>">
                            <?= App\Support\Csrf::input() ?>
                            <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="package_id" value="<?= (int) $package->id ?>">
                            <button class="text-xs font-semibold text-red-600 hover:text-red-700">Sterge</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if (!empty($canRenamePackages)): ?>
                    <div class="mt-2" data-package-rename>
                        <button
                            type="button"
                            class="text-xs font-semibold text-blue-700 hover:text-blue-800"
                            data-rename-edit
                        >
                            Editeaza
                        </button>
                        <form
                            method="POST"
                            action="<?= App\Support\Url::to('admin/facturi/redenumeste-pachet') ?>"
                            class="mt-2 hidden flex-wrap items-center gap-2"
                            data-rename-form
                        >
                            <?= App\Support\Csrf::input() ?>
                            <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                            <input type="hidden" name="package_id" value="<?= (int) $package->id ?>">
                            <input
                                type="text"
                                name="label"
                                value="<?= htmlspecialchars($packageLabelText) ?>"
                                class="w-48 rounded border border-slate-200 px-2 py-1 text-xs"
                                placeholder="Denumire pachet"
                                data-rename-input
                            >
                            <span class="text-xs font-semibold text-slate-500">#<?= (int) $package->package_no ?></span>
                            <button class="rounded border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                Salveaza
                            </button>
                            <button
                                type="button"
                                class="rounded border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-50"
                                data-rename-cancel
                            >
                                Renunta
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                <div class="text-xs font-semibold text-slate-600">Cota TVA <?= number_format($package->vat_percent, 2, '.', ' ') ?>%</div>
                <?php if ($stat): ?>
                    <div class="mt-1 text-xs text-slate-600">
                        <?= (int) $stat['line_count'] ?> produse
                    </div>
                    <?php if ($commissionTotal !== null): ?>
                        <div class="mt-1 text-xs font-semibold text-slate-700">
                            Total client: <?= number_format($commissionTotal, 2, '.', ' ') ?> RON
                        </div>
                    <?php else: ?>
                        <div class="mt-1 text-xs text-slate-500">
                            Selecteaza clientul pentru a vedea preturile.
                        </div>
                    <?php endif; ?>
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
                                <div class="flex items-start justify-between gap-2">
                                    <div><?= htmlspecialchars($line->product_name) ?></div>
                                    <?php if (empty($isConfirmed) && $line->quantity > 1): ?>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100"
                                            data-split-line
                                            data-line-id="<?= (int) $line->id ?>"
                                            data-line-qty="<?= htmlspecialchars(number_format($line->quantity, 3, '.', '')) ?>"
                                            data-line-name="<?= htmlspecialchars($line->product_name) ?>"
                                            draggable="false"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16M12 4v16" />
                                            </svg>
                                            Split
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-1 text-[11px] font-normal text-slate-700">
                                    Cantitate: <?= number_format($line->quantity, 2, '.', ' ') ?> <?= htmlspecialchars($line->unit_code) ?>
                                </div>
                                <?php if ($hasCommission): ?>
                                    <div class="text-[11px] font-normal text-slate-700">
                                        Pret/buc: <?= number_format($applyCommission($line->unit_price) ?? 0, 2, '.', ' ') ?> RON
                                    </div>
                                    <div class="text-[11px] font-normal text-slate-700">
                                        Total: <?= number_format($applyCommission($line->line_total_vat) ?? 0, 2, '.', ' ') ?> RON
                                    </div>
                                <?php else: ?>
                                    <div class="text-[11px] font-normal text-slate-500">
                                        Selecteaza clientul pentru preturi.
                                    </div>
                                <?php endif; ?>
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
                        <div class="flex items-start justify-between gap-2">
                            <div><?= htmlspecialchars($line->product_name) ?></div>
                            <?php if (empty($isConfirmed) && $line->quantity > 1): ?>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100"
                                    data-split-line
                                    data-line-id="<?= (int) $line->id ?>"
                                    data-line-qty="<?= htmlspecialchars(number_format($line->quantity, 3, '.', '')) ?>"
                                    data-line-name="<?= htmlspecialchars($line->product_name) ?>"
                                    draggable="false"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16M12 4v16" />
                                    </svg>
                                    Split
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="mt-1 text-[11px] font-normal text-slate-700">
                            Cantitate: <?= number_format($line->quantity, 2, '.', ' ') ?> <?= htmlspecialchars($line->unit_code) ?>
                        </div>
                        <?php if ($hasCommission): ?>
                            <div class="text-[11px] font-normal text-slate-700">
                                Pret/buc: <?= number_format($applyCommission($line->unit_price) ?? 0, 2, '.', ' ') ?> RON
                            </div>
                            <div class="text-[11px] font-normal text-slate-700">
                                Total: <?= number_format($applyCommission($line->line_total_vat) ?? 0, 2, '.', ' ') ?> RON
                            </div>
                        <?php else: ?>
                            <div class="text-[11px] font-normal text-slate-500">
                                Selecteaza clientul pentru preturi.
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($commissionPercent) && !empty($packageTotalsWithCommission['invoice_total'])): ?>
        <div class="mt-6 rounded border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
            Total client pachete: <strong><?= number_format($packageTotalsWithCommission['invoice_total'], 2, '.', ' ') ?> RON</strong>
        </div>
    <?php endif; ?>

    <?php if (empty($isConfirmed) && !empty($packages)): ?>
        <form method="POST" action="<?= App\Support\Url::to('admin/facturi/pachete') ?>" class="mt-6 flex justify-end">
            <?= App\Support\Csrf::input() ?>
            <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
            <input type="hidden" name="action" value="confirm">
            <button class="rounded border border-blue-600 bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Confirma pachetele
            </button>
        </form>
    <?php endif; ?>

    <?php if (!empty($isConfirmed) && !empty($packages)): ?>
        <div class="mt-4 space-y-3">
            <?php if (!empty($canUnconfirmPackages)): ?>
                <div class="flex justify-end">
                    <?php if (empty($hasFgoInvoice)): ?>
                        <form method="POST" action="<?= App\Support\Url::to('admin/facturi/pachete') ?>">
                            <?= App\Support\Csrf::input() ?>
                            <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                            <input type="hidden" name="action" value="unconfirm">
                            <button
                                class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100"
                                onclick="return confirm('Sigur doresti anularea confirmarii pachetelor?')"
                            >
                                Anuleaza confirmarea
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">
                            Factura FGO este emisa. Confirmarea nu mai poate fi anulata.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="flex justify-end">
                <form method="POST" action="<?= App\Support\Url::to('admin/facturi/saga/factura') ?>">
                    <?= App\Support\Csrf::input() ?>
                    <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                    <button class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Descarca Saga (toate pachetele)
                    </button>
                </form>
            </div>
            <?php if (empty($invoice->fgo_number) && empty($invoice->fgo_series)): ?>
                <div class="flex justify-end">
                    <form method="POST" action="<?= App\Support\Url::to('admin/facturi/genereaza') ?>">
                        <?= App\Support\Csrf::input() ?>
                        <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                        <?php if (!empty($selectedClientCui)): ?>
                            <input type="hidden" name="client_cui" value="<?= htmlspecialchars($selectedClientCui) ?>">
                        <?php endif; ?>
                        <?php if (!empty($fgoSeriesOptions)): ?>
                            <div class="mb-2">
                                <label class="mb-1 block text-xs font-semibold text-slate-600" for="fgo_series_select">Serie FGO</label>
                                <select
                                    id="fgo_series_select"
                                    name="fgo_series"
                                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                                >
                                    <option value="">Alege serie</option>
                                    <?php foreach ($fgoSeriesOptions as $series): ?>
                                        <option value="<?= htmlspecialchars($series) ?>" <?= ($fgoSeriesSelected ?? '') === $series ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($series) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif (!empty($fgoSeriesSelected)): ?>
                            <input type="hidden" name="fgo_series" value="<?= htmlspecialchars($fgoSeriesSelected) ?>">
                        <?php endif; ?>
                        <button
                            class="rounded border border-blue-600 bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            <?= empty($selectedClientCui) ? 'disabled' : '' ?>
                        >
                            Genereaza factura FGO
                        </button>
                    </form>
                </div>
                <?php if (empty($selectedClientCui)): ?>
                    <div class="text-xs text-right text-slate-500">Selecteaza clientul pentru a genera factura.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="text-sm text-slate-600">
                        Factura FGO:
                        <strong><?= htmlspecialchars(trim($invoice->fgo_series . ' ' . $invoice->fgo_number)) ?></strong>
                        <?php if (!empty($invoice->fgo_storno_number)): ?>
                            <span class="ml-2 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                Stornata
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if (!empty($invoice->fgo_link)): ?>
                            <a
                                href="<?= htmlspecialchars($invoice->fgo_link) ?>"
                                target="_blank"
                                class="inline-flex items-center gap-2 rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                                    <path d="M14 3v6h6" />
                                </svg>
                                Factura <?= htmlspecialchars(trim($invoice->fgo_series . ' ' . $invoice->fgo_number)) ?>
                            </a>
                        <?php endif; ?>
                        <a
                            href="<?= App\Support\Url::to('admin/facturi/anexa?invoice_id=' . (int) $invoice->id) ?>"
                            target="_blank"
                            class="inline-flex items-center gap-2 rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                                <path d="M14 3v6h6" />
                            </svg>
                            Afiseaza anexa
                        </a>
                        <a
                            href="<?= App\Support\Url::to('admin/facturi/nota-comanda?invoice_id=' . (int) $invoice->id) ?>"
                            target="_blank"
                            class="inline-flex items-center gap-2 rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                                <path d="M14 3v6h6" />
                                <path d="M9 14h6" />
                                <path d="M9 18h6" />
                            </svg>
                            Nota de comanda
                        </a>
                        <?php if (empty($invoice->fgo_storno_number)): ?>
                            <form method="POST" action="<?= App\Support\Url::to('admin/facturi/storno') ?>">
                                <?= App\Support\Csrf::input() ?>
                                <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                                <button
                                    class="inline-flex items-center gap-2 rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-100"
                                    onclick="return confirm('Sigur vrei sa stornezi factura FGO?')"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M12 5v4l3-3M12 5a7 7 0 1 1-6.33 4" />
                                    </svg>
                                    Storneaza
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?= App\Support\Url::to('admin/facturi/print-storno') ?>" target="_blank">
                                <?= App\Support\Csrf::input() ?>
                                <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                                <button class="inline-flex items-center gap-2 rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                                        <path d="M14 3v6h6" />
                                        <path d="M8 14h8" />
                                    </svg>
                                    PDF Storno <?= htmlspecialchars(trim($invoice->fgo_storno_series . ' ' . $invoice->fgo_storno_number)) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form id="move-line-form" method="POST" action="<?= App\Support\Url::to('admin/facturi/muta-linie') ?>" class="hidden">
        <?= App\Support\Csrf::input() ?>
        <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
        <input type="hidden" name="line_id" value="">
        <input type="hidden" name="package_id" value="">
    </form>
</div>

<?php if (!empty($isPlatform)): ?>
    <div class="mt-8 flex flex-wrap items-center gap-3">
        <form method="POST" action="<?= App\Support\Url::to('admin/facturi/sterge') ?>">
            <?= App\Support\Csrf::input() ?>
            <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
            <button
                type="submit"
                class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100"
                onclick="return confirm('Sigur vrei sa stergi factura de intrare?')"
            >
                Sterge factura
            </button>
        </form>
        <div class="text-sm text-slate-500">Stergerea elimina si pachetele si produsele importate.</div>
    </div>
<?php endif; ?>

<?php if (!empty($isSupplierUser) && !empty($isConfirmed) && empty($invoice->xml_path)): ?>
    <div id="supplier-request" class="mt-8 rounded-lg border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
        <div class="text-base font-semibold text-blue-900">Solicita generare factura</div>
        <p class="mt-1 text-sm text-blue-800/80">Incarca factura furnizorului (XML/PDF) pentru a continua.</p>
        <form
            method="POST"
            action="<?= App\Support\Url::to('admin/facturi/incarca-fisier') ?>"
            enctype="multipart/form-data"
            class="mt-4 flex flex-wrap items-center gap-3"
            data-supplier-upload
        >
            <?= App\Support\Csrf::input() ?>
            <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
            <input
                id="supplier_file_upload"
                type="file"
                name="supplier_file"
                accept=".xml,.pdf"
                class="sr-only"
                required
                data-upload-input
            >
            <label
                for="supplier_file_upload"
                class="inline-flex items-center gap-2 rounded border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-800 hover:bg-blue-100"
            >
                <span>Alege fisier</span>
                <span class="text-slate-500" data-upload-name>Nicio selectie</span>
            </label>
            <button
                class="rounded border border-blue-700 bg-blue-700 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
                disabled
                data-upload-submit
            >
                Solicita generare factura
            </button>
        </form>
    </div>
<?php endif; ?>

<div id="split-modal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div id="split-overlay" class="absolute inset-0 bg-slate-900/50"></div>
    <div class="relative z-10 w-full max-w-md rounded-lg bg-white p-4 shadow-lg">
        <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-slate-900">Separare produs</div>
            <button
                type="button"
                id="split-close"
                class="rounded border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50"
            >
                Inchide
            </button>
        </div>
        <form method="POST" action="<?= App\Support\Url::to('admin/facturi/split-linie') ?>" class="mt-4 space-y-3">
            <?= App\Support\Csrf::input() ?>
            <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
            <input type="hidden" name="line_id" id="split-line-id" value="">

            <div class="text-xs text-slate-600">
                Produs: <strong id="split-line-name"></strong>
            </div>
            <div class="text-xs text-slate-600">
                Cantitate curenta: <strong id="split-line-qty"></strong>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600" for="split-qty">Cantitate separata</label>
                <input
                    id="split-qty"
                    name="split_qty"
                    type="number"
                    step="0.001"
                    min="0.001"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    required
                >
                <div class="mt-1 text-xs text-slate-500">
                    Ramane: <span id="split-remaining"></span>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                    onclick="document.getElementById('split-close').click()"
                >
                    Renunta
                </button>
                <button class="rounded border border-blue-600 bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                    Split
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const searchInput = document.getElementById('client-search');
        if (searchInput) {
            const rows = Array.from(document.querySelectorAll('[data-client-row]'));
            const form = document.getElementById('client-form');
            const locked = form && form.dataset.clientLocked === '1';
            const filterRows = () => {
                const value = searchInput.value.trim().toLowerCase();
                rows.forEach((row) => {
                    const haystack = row.dataset.search || '';
                    row.style.display = haystack.includes(value) ? '' : 'none';
                });
            };
            searchInput.addEventListener('input', filterRows);
            filterRows();

            if (!locked) {
                rows.forEach((row) => {
                    row.addEventListener('click', (event) => {
                        const radio = row.querySelector('input[type="radio"]');
                        if (radio) {
                            radio.checked = true;
                            if (form) {
                                form.submit();
                            }
                        }
                    });
                });

                rows.forEach((row) => {
                    const radio = row.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.addEventListener('change', () => {
                            if (form) {
                                form.submit();
                            }
                        });
                    }
                });
            }
        }

        const renameBlocks = Array.from(document.querySelectorAll('[data-package-rename]'));
        renameBlocks.forEach((block) => {
            const editButton = block.querySelector('[data-rename-edit]');
            const form = block.querySelector('[data-rename-form]');
            const cancelButton = block.querySelector('[data-rename-cancel]');
            const input = block.querySelector('[data-rename-input]');
            if (!editButton || !form) {
                return;
            }
            const initialValue = input ? input.value : '';
            editButton.addEventListener('click', () => {
                form.classList.remove('hidden');
                form.classList.add('flex');
                editButton.classList.add('hidden');
            });
            if (cancelButton) {
                cancelButton.addEventListener('click', () => {
                    if (input) {
                        input.value = initialValue;
                    }
                    form.classList.add('hidden');
                    form.classList.remove('flex');
                    editButton.classList.remove('hidden');
                });
            }
        });

        const uploadForm = document.querySelector('[data-supplier-upload]');
        if (uploadForm) {
            const uploadInput = uploadForm.querySelector('[data-upload-input]');
            const uploadName = uploadForm.querySelector('[data-upload-name]');
            const uploadSubmit = uploadForm.querySelector('[data-upload-submit]');
            const updateUpload = () => {
                const hasFile = uploadInput && uploadInput.files && uploadInput.files.length > 0;
                if (uploadName) {
                    uploadName.textContent = hasFile ? uploadInput.files[0].name : 'Nicio selectie';
                }
                if (uploadSubmit) {
                    uploadSubmit.disabled = !hasFile;
                }
            };
            if (uploadInput) {
                uploadInput.addEventListener('change', updateUpload);
                updateUpload();
            }
        }

        const params = new URLSearchParams(window.location.search);
        const anchor = params.get('anchor');
        if (anchor) {
            const el = document.getElementById(anchor);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        const decButtons = document.querySelectorAll('[data-counter-decrement]');
        const incButtons = document.querySelectorAll('[data-counter-increment]');
        const counterInputs = document.querySelectorAll('[data-counter-input]');
        const packagesForm = document.getElementById('packages-form');

        const submitPackages = () => {
            if (packagesForm) {
                packagesForm.submit();
            }
        };

        const updateCounter = (id, delta) => {
            const input = document.getElementById(id);
            if (!input) {
                return;
            }
            const current = parseInt(input.value || '1', 10);
            const next = Math.max(1, current + delta);
            input.value = String(next);
            submitPackages();
        };

        decButtons.forEach((button) => {
            button.addEventListener('click', () => {
                updateCounter(button.dataset.target, -1);
            });
        });

        incButtons.forEach((button) => {
            button.addEventListener('click', () => {
                updateCounter(button.dataset.target, 1);
            });
        });

        counterInputs.forEach((input) => {
            input.addEventListener('change', () => {
                submitPackages();
            });
        });

        const splitModal = document.getElementById('split-modal');
        const splitOverlay = document.getElementById('split-overlay');
        const splitClose = document.getElementById('split-close');
        const splitLineId = document.getElementById('split-line-id');
        const splitLineName = document.getElementById('split-line-name');
        const splitLineQty = document.getElementById('split-line-qty');
        const splitQtyInput = document.getElementById('split-qty');
        const splitRemaining = document.getElementById('split-remaining');

        const updateRemaining = (total, value) => {
            if (!splitRemaining) {
                return;
            }
            const remaining = Math.max(0, total - value);
            splitRemaining.textContent = remaining.toFixed(3).replace(/\.?0+$/, '');
        };

        const openSplit = (button) => {
            if (!splitModal || !splitQtyInput || !splitLineId || !splitLineQty || !splitLineName) {
                return;
            }
            const qty = parseFloat(button.dataset.lineQty || '0');
            const name = button.dataset.lineName || '';
            const lineId = button.dataset.lineId || '';
            if (!qty || qty <= 1) {
                return;
            }

            const maxQty = Math.max(0.001, qty - 0.001);
            const defaultQty = Math.round((qty / 2) * 1000) / 1000;
            const initial = Math.min(defaultQty, maxQty);

            splitLineId.value = lineId;
            splitLineName.textContent = name;
            splitLineQty.textContent = qty.toFixed(3).replace(/\.?0+$/, '');
            splitQtyInput.value = initial.toFixed(3).replace(/\.?0+$/, '');
            splitQtyInput.max = maxQty.toFixed(3);
            updateRemaining(qty, initial);

            splitModal.classList.remove('hidden');
            splitModal.classList.add('flex');
            splitQtyInput.focus();
            splitQtyInput.select();
        };

        const closeSplit = () => {
            if (!splitModal) {
                return;
            }
            splitModal.classList.add('hidden');
            splitModal.classList.remove('flex');
        };

        document.querySelectorAll('[data-split-line]').forEach((button) => {
            button.addEventListener('click', () => openSplit(button));
        });

        if (splitQtyInput) {
            splitQtyInput.addEventListener('input', () => {
                const total = parseFloat(splitLineQty.textContent || '0');
                let value = parseFloat(splitQtyInput.value || '0');
                if (!Number.isFinite(value)) {
                    value = 0;
                }
                const maxQty = Math.max(0.001, total - 0.001);
                if (value > maxQty) {
                    value = maxQty;
                    splitQtyInput.value = value.toFixed(3).replace(/\.?0+$/, '');
                }
                if (value < 0) {
                    value = 0;
                    splitQtyInput.value = '';
                }
                updateRemaining(total, value);
            });
        }

        if (splitClose) {
            splitClose.addEventListener('click', closeSplit);
        }
        if (splitOverlay) {
            splitOverlay.addEventListener('click', closeSplit);
        }

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
