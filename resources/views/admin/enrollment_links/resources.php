<?php
    $title = 'Fisiere UPA';
    $resources = $resources ?? [];
    $resourceTypeLabels = [
        'supplier' => 'Furnizor',
        'client' => 'Client',
        'both' => 'Ambele tipuri',
    ];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Fisiere UPA</h1>
        <p class="mt-1 text-sm text-slate-500">
            Gestionati documentele afisate in Pasul 1 din onboarding (UPA), pentru furnizori sau clienti.
        </p>
    </div>
</div>

<div class="mt-4 rounded-xl border border-blue-100 bg-blue-50 p-4 shadow-sm ring-1 ring-blue-100">
    <div class="text-sm font-semibold text-slate-800">Adauga fisier onboarding</div>
    <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/resources/upload') ?>" enctype="multipart/form-data" class="mt-4 grid gap-4 md:grid-cols-4">
        <?= App\Support\Csrf::input() ?>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700" for="resource-title">Titlu document</label>
            <input
                id="resource-title"
                name="title"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="Ex: Procedura onboarding, Anexa GDPR"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="resource-applies">Afisare pentru</label>
            <select id="resource-applies" name="applies_to" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="supplier">Furnizor</option>
                <option value="client">Client</option>
                <option value="both" selected>Ambele</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="resource-order">Ordine</label>
            <input
                id="resource-order"
                name="sort_order"
                type="number"
                min="0"
                max="9999"
                value="100"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div class="md:col-span-3">
            <label class="block text-sm font-medium text-slate-700" for="resource-file">Fisier</label>
            <input
                id="resource-file"
                name="resource_file"
                type="file"
                required
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
            <p class="mt-1 text-xs text-slate-500">
                Formate acceptate: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, JPG, PNG, ZIP. Maxim 25MB.
            </p>
        </div>
        <div class="flex items-end">
            <button class="w-full rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Adauga fisier
            </button>
        </div>
    </form>
</div>

<?php if (empty($resources)): ?>
    <div class="mt-4 rounded border border-slate-200 bg-white p-4 text-sm text-slate-500">
        Nu exista fisiere UPA configurate.
    </div>
<?php else: ?>
    <div class="mt-4 overflow-x-auto rounded border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Titlu</th>
                    <th class="px-3 py-2">Tip onboarding</th>
                    <th class="px-3 py-2">Fisier</th>
                    <th class="px-3 py-2">Ordine</th>
                    <th class="px-3 py-2">Creat la</th>
                    <th class="px-3 py-2 text-right">Actiuni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resources as $resource): ?>
                    <?php
                        $resourceId = (int) ($resource['id'] ?? 0);
                        $resourceTitle = (string) ($resource['title'] ?? 'Document onboarding');
                        $resourceType = (string) ($resource['applies_to'] ?? 'both');
                        $resourceTypeLabel = $resourceTypeLabels[$resourceType] ?? ucfirst($resourceType);
                        $resourceOriginalName = (string) ($resource['original_name'] ?? 'fisier');
                        $resourceOrder = (int) ($resource['sort_order'] ?? 100);
                        $resourceDownloadUrl = App\Support\Url::to('admin/enrollment-links/resources/download?id=' . $resourceId);
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars($resourceTitle) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($resourceTypeLabel) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($resourceOriginalName) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= $resourceOrder ?></td>
                        <td class="px-3 py-2 text-slate-500"><?= htmlspecialchars((string) ($resource['created_at'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-right">
                            <div class="inline-flex items-center gap-3 text-xs font-semibold">
                                <a href="<?= htmlspecialchars($resourceDownloadUrl) ?>" class="text-blue-700 hover:text-blue-800">Download</a>
                                <form method="POST" action="<?= App\Support\Url::to('admin/enrollment-links/resources/delete') ?>">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= $resourceId ?>">
                                    <button
                                        class="text-rose-600 hover:text-rose-700"
                                        onclick="return confirm('Sigur vrei sa stergi acest fisier?')"
                                    >
                                        Sterge
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
