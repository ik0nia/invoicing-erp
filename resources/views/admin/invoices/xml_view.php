<?php
    $content = $content ?? '';
    $tree = $tree ?? null;
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

        <?php if ($tree): ?>
            <?php $renderNode($tree); ?>
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
