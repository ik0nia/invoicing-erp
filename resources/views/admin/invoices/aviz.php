<?php
    $fgoSeries = $invoice->fgo_series ?: $invoice->invoice_series;
    $fgoNumber = $invoice->fgo_number ?: $invoice->invoice_no ?: $invoice->invoice_number;
    $reference = trim($fgoSeries . ' ' . $fgoNumber);
    $title = 'Anexa factura ' . $reference;
?>

<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <?php
                $dateDisplay = $invoice->issue_date ? date('d.m.Y', strtotime($invoice->issue_date)) : '';
            ?>
            <h1 class="text-xl font-semibold text-slate-900">
                Anexa la factura, <?= htmlspecialchars($reference) ?> din data de <?= htmlspecialchars($dateDisplay) ?>
            </h1>
            <p class="mt-1 text-xs font-semibold text-slate-600">DOCUMENT NEFISCAL</p>
            <p class="mt-2 text-xs text-slate-600">
                Client: <strong><?= htmlspecialchars($clientName ?: $clientCui) ?></strong>
                <?php if (!empty($clientCui)): ?>
                    (CUI: <?= htmlspecialchars($clientCui) ?>)
                <?php endif; ?>
            </p>
        </div>
        <a
            href="<?= App\Support\Url::to('admin/facturi?invoice_id=' . (int) $invoice->id) ?>"
            class="no-print rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
        >
            Inapoi la factura
        </a>
    </div>

    <div class="mt-4 space-y-4">
        <?php foreach ($packages as $package): ?>
            <?php
                $stat = $packageStats[$package->id] ?? ['line_count' => 0, 'total' => 0];
                $lines = $linesByPackage[$package->id] ?? [];
            ?>
            <div class="rounded border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-3 py-2">
                    <div class="text-xs font-semibold text-slate-900">
                        <?= htmlspecialchars($package->label ?: 'Pachet #' . $package->package_no) ?>
                    </div>
                    <div class="text-xs text-slate-600">
                        Total pachet: <strong><?= number_format((float) ($stat['total'] ?? 0), 2, '.', ' ') ?> RON</strong>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full table-fixed text-left text-xs">
                        <colgroup>
                            <col style="width: 6%">
                            <col style="width: 40%">
                            <col style="width: 10%">
                            <col style="width: 8%">
                            <col style="width: 12%">
                            <col style="width: 16%">
                            <col style="width: 8%">
                        </colgroup>
                        <thead class="bg-white text-slate-600">
                            <tr>
                                <th class="px-2 py-1">Nr</th>
                                <th class="px-2 py-1">Produs</th>
                                <th class="px-2 py-1">Cantitate</th>
                                <th class="px-2 py-1">UM</th>
                                <th class="px-2 py-1">Pret/buc (fara TVA)</th>
                                <th class="px-2 py-1">Total (fara TVA)</th>
                                <th class="px-2 py-1">TVA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $index = 1; ?>
                            <?php foreach ($lines as $line): ?>
                                <tr class="border-t border-slate-100">
                                    <td class="px-2 py-1"><?= $index ?></td>
                                    <td class="px-2 py-1 break-words"><?= htmlspecialchars($line->product_name) ?></td>
                                    <td class="px-2 py-1"><?= number_format($line->quantity, 2, '.', ' ') ?></td>
                                    <td class="px-2 py-1"><?= htmlspecialchars($line->unit_code) ?></td>
                                    <td class="px-2 py-1"><?= number_format($line->unit_price, 2, '.', ' ') ?></td>
                                    <td class="px-2 py-1"><?= number_format($line->line_total, 2, '.', ' ') ?></td>
                                    <td class="px-2 py-1"><?= number_format($line->tax_percent, 2, '.', ' ') ?>%</td>
                                </tr>
                                <?php $index++; ?>
                            <?php endforeach; ?>
                            <?php if (empty($lines)): ?>
                                <tr class="border-t border-slate-100">
                                    <td colspan="7" class="px-2 py-2 text-xs text-slate-500">Nu exista produse in acest pachet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 grid gap-2 text-xs text-slate-700 md:grid-cols-2">
        <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
            Total fara TVA: <strong><?= number_format($totalWithout, 2, '.', ' ') ?> RON</strong>
        </div>
        <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
            Total cu TVA: <strong><?= number_format($totalWith, 2, '.', ' ') ?> RON</strong>
        </div>
    </div>
</div>
