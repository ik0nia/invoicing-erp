<?php
    $fgoSeries = $invoice->fgo_series ?: $invoice->invoice_series;
    $fgoNumber = $invoice->fgo_number ?: $invoice->invoice_no ?: $invoice->invoice_number;
    $reference = trim($fgoSeries . ' ' . $fgoNumber);
    $title = 'Anexa factura ' . $reference;
    $company = is_array($company ?? null) ? $company : [];
    $companyName = trim((string) ($company['denumire'] ?? ''));
    $companyType = trim((string) ($company['tip_firma'] ?? ''));
    $companyDisplayName = trim($companyName . ($companyType !== '' ? ' - ' . $companyType : ''));
    $companyEmail = trim((string) ($company['email'] ?? ''));
    $companyPhone = trim((string) ($company['telefon'] ?? ''));
    $companyLogo = trim((string) ($company['logo_data_uri'] ?? ($company['logo_url'] ?? '')));
?>

<style>
    .aviz-compact {
        font-size: 11px;
        line-height: 1.25;
    }
    .aviz-compact .aviz-package {
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .aviz-compact .aviz-table {
        font-size: 11px;
        line-height: 1.15;
        width: 100%;
        table-layout: auto !important;
    }
    .aviz-compact .aviz-table th,
    .aviz-compact .aviz-table td {
        padding: 2px 4px;
        vertical-align: top;
    }
    .aviz-compact .aviz-table .col-nr {
        width: 18px;
        min-width: 18px;
        max-width: 18px;
        padding-left: 2px;
        padding-right: 2px;
        text-align: center;
        white-space: nowrap;
    }
    .aviz-compact .aviz-table .col-product { width: auto; }
    .aviz-compact .aviz-table .col-qty { width: 66px; white-space: nowrap; text-align: right; }
    .aviz-compact .aviz-table .col-um { width: 44px; white-space: nowrap; text-align: center; }
    .aviz-compact .aviz-table .col-price { width: 90px; white-space: nowrap; text-align: right; }
    .aviz-compact .aviz-table .col-total { width: 98px; white-space: nowrap; text-align: right; }
    .aviz-compact .aviz-table .col-vat { width: 60px; white-space: nowrap; text-align: right; }
    .aviz-compact .aviz-company-logo {
        max-height: 56px;
        width: auto;
        max-width: 170px;
        object-fit: contain;
    }
</style>

<div class="aviz-compact rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-2">
        <div class="flex flex-wrap items-start gap-3">
            <?php if ($companyLogo !== ''): ?>
                <img class="aviz-company-logo" src="<?= htmlspecialchars($companyLogo) ?>" alt="Logo companie">
            <?php endif; ?>
            <div class="text-xs text-slate-700">
                <?php if ($companyDisplayName !== ''): ?>
                    <div class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($companyDisplayName) ?></div>
                <?php endif; ?>
                <div class="mt-1">
                    <?php if ($companyEmail !== ''): ?>
                        <span>Email: <?= htmlspecialchars($companyEmail) ?></span>
                    <?php endif; ?>
                    <?php if ($companyPhone !== ''): ?>
                        <span<?= $companyEmail !== '' ? ' class="ml-2"' : '' ?>>Tel: <?= htmlspecialchars($companyPhone) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div>
            <?php
                $dateDisplay = $invoice->issue_date ? date('d.m.Y', strtotime($invoice->issue_date)) : '';
            ?>
            <h1 class="text-lg font-semibold text-slate-900">
                ANEXA - Factura <?= htmlspecialchars($reference) ?> / DATA <?= htmlspecialchars($dateDisplay) ?>
            </h1>
            <p class="mt-1 text-xs font-semibold text-slate-600">DOCUMENT NEFISCAL</p>
            <p class="mt-2 text-xs text-slate-600">
                Client: <strong><?= htmlspecialchars($clientName ?: $clientCui) ?></strong>
                <?php if (!empty($clientCui)): ?>
                    (CUI: <?= htmlspecialchars($clientCui) ?>)
                <?php endif; ?>
            </p>
            <div class="mt-1 text-right">
                <a
                    href="<?= App\Support\Url::to('admin/facturi?invoice_id=' . (int) $invoice->id) ?>"
                    class="no-print rounded border border-slate-200 px-2 py-1 text-xs text-slate-600 hover:text-slate-900"
                >
                    Inapoi la factura
                </a>
            </div>
        </div>
    </div>

    <?php
        $commissionPercent = $commissionPercent ?? null;
        $applyCommission = function (float $amount) use ($commissionPercent): float {
            if ($commissionPercent === null) {
                return $amount;
            }
            $factor = 1 + (abs($commissionPercent) / 100);
            return $commissionPercent >= 0
                ? round($amount * $factor, 2)
                : round($amount / $factor, 2);
        };
    ?>

    <div class="mt-3 space-y-2">
        <?php foreach ($packages as $package): ?>
            <?php
                $stat = $packageStats[$package->id] ?? ['line_count' => 0, 'total' => 0];
                $lines = $linesByPackage[$package->id] ?? [];
                $packageTotal = $applyCommission((float) ($stat['total'] ?? 0));
            ?>
            <div class="aviz-package rounded border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-2 py-1">
                    <?php
                        $labelText = trim((string) ($package->label ?? ''));
                        if ($labelText === '') {
                            $labelText = 'Pachet de produse';
                        }
                        $label = $labelText . ' #' . $package->package_no;
                    ?>
                    <div class="text-xs font-semibold text-slate-900">
                        <?= htmlspecialchars($label) ?>
                    </div>
                    <div class="text-xs text-slate-600">
                        Total pachet: <strong><?= number_format($packageTotal, 2, '.', ' ') ?> RON</strong>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="aviz-table w-full border border-slate-300 text-left text-xs">
                        <thead class="bg-white text-slate-600 border-b border-slate-300">
                            <tr>
                                <th class="col-nr">Nr</th>
                                <th class="col-product">Produs</th>
                                <th class="col-qty">Cantitate</th>
                                <th class="col-um">UM</th>
                                <th class="col-price">Pret/buc (fara TVA)</th>
                                <th class="col-total">Total (fara TVA)</th>
                                <th class="col-vat">TVA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $index = 1; ?>
                            <?php foreach ($lines as $line): ?>
                                <?php
                                    $unitPrice = $applyCommission((float) $line->unit_price);
                                    $lineTotal = $applyCommission((float) $line->line_total);
                                ?>
                                <tr class="border-t border-slate-300">
                                    <td class="col-nr"><?= $index ?></td>
                                    <td class="col-product break-words"><?= htmlspecialchars($line->product_name) ?></td>
                                    <td class="col-qty"><?= number_format($line->quantity, 2, '.', ' ') ?></td>
                                    <td class="col-um"><?= htmlspecialchars($line->unit_code) ?></td>
                                    <td class="col-price"><?= number_format($unitPrice, 2, '.', ' ') ?></td>
                                    <td class="col-total"><?= number_format($lineTotal, 2, '.', ' ') ?></td>
                                    <td class="col-vat"><?= number_format($line->tax_percent, 2, '.', ' ') ?>%</td>
                                </tr>
                                <?php $index++; ?>
                            <?php endforeach; ?>
                            <?php if (empty($lines)): ?>
                                <tr class="border-t border-slate-100">
                                    <td colspan="7" class="text-xs text-slate-500">Nu exista produse in acest pachet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-3 grid gap-2 text-xs text-slate-700 md:grid-cols-2">
        <div class="rounded border border-slate-200 bg-slate-50 px-2 py-1">
            Total fara TVA: <strong><?= number_format($totalWithout, 2, '.', ' ') ?> RON</strong>
        </div>
        <div class="rounded border border-slate-200 bg-slate-50 px-2 py-1">
            Total cu TVA: <strong><?= number_format($totalWith, 2, '.', ' ') ?> RON</strong>
        </div>
    </div>
</div>
