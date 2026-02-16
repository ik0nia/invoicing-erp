<?php
    $title = 'Modele de contract';
    $templates = $templates ?? [];
    $variables = $variables ?? [];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Modele de contract</h1>
        <p class="mt-1 text-sm text-slate-500">Gestioneaza modelele folosite la generarea contractelor.</p>
    </div>
</div>

<div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <div class="font-semibold">Variabile disponibile</div>
    <div class="mt-2 text-xs text-blue-700">
        Foloseste variabilele intre acolade duble, de exemplu: <strong>{{partner.name}}</strong>
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

<div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <div class="font-semibold">Inrolare automata</div>
    <div class="mt-1">
        Pentru a genera automat documente la inrolare, bifeaza „Creeaza automat la inrolare”, marcheaza „Obligatoriu la onboarding”
        si seteaza „Se aplica la”.
        Prioritatea controleaza ordinea documentelor create.
    </div>
</div>

<form method="POST" action="<?= App\Support\Url::to('admin/contract-templates/save') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <?= App\Support\Csrf::input() ?>
    <input type="hidden" name="id" value="">
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-name">Nume</label>
            <input
                id="template-name"
                name="name"
                type="text"
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
                <option value="contract">Contract</option>
                <option value="acord">Acord</option>
                <option value="anexa">Anexa</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-applies">Se aplica la</label>
            <select
                id="template-applies"
                name="applies_to"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
                <option value="both">Ambele</option>
                <option value="supplier">Furnizor</option>
                <option value="client">Client</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-priority">Prioritate</label>
            <input
                id="template-priority"
                name="priority"
                type="number"
                value="100"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>
    <div class="mt-4">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="auto_on_enrollment" class="rounded border-slate-300">
            Creeaza automat la inrolare
        </label>
        <label class="ml-6 inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="required_onboarding" class="rounded border-slate-300">
            Obligatoriu la onboarding
        </label>
        <label class="ml-6 inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" class="rounded border-slate-300" checked>
            Activ
        </label>
    </div>
    <div class="mt-4">
        <label class="block text-sm font-medium text-slate-700" for="template-html">HTML</label>
        <textarea
            id="template-html"
            name="html_content"
            rows="6"
            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        ></textarea>
    </div>
    <div class="mt-4">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Salveaza model
        </button>
    </div>
</form>

<div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-3 py-2">Nume</th>
                <th class="px-3 py-2">Doc type</th>
                <th class="px-3 py-2">Categorie</th>
                <th class="px-3 py-2">Se aplica la</th>
                <th class="px-3 py-2">Automat la inrolare</th>
                <th class="px-3 py-2">Obligatoriu</th>
                <th class="px-3 py-2">Prioritate</th>
                <th class="px-3 py-2">Activ</th>
                <th class="px-3 py-2">Creat</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($templates)): ?>
                <tr>
                    <td colspan="10" class="px-3 py-4 text-sm text-slate-500">
                        Nu exista modele de contract active. Creati un model pentru a putea genera contracte.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <?php
                        $docKind = (string) ($template['doc_kind'] ?? 'contract');
                        $docType = (string) ($template['doc_type'] ?? $template['template_type'] ?? $docKind);
                        $applies = (string) ($template['applies_to'] ?? 'both');
                        $auto = !empty($template['auto_on_enrollment']);
                        $required = !empty($template['required_onboarding']);
                        $active = !empty($template['is_active']);
                        $appliesLabel = $applies === 'supplier' ? 'Furnizor' : ($applies === 'client' ? 'Client' : 'Ambele');
                        $categoryLabel = $docKind === 'acord' ? 'Acord' : ($docKind === 'anexa' ? 'Anexa' : 'Contract');
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($template['name'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600 font-mono"><?= htmlspecialchars($docType) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($categoryLabel) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($appliesLabel) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= $auto ? 'Da' : 'Nu' ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= $required ? 'Da' : 'Nu' ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= (int) ($template['priority'] ?? 100) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= $active ? 'Da' : 'Nu' ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($template['created_at'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-right">
                            <a
                                href="<?= App\Support\Url::to('admin/contract-templates/edit?id=' . (int) $template['id']) ?>"
                                class="text-xs font-semibold text-blue-700 hover:text-blue-800"
                            >
                                Editeaza
                            </a>
                            <form method="POST" action="<?= App\Support\Url::to('admin/contract-templates/duplicate') ?>" class="inline-block ml-3">
                                <?= App\Support\Csrf::input() ?>
                                <input type="hidden" name="id" value="<?= (int) $template['id'] ?>">
                                <button class="text-xs font-semibold text-slate-600 hover:text-slate-800">
                                    Duplică
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

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
