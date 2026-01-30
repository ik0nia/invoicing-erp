<?php
    $fgoSeries = $invoice->fgo_series ?: $invoice->invoice_series;
    $fgoNumber = $invoice->fgo_number ?: $invoice->invoice_no ?: $invoice->invoice_number;
    $reference = trim($fgoSeries . ' ' . $fgoNumber);
    $title = 'Anexa factura ' . $reference;
?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <?php
                $dateDisplay = $invoice->issue_date ? date('d.m.Y', strtotime($invoice->issue_date)) : '';
            ?>
            <h1 class="text-xl font-semibold text-slate-900">
                Anexa la factura, <?= htmlspecialchars($reference) ?> din data de <?= htmlspecialchars($dateDisplay) ?>
            </h1>
            <p class="mt-1 text-xs font-semibold text-slate-600">DOCUMENT NEFISCAL</p>
        </div>
        <a
            href="<?= App\Support\Url::to('admin/facturi?invoice_id=' . (int) $invoice->id) ?>"
            class="no-print rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
        >
            Inapoi la factura
        </a>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded border border-slate-200 bg-slate-50 p-4 text-sm">
            <div class="text-xs font-semibold text-slate-600">Expeditor</div>
            <?php if (!empty($company['logo_url'])): ?>
                <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="Logo" class="mt-2 h-12 w-auto">
            <?php endif; ?>
            <div class="mt-2 font-semibold text-slate-900"><?= htmlspecialchars($company['denumire'] ?? '') ?></div>
            <div class="mt-2 space-y-1 text-slate-600">
                <div>CUI: <?= htmlspecialchars($company['cui'] ?? '') ?></div>
                <div>Nr. Reg. Comertului: <?= htmlspecialchars($company['nr_reg_comertului'] ?? '') ?></div>
                <div>Adresa: <?= htmlspecialchars($company['adresa'] ?? '') ?>, <?= htmlspecialchars($company['localitate'] ?? '') ?>, <?= htmlspecialchars($company['judet'] ?? '') ?></div>
                <div>Tara: <?= htmlspecialchars($company['tara'] ?? '') ?></div>
                <div>Telefon: <?= htmlspecialchars($company['telefon'] ?? '') ?></div>
                <div>Email: <?= htmlspecialchars($company['email'] ?? '') ?></div>
                <div>Banca: <?= htmlspecialchars($company['banca'] ?? '') ?></div>
                <div>IBAN: <?= htmlspecialchars($company['iban'] ?? '') ?></div>
            </div>
        </div>
        <div class="rounded border border-slate-200 bg-slate-50 p-4 text-sm">
            <div class="text-xs font-semibold text-slate-600">Destinatar</div>
            <div class="mt-1 font-semibold text-slate-900"><?= htmlspecialchars($clientName ?: $clientCui) ?></div>
            <div class="mt-2 space-y-1 text-slate-600">
                <div>CUI: <?= htmlspecialchars($clientCui) ?></div>
                <?php if (!empty($clientCompany)): ?>
                    <div>Nr. Reg. Comertului: <?= htmlspecialchars($clientCompany->nr_reg_comertului ?? '') ?></div>
                    <div>Adresa: <?= htmlspecialchars($clientCompany->adresa ?? '') ?>, <?= htmlspecialchars($clientCompany->localitate ?? '') ?>, <?= htmlspecialchars($clientCompany->judet ?? '') ?></div>
                    <div>Tara: <?= htmlspecialchars($clientCompany->tara ?? '') ?></div>
                    <div>Telefon: <?= htmlspecialchars($clientCompany->telefon ?? '') ?></div>
                    <div>Email: <?= htmlspecialchars($clientCompany->email ?? '') ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 space-y-6">
        <?php foreach ($packages as $package): ?>
            <?php
                $stat = $packageStats[$package->id] ?? ['line_count' => 0, 'total' => 0];
                $lines = $linesByPackage[$package->id] ?? [];
            ?>
            <div class="rounded border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-3">
                    <div class="text-sm font-semibold text-slate-900">
                        <?= htmlspecialchars($package->label ?: 'Pachet #' . $package->package_no) ?>
                    </div>
                    <div class="text-xs text-slate-600">
                        Total pachet: <strong><?= number_format((float) ($stat['total'] ?? 0), 2, '.', ' ') ?> RON</strong>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full table-fixed text-left text-sm">
                        <colgroup>
                            <col style="width: 44%">
                            <col style="width: 10%">
                            <col style="width: 8%">
                            <col style="width: 12%">
                            <col style="width: 16%">
                            <col style="width: 10%">
                        </colgroup>
                        <thead class="bg-white text-slate-600">
                            <tr>
                                <th class="px-4 py-2">Produs</th>
                                <th class="px-4 py-2">Cantitate</th>
                                <th class="px-4 py-2">UM</th>
                                <th class="px-4 py-2">Pret/buc (fara TVA)</th>
                                <th class="px-4 py-2">Total (fara TVA)</th>
                                <th class="px-4 py-2">TVA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lines as $line): ?>
                                <tr class="border-t border-slate-100">
                                    <td class="px-4 py-2 break-words"><?= htmlspecialchars($line->product_name) ?></td>
                                    <td class="px-4 py-2"><?= number_format($line->quantity, 2, '.', ' ') ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($line->unit_code) ?></td>
                                    <td class="px-4 py-2"><?= number_format($line->unit_price, 2, '.', ' ') ?></td>
                                    <td class="px-4 py-2"><?= number_format($line->line_total, 2, '.', ' ') ?></td>
                                    <td class="px-4 py-2"><?= number_format($line->tax_percent, 2, '.', ' ') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($lines)): ?>
                                <tr class="border-t border-slate-100">
                                    <td colspan="6" class="px-4 py-3 text-sm text-slate-500">Nu exista produse in acest pachet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-6 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
        <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
            Total fara TVA: <strong><?= number_format($totalWithout, 2, '.', ' ') ?> RON</strong>
        </div>
        <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3">
            Total cu TVA: <strong><?= number_format($totalWith, 2, '.', ' ') ?> RON</strong>
        </div>
    </div>
</div>
