<?php
    $content = $content ?? '';
    $tree = $tree ?? null;
    $display = $display ?? null;
    $error = $error ?? null;
    $invoiceNumber = isset($invoice) ? ($invoice->invoice_number ?? '') : '';

    $renderNode = static function ($node, int $level = 0, string $suffix = '') use (&$renderNode): void {
        if (!$node || !is_array($node)) {
            return;
        }

        $label = (string) ($node['label'] ?? ($node['name'] ?? 'Element'));
        $tag = (string) ($node['name'] ?? '');
        $value = $node['value'] ?? null;
        $attributes = $node['attributes'] ?? [];
        $children = $node['children'] ?? [];

        $indentClass = $level > 0 ? 'node node-level' : 'node-root';
        echo '<div class="' . $indentClass . '">';
        echo '<div class="node-header">';
        echo '<div class="node-title">' . htmlspecialchars($label);
        if ($suffix !== '') {
            echo ' <span class="node-index">' . htmlspecialchars($suffix) . '</span>';
        }
        if ($tag !== '') {
            echo ' <span class="node-tag">(' . htmlspecialchars($tag) . ')</span>';
        }
        echo '</div>';

        if (!empty($attributes)) {
            echo '<div class="node-attrs">';
            foreach ($attributes as $attrName => $attrValue) {
                echo '<span class="attr">' . htmlspecialchars((string) $attrName) . ': ' . htmlspecialchars((string) $attrValue) . '</span>';
            }
            echo '</div>';
        }
        echo '</div>';

        if ($value !== null && $value !== '') {
            echo '<div class="node-value">' . htmlspecialchars((string) $value) . '</div>';
        }

        if (!empty($children)) {
            $counts = [];
            foreach ($children as $child) {
                $name = (string) ($child['name'] ?? '');
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }

            $indexes = [];
            echo '<div class="node-children">';
            foreach ($children as $child) {
                $name = (string) ($child['name'] ?? '');
                $indexes[$name] = ($indexes[$name] ?? 0) + 1;
                $suffixText = $counts[$name] > 1 ? '#' . $indexes[$name] : '';
                $renderNode($child, $level + 1, $suffixText);
            }
            echo '</div>';
        }

        echo '</div>';
    };

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

    $formatMoney = static function ($value, string $currency): string {
        $amount = is_numeric($value) ? (float) $value : 0.0;
        return number_format($amount, 2, '.', ' ') . ' ' . $currency;
    };

    $addressLine = static function (array $address): string {
        $parts = array_filter([
            $address['street'] ?? null,
            $address['city'] ?? null,
            $address['postal'] ?? null,
            $address['region'] ?? null,
            $address['country'] ?? null,
        ]);
        return $parts ? implode(', ', $parts) : '—';
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
        .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 12px; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .section { margin-top: 16px; }
        .section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; margin-bottom: 8px; }
        .label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .value { font-size: 14px; font-weight: 600; color: #0f172a; margin-top: 4px; }
        .muted { color: #64748b; font-size: 12px; }
        .card-block { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 8px 6px; text-align: left; vertical-align: top; }
        th { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .right { text-align: right; }
        .tag { background: #e2e8f0; color: #0f172a; font-size: 11px; padding: 2px 6px; border-radius: 999px; margin-left: 6px; }
        .node-root { margin-top: 8px; }
        .node { margin-top: 8px; padding-left: 12px; border-left: 2px solid #e2e8f0; }
        .node-header { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .node-title { font-size: 14px; font-weight: 600; color: #0f172a; }
        .node-tag { font-size: 12px; color: #94a3b8; }
        .node-index { font-size: 12px; color: #475569; }
        .node-attrs { display: flex; flex-wrap: wrap; gap: 6px; }
        .attr { background: #e2e8f0; color: #0f172a; font-size: 11px; padding: 2px 6px; border-radius: 999px; }
        .node-value { margin-top: 4px; font-size: 13px; color: #334155; }
        .node-children { margin-top: 8px; }
        pre { white-space: pre-wrap; word-break: break-word; background: #f1f5f9; border-radius: 8px; padding: 12px; font-size: 12px; line-height: 1.5; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">Factura furnizor<?= $invoiceNumber !== '' ? ' #' . htmlspecialchars($invoiceNumber) : '' ?></div>
        <div class="subtitle">Vizualizare detalii factura furnizor.</div>

        <?php if ($error): ?>
            <div class="error">
                Nu am putut interpreta XML-ul: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($display): ?>
            <?php
                $currency = (string) ($display['currency'] ?? 'RON');
                $supplier = $display['supplier'] ?? [];
                $customer = $display['customer'] ?? [];
                $delivery = $display['delivery'] ?? [];
                $payment = $display['payment'] ?? [];
                $totals = $display['totals'] ?? [];
                $taxSubtotals = $display['tax_subtotals'] ?? [];
                $lines = $display['lines'] ?? [];
            ?>

            <div class="section grid">
                <div class="card-block">
                    <div class="section-title">Furnizor</div>
                    <div class="value"><?= htmlspecialchars((string) ($supplier['name'] ?? '—')) ?></div>
                    <div class="muted">CUI: <?= htmlspecialchars((string) ($supplier['cui'] ?? '—')) ?></div>
                    <div class="muted">Reg. comert: <?= htmlspecialchars((string) ($supplier['reg'] ?? '—')) ?></div>
                    <div class="muted">Adresa: <?= htmlspecialchars($addressLine($supplier['address'] ?? [])) ?></div>
                </div>
                <div class="card-block">
                    <div class="section-title">Client</div>
                    <div class="value"><?= htmlspecialchars((string) ($customer['name'] ?? '—')) ?></div>
                    <div class="muted">CUI: <?= htmlspecialchars((string) ($customer['cui'] ?? '—')) ?></div>
                    <div class="muted">Reg. comert: <?= htmlspecialchars((string) ($customer['reg'] ?? '—')) ?></div>
                    <div class="muted">Adresa: <?= htmlspecialchars($addressLine($customer['address'] ?? [])) ?></div>
                </div>
                <div class="card-block">
                    <div class="section-title">Factura</div>
                    <div class="muted">Nr: <?= htmlspecialchars((string) ($display['invoice_number'] ?? '—')) ?></div>
                    <div class="muted">Data: <?= htmlspecialchars($formatDate($display['issue_date'] ?? null)) ?></div>
                    <div class="muted">Scadenta: <?= htmlspecialchars($formatDate($display['due_date'] ?? null)) ?></div>
                    <div class="muted">Moneda: <?= htmlspecialchars($currency) ?></div>
                    <div class="muted">Tip: <?= htmlspecialchars((string) ($display['invoice_type'] ?? '—')) ?></div>
                </div>
            </div>

            <div class="section grid">
                <div class="card-block">
                    <div class="section-title">Livrare</div>
                    <div class="muted">Data livrare: <?= htmlspecialchars($formatDate($delivery['date'] ?? null)) ?></div>
                    <div class="muted">Locatie: <?= htmlspecialchars((string) ($delivery['location_id'] ?? '—')) ?></div>
                    <div class="muted">Adresa: <?= htmlspecialchars($addressLine($delivery['address'] ?? [])) ?></div>
                </div>
                <div class="card-block">
                    <div class="section-title">Plata</div>
                    <div class="muted">Cod plata: <?= htmlspecialchars((string) ($payment['code'] ?? '—')) ?></div>
                    <div class="muted">Banca: <?= htmlspecialchars((string) ($payment['bank'] ?? '—')) ?></div>
                    <div class="muted">IBAN: <?= htmlspecialchars((string) ($payment['account'] ?? '—')) ?></div>
                </div>
                <div class="card-block">
                    <div class="section-title">Referinte</div>
                    <div class="muted">Comanda: <?= htmlspecialchars((string) ($display['order_ref'] ?? '—')) ?></div>
                    <div class="muted">Document expeditie: <?= htmlspecialchars((string) ($display['despatch_ref'] ?? '—')) ?></div>
                </div>
            </div>

            <?php if (!empty($display['note'])): ?>
                <div class="section card-block">
                    <div class="section-title">Nota</div>
                    <div class="muted"><?= htmlspecialchars((string) $display['note']) ?></div>
                </div>
            <?php endif; ?>

            <div class="section grid">
                <div class="card-block">
                    <div class="section-title">Totaluri</div>
                    <div class="muted">Total fara TVA: <?= htmlspecialchars($formatMoney($totals['tax_exclusive'] ?? 0, $currency)) ?></div>
                    <div class="muted">TVA: <?= htmlspecialchars($formatMoney($display['tax_total'] ?? 0, $currency)) ?></div>
                    <div class="muted">Total cu TVA: <?= htmlspecialchars($formatMoney($totals['tax_inclusive'] ?? 0, $currency)) ?></div>
                    <div class="muted">Total de plata: <?= htmlspecialchars($formatMoney($totals['payable'] ?? 0, $currency)) ?></div>
                </div>
            </div>

            <?php if (!empty($taxSubtotals)): ?>
                <div class="section">
                    <div class="section-title">Detalii TVA</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Categorie</th>
                                <th class="right">Procent</th>
                                <th class="right">Baza</th>
                                <th class="right">TVA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taxSubtotals as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($row['category'] ?? '—')) ?></td>
                                    <td class="right"><?= htmlspecialchars((string) ($row['percent'] ?? '—')) ?>%</td>
                                    <td class="right"><?= htmlspecialchars($formatMoney($row['taxable'] ?? 0, $currency)) ?></td>
                                    <td class="right"><?= htmlspecialchars($formatMoney($row['tax'] ?? 0, $currency)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="section">
                <div class="section-title">Linii factura</div>
                <table>
                    <thead>
                        <tr>
                            <th>Nr</th>
                            <th>Produs</th>
                            <th class="right">Cantitate</th>
                            <th>UM</th>
                            <th class="right">Pret unitar</th>
                            <th class="right">TVA</th>
                            <th class="right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lines)): ?>
                            <tr>
                                <td colspan="7" class="muted">Nu exista linii de factura in XML.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lines as $index => $line): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($line['id'] ?? ($index + 1))) ?></td>
                                    <td>
                                        <?= htmlspecialchars((string) ($line['product_name'] ?? '')) ?>
                                        <?php if (!empty($line['line_ref'])): ?>
                                            <span class="tag">Ref: <?= htmlspecialchars((string) $line['line_ref']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($line['allowances'])): ?>
                                            <?php foreach ($line['allowances'] as $allow): ?>
                                                <div class="muted">
                                                    Taxa/Discount: <?= htmlspecialchars((string) ($allow['reason'] ?? '—')) ?>,
                                                    <?= htmlspecialchars((string) ($allow['amount'] ?? '0')) ?> <?= htmlspecialchars((string) ($allow['currency'] ?? $currency)) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="right"><?= htmlspecialchars((string) ($line['quantity'] ?? '0')) ?></td>
                                    <td><?= htmlspecialchars((string) ($line['unit_code'] ?? '')) ?></td>
                                    <td class="right">
                                        <?= htmlspecialchars($formatMoney($line['unit_price'] ?? 0, $line['unit_currency'] ?? $currency)) ?>
                                    </td>
                                    <td class="right"><?= htmlspecialchars((string) ($line['tax_percent'] ?? '—')) ?>%</td>
                                    <td class="right">
                                        <?= htmlspecialchars($formatMoney($line['total_with_vat'] ?? ($line['line_total'] ?? 0), $line['line_currency'] ?? $currency)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($tree): ?>
            <details class="section">
                <summary class="muted">Detalii XML complete</summary>
                <?php $renderNode($tree); ?>
            </details>
        <?php endif; ?>

        <?php if ($content !== ''): ?>
            <details class="mt-4">
                <summary class="text-sm text-slate-600">Vezi XML</summary>
                <pre><?= htmlspecialchars($content) ?></pre>
            </details>
        <?php endif; ?>
    </div>
</body>
</html>
