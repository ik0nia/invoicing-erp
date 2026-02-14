<?php
    $title = 'Previzualizare model';
    $template = $template ?? [];
    $rendered = $rendered ?? '';
    $sample = $sample ?? [];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Previzualizare model</h1>
        <p class="mt-1 text-sm text-slate-500">Afisare continut generat pe baza variabilelor.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/contract-templates/edit?id=' . (int) ($template['id'] ?? 0)) ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi la editare
    </a>
</div>

<div class="mt-4 rounded border border-slate-200 bg-white p-4 shadow-sm">
    <div class="text-xs text-slate-500">Model: <?= htmlspecialchars((string) ($template['name'] ?? '')) ?></div>
    <div class="text-xs text-slate-500">Tip: <?= htmlspecialchars((string) ($template['template_type'] ?? '')) ?></div>
    <?php if (!empty($sample)): ?>
        <div class="mt-2 text-xs text-slate-500">
            CUI partener: <?= htmlspecialchars((string) ($sample['partner_cui'] ?? '')) ?> |
            Furnizor: <?= htmlspecialchars((string) ($sample['supplier_cui'] ?? '')) ?> |
            Client: <?= htmlspecialchars((string) ($sample['client_cui'] ?? '')) ?>
        </div>
    <?php endif; ?>
</div>

<div class="mt-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <iframe
        title="Previzualizare"
        sandbox=""
        srcdoc="<?= htmlspecialchars((string) $rendered) ?>"
        class="h-[600px] w-full rounded border border-slate-200"
    ></iframe>
</div>
