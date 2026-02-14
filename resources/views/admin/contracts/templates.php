<?php
    $title = 'Modele de contract';
    $templates = $templates ?? [];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Modele de contract</h1>
        <p class="mt-1 text-sm text-slate-500">Gestioneaza modelele folosite la generarea contractelor.</p>
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
            <label class="block text-sm font-medium text-slate-700" for="template-type">Tip</label>
            <input
                id="template-type"
                name="template_type"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="supplier_contract / client_agreement / annex"
                required
            >
        </div>
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
                <th class="px-3 py-2">Tip</th>
                <th class="px-3 py-2">Creat</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($templates)): ?>
                <tr>
                    <td colspan="3" class="px-3 py-4 text-sm text-slate-500">
                        Nu exista modele de contract active. Creati un model pentru a putea genera contracte.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($template['name'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($template['template_type'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($template['created_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
