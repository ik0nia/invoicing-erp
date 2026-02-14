<?php
    $title = 'Contracts';
    $templates = $templates ?? [];
    $contracts = $contracts ?? [];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Contracts</h1>
        <p class="mt-1 text-sm text-slate-500">Contracte generate si incarcate.</p>
    </div>
</div>

<form method="POST" action="<?= App\Support\Url::to('admin/contracts/generate') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <?= App\Support\Csrf::input() ?>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template_id">Template</label>
            <select id="template_id" name="template_id" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">(fara template)</option>
                <?php foreach ($templates as $template): ?>
                    <option value="<?= (int) $template['id'] ?>">
                        <?= htmlspecialchars((string) ($template['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="contract-title">Titlu</label>
            <input
                id="contract-title"
                name="title"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="partner-cui">Partner CUI (optional)</label>
            <input
                id="partner-cui"
                name="partner_cui"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="supplier-cui">Supplier CUI (optional)</label>
            <input
                id="supplier-cui"
                name="supplier_cui"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="client-cui">Client CUI (optional)</label>
            <input
                id="client-cui"
                name="client_cui"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>

    <div class="mt-4">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Genereaza contract
        </button>
    </div>
</form>

<form method="POST" action="<?= App\Support\Url::to('admin/contracts/upload-signed') ?>" enctype="multipart/form-data" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <?= App\Support\Csrf::input() ?>
    <div class="flex flex-wrap items-center gap-3">
        <select name="contract_id" class="rounded border border-slate-300 px-3 py-2 text-sm" required>
            <option value="">Selecteaza contract</option>
            <?php foreach ($contracts as $contract): ?>
                <option value="<?= (int) $contract['id'] ?>">
                    <?= htmlspecialchars((string) ($contract['title'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="file" name="file" required class="text-sm text-slate-600">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Incarca semnat
        </button>
    </div>
</form>

<div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-3 py-2">Titlu</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Relatie</th>
                <th class="px-3 py-2">Download</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contracts)): ?>
                <tr>
                    <td colspan="5" class="px-3 py-4 text-sm text-slate-500">Nu exista contracte.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($contracts as $contract): ?>
                    <?php
                        $downloadUrl = App\Support\Url::to('admin/contracts/download?id=' . (int) $contract['id']);
                        $relation = '';
                        if (!empty($contract['supplier_cui']) || !empty($contract['client_cui'])) {
                            $relation = trim((string) ($contract['supplier_cui'] ?? '')) . ' / ' . trim((string) ($contract['client_cui'] ?? ''));
                        }
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contract['title'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contract['status'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($relation !== '' ? $relation : '—') ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <?php if (!empty($contract['signed_file_path']) || !empty($contract['generated_file_path'])): ?>
                                <a href="<?= htmlspecialchars($downloadUrl) ?>" class="text-xs font-semibold text-blue-700 hover:text-blue-800">Descarca</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <?php if (($contract['status'] ?? '') !== 'approved'): ?>
                                <form method="POST" action="<?= App\Support\Url::to('admin/contracts/approve') ?>">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
                                    <button class="text-xs font-semibold text-emerald-600 hover:text-emerald-700">Aproba</button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Aprobat</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
