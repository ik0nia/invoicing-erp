<?php
    $data = $data ?? null;
    $error = $error ?? null;
    $notice = $notice ?? null;
    $invoiceNumber = $data['invoice_number'] ?? (isset($invoice) ? ($invoice->invoice_number ?? '') : '');
    $issueDate = $data['issue_date'] ?? '';
    $dueDate = $data['due_date'] ?? '';
    $currency = $data['currency'] ?? 'RON';
    $lines = $data['lines'] ?? [];

    $formatDate = static function (?string $value): string {
        if (!$value) {
            return '—';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }
        return date('d.m.Y', $ts);
    };

    $formatMoney = static function ($value) use ($currency): string {
        $amount = is_numeric($value) ? (float) $value : 0.0;
        return number_format($amount, 2, '.', ' ') . ' ' . $currency;
    };
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura furnizor</title>
    <style>
        body { font-family: "Inter", "Segoe UI", Arial, sans-serif; margin: 0; padding: 24px; background: #f8fafc; color: #0f172a; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06); }
        .title { font-size: 18px; font-weight: 700; margin-bottom: 12px; }
        .subtitle { font-size: 13px; color: #64748b; margin-bottom: 16px; }
        .grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .value { font-size: 14px; font-weight: 600; color: #0f172a; margin-top: 4px; }
        .totals { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 8px 6px; text-align: left; vertical-align: top; }
        th { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .right { text-align: right; }
        .muted { color: #94a3b8; }
        .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 10px; font-size: 13px; }
        .notice { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 12px; border-radius: 10px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">Factura furnizor<?= $invoiceNumber !== '' ? ' #' . htmlspecialchars($invoiceNumber) : '' ?></div>
        <div class="subtitle">Vizualizare detalii factura furnizor.</div>

        <?php if ($notice): ?>
            <div class="notice">
                <?= htmlspecialchars($notice) ?>
            </div>
        <?php endif; ?>

        <?php if ($error || !$data): ?>
            <div class="error">
                Nu am putut interpreta fisierul XML. <?= $error ? htmlspecialchars($error) : '' ?>
            </div>
        <?php else: ?>
            <div class="grid">
                <div>
                    <div class="label">Furnizor</div>
                    <div class="value"><?= htmlspecialchars((string) ($data['supplier_name'] ?? '—')) ?></div>
                    <div class="muted">CUI: <?= htmlspecialchars((string) ($data['supplier_cui'] ?? '—')) ?></div>
                </div>
                <div>
                    <div class="label">Client</div>
                    <div class="value"><?= htmlspecialchars((string) ($data['customer_name'] ?? '—')) ?></div>
                    <div class="muted">CUI: <?= htmlspecialchars((string) ($data['customer_cui'] ?? '—')) ?></div>
                </div>
                <div>
                    <div class="label">Detalii factura</div>
                    <div class="value">Nr: <?= htmlspecialchars((string) ($data['invoice_number'] ?? '—')) ?></div>
                    <div class="muted">Data: <?= htmlspecialchars($formatDate($issueDate)) ?></div>
                    <div class="muted">Scadenta: <?= htmlspecialchars($formatDate($dueDate)) ?></div>
                </div>
            </div>

            <div class="totals">
                <div>
                    <div class="label">Total fara TVA</div>
                    <div class="value"><?= htmlspecialchars($formatMoney($data['total_without_vat'] ?? 0)) ?></div>
                </div>
                <div>
                    <div class="label">TVA</div>
                    <div class="value"><?= htmlspecialchars($formatMoney($data['total_vat'] ?? 0)) ?></div>
                </div>
                <div>
                    <div class="label">Total cu TVA</div>
                    <div class="value"><?= htmlspecialchars($formatMoney($data['total_with_vat'] ?? 0)) ?></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Produs</th>
                        <th class="right">Cant.</th>
                        <th>UM</th>
                        <th class="right">Pret</th>
                        <th class="right">TVA</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lines)): ?>
                        <tr>
                            <td colspan="7" class="muted">Nu exista linii de factura in acest XML.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lines as $index => $line): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) ($line['line_no'] ?: ($index + 1))) ?></td>
                                <td><?= htmlspecialchars((string) ($line['product_name'] ?? '')) ?></td>
                                <td class="right"><?= htmlspecialchars(number_format((float) ($line['quantity'] ?? 0), 2, '.', ' ')) ?></td>
                                <td><?= htmlspecialchars((string) ($line['unit_code'] ?? '')) ?></td>
                                <td class="right"><?= htmlspecialchars($formatMoney($line['unit_price'] ?? 0)) ?></td>
                                <td class="right"><?= htmlspecialchars(number_format((float) ($line['tax_percent'] ?? 0), 2, '.', ' ')) ?>%</td>
                                <td class="right"><?= htmlspecialchars($formatMoney($line['line_total_vat'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
