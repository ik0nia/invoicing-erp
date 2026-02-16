<?php
    $title = 'Editare model contract';
    $template = $template ?? [];
    $variables = $variables ?? [];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Editare model de contract</h1>
        <p class="mt-1 text-sm text-slate-500">Actualizeaza detaliile modelului.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/contract-templates') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi la lista
    </a>
</div>

<div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <div class="font-semibold">Variabile disponibile</div>
    <div class="mt-2 text-xs text-blue-700">
        Foloseste variabilele intre acolade duble, de exemplu: <strong>{{partner.name}}</strong>
    </div>
    <div class="mt-1 text-xs text-blue-700">
        Datele despre reprezentant si banca se completeaza din Companii/Inrolare.
    </div>
    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($variables as $item): ?>
            <div class="flex items-center justify-between gap-2 rounded border border-blue-100 bg-white px-2 py-1 text-xs text-blue-800">
                <span class="font-mono">{{<?= htmlspecialchars((string) $item['key']) ?>}}</span>
                <button
                    type="button"
                    class="rounded border border-blue-200 px-2 py-0.5 text-[11px] text-blue-700 hover:bg-blue-50"
                    data-copy="{{<?= htmlspecialchars((string) $item['key']) ?>}}"
                >
                    Copiaza
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<form method="POST" action="<?= App\Support\Url::to('admin/contract-templates/update') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <?= App\Support\Csrf::input() ?>
    <input type="hidden" name="id" value="<?= (int) ($template['id'] ?? 0) ?>">
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-name">Nume</label>
            <input
                id="template-name"
                name="name"
                type="text"
                value="<?= htmlspecialchars((string) ($template['name'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-doc-type">Doc type (indexare)</label>
            <input
                id="template-doc-type"
                name="doc_type"
                type="text"
                value="<?= htmlspecialchars((string) ($template['doc_type'] ?? $template['template_type'] ?? '')) ?>"
                placeholder="ex: client_agreement"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-kind">Categorie document</label>
            <select
                id="template-kind"
                name="doc_kind"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
                <?php $docKind = (string) ($template['doc_kind'] ?? 'contract'); ?>
                <option value="contract" <?= $docKind === 'contract' ? 'selected' : '' ?>>Contract</option>
                <option value="acord" <?= $docKind === 'acord' ? 'selected' : '' ?>>Acord</option>
                <option value="anexa" <?= $docKind === 'anexa' ? 'selected' : '' ?>>Anexa</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-applies">Se aplica la</label>
            <?php $appliesTo = (string) ($template['applies_to'] ?? 'both'); ?>
            <select
                id="template-applies"
                name="applies_to"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
                <option value="both" <?= $appliesTo === 'both' ? 'selected' : '' ?>>Ambele</option>
                <option value="supplier" <?= $appliesTo === 'supplier' ? 'selected' : '' ?>>Furnizor</option>
                <option value="client" <?= $appliesTo === 'client' ? 'selected' : '' ?>>Client</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-priority">Prioritate</label>
            <input
                id="template-priority"
                name="priority"
                type="number"
                value="<?= (int) ($template['priority'] ?? 100) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>
    <div class="mt-4">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="auto_on_enrollment" class="rounded border-slate-300" <?= !empty($template['auto_on_enrollment']) ? 'checked' : '' ?>>
            Creeaza automat la inrolare
        </label>
        <label class="ml-6 inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="required_onboarding" class="rounded border-slate-300" <?= !empty($template['required_onboarding']) ? 'checked' : '' ?>>
            Obligatoriu la onboarding
        </label>
        <label class="ml-6 inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" class="rounded border-slate-300" <?= !empty($template['is_active']) ? 'checked' : '' ?>>
            Activ
        </label>
    </div>
    <div class="mt-4">
        <label class="block text-sm font-medium text-slate-700" for="template-html">HTML</label>
        <textarea
            id="template-html"
            name="html_content"
            rows="8"
            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        ><?= htmlspecialchars((string) ($template['html_content'] ?? '')) ?></textarea>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Salveaza modificari
        </button>
    </div>
</form>

<form method="POST" action="<?= App\Support\Url::to('admin/contract-templates/preview') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <?= App\Support\Csrf::input() ?>
    <input type="hidden" name="id" value="<?= (int) ($template['id'] ?? 0) ?>">
    <div class="text-sm font-semibold text-slate-700">Previzualizare</div>
    <p class="mt-1 text-xs text-slate-500">Optional: completeaza un partener pentru test.</p>
    <div class="mt-3 grid gap-3 md:grid-cols-3">
        <input
            type="text"
            name="partner_cui"
            placeholder="CUI partener"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
        <input
            type="text"
            name="supplier_cui"
            placeholder="CUI furnizor (relatie)"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
        <input
            type="text"
            name="client_cui"
            placeholder="CUI client (relatie)"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
    </div>
    <div class="mt-4">
        <button class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Previzualizeaza
        </button>
    </div>
</form>

<script>
    (function () {
        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', () => {
                const value = button.getAttribute('data-copy') || '';
                if (!value) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value);
                } else {
                    const temp = document.createElement('textarea');
                    temp.value = value;
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand('copy');
                    document.body.removeChild(temp);
                }
            });
        });
    })();
</script>
