<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Situatie facturi</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Inter", "Segoe UI", Arial, sans-serif; color: #0f172a; font-size: 12px; line-height: 1.3; }
        .page { padding: 24px; }
        .header { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .header-left { display: flex; align-items: center; gap: 16px; }
        .logo { max-height: 48px; }
        .company { font-size: 12px; color: #334155; line-height: 1.3; }
        .title { margin-top: 16px; font-size: 18px; font-weight: 700; }
        .subtitle { margin-top: 4px; font-size: 12px; color: #475569; }
        .actions { margin-top: 12px; display: flex; gap: 8px; }
        .btn { border: 1px solid #cbd5f5; background: #2563eb; color: #fff; padding: 8px 12px; font-size: 12px; font-weight: 600; border-radius: 8px; cursor: pointer; }
        .btn.secondary { background: #fff; color: #2563eb; border-color: #cbd5f5; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 11px; }
        thead th { text-align: left; padding: 6px 8px; background: #f1f5f9; border: 1px solid #e2e8f0; }
        tbody td { padding: 6px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .muted { color: #64748b; }
        .no-print { display: block; }
        @media print {
            .no-print { display: none !important; }
            .page { padding: 12px; }
            body { color: #000; font-size: 11px; }
            table { font-size: 10px; margin-top: 8px; }
            thead th, tbody td { padding: 4px 6px; }
            .title { margin-top: 12px; font-size: 16px; }
            .subtitle { font-size: 11px; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="header-left">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <div class="company">
                <div><strong><?= htmlspecialchars($company['denumire'] ?? '') ?></strong></div>
                <?php if (!empty($company['cui'])): ?>
                    <div>CUI: <?= htmlspecialchars($company['cui']) ?></div>
                <?php endif; ?>
                <?php if (!empty($company['nr_reg_comertului'])): ?>
                    <div>Nr. Reg. Comertului: <?= htmlspecialchars($company['nr_reg_comertului']) ?></div>
                <?php endif; ?>
                <?php if (!empty($company['adresa'])): ?>
                    <div><?= htmlspecialchars($company['adresa']) ?></div>
                <?php endif; ?>
                <?php if (!empty($company['localitate']) || !empty($company['judet']) || !empty($company['tara'])): ?>
                    <div>
                        <?= htmlspecialchars(trim(($company['localitate'] ?? '') . ' ' . ($company['judet'] ?? '') . ' ' . ($company['tara'] ?? ''))) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($company['email']) || !empty($company['telefon'])): ?>
                    <div>
                        <?= htmlspecialchars(trim(($company['email'] ?? '') . ' ' . ($company['telefon'] ?? ''))) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="title"><?= htmlspecialchars($titleText ?? 'Situatie facturi') ?></div>
    <div class="subtitle">Situatie la data <?= htmlspecialchars($printedAt ?? '') ?></div>

    <div class="actions no-print">
        <button class="btn" onclick="window.print()">Printeaza</button>
        <button class="btn secondary" onclick="window.print()">Salveaza PDF</button>
    </div>

    <table>
        <thead>
            <tr>
                <th>Furnizor</th>
                <th>Factura furnizor</th>
                <th>Data factura furnizor</th>
                <th>Total factura furnizor</th>
                <th>Client final</th>
                <th>Total factura client</th>
                <th>Incasare client</th>
                <th>Plata furnizor</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="8" class="muted">Nu exista facturi pentru criteriile selectate.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): ?>
                    <?php
                        $status = $invoiceStatuses[$invoice->id] ?? null;
                        $clientTotal = $status['client_total'] ?? null;
                        $supplierInvoice = trim((string) ($invoice->invoice_series ?? '') . ' ' . (string) ($invoice->invoice_no ?? ''));
                        if ($supplierInvoice === '') {
                            $supplierInvoice = (string) ($invoice->invoice_number ?? '');
                        }
                        $clientFinal = $clientFinals[$invoice->id] ?? ['name' => '', 'cui' => ''];
                        $clientLabel = $clientFinal['name'] !== '' ? $clientFinal['name'] : '—';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($invoice->supplier_name) ?></td>
                        <td><?= htmlspecialchars($supplierInvoice !== '' ? $supplierInvoice : '—') ?></td>
                        <td><?= htmlspecialchars($invoice->issue_date) ?></td>
                        <td><?= number_format((float) $invoice->total_with_vat, 2, '.', ' ') ?></td>
                        <td><?= htmlspecialchars($clientLabel) ?></td>
                        <td><?= $clientTotal !== null ? number_format($clientTotal, 2, '.', ' ') : '—' ?></td>
                        <td>
                            <?php if ($status && $status['client_total'] !== null): ?>
                                <?= number_format($status['collected'], 2, '.', ' ') ?> / <?= number_format($status['client_total'], 2, '.', ' ') ?>
                                <div class="muted"><?= htmlspecialchars($status['client_label']) ?></div>
                            <?php else: ?>
                                <span class="muted">Client nesetat</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($status): ?>
                                <?= number_format($status['paid'], 2, '.', ' ') ?> / <?= number_format((float) $invoice->total_with_vat, 2, '.', ' ') ?>
                                <div class="muted"><?= htmlspecialchars($status['supplier_label']) ?></div>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
