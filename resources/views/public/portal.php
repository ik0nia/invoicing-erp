<?php
    $title = 'Portal Documente';
    $link = $link ?? null;
    $permissions = $permissions ?? [
        'can_view' => false,
        'can_upload_signed' => false,
        'can_upload_custom' => false,
    ];
    $contracts = $contracts ?? [];
    $relationDocs = $relationDocs ?? [];
    $scope = $scope ?? [];
    $token = $token ?? '';
    $error = $error ?? '';
?>

<div class="max-w-4xl">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Portal documente</h1>
        <p class="mt-1 text-sm text-slate-600">Acces documente si contracte.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <?= htmlspecialchars((string) $error) ?>
        </div>
    <?php endif; ?>

    <?php if ($link && !empty($permissions['can_view'])): ?>
        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-700">Contracte</div>
            <?php if (empty($contracts)): ?>
                <div class="mt-3 text-sm text-slate-500">Nu exista contracte disponibile.</div>
            <?php else: ?>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Titlu</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $contract): ?>
                                <?php
                                    $downloadUrl = App\Support\Url::to('portal/' . $token . '/download?type=contract&id=' . (int) $contract['id']);
                                    $fileAvailable = !empty($contract['signed_file_path']) || !empty($contract['generated_file_path']);
                                ?>
                                <tr class="border-t border-slate-100">
                                    <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contract['title'] ?? '')) ?></td>
                                    <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contract['status'] ?? '')) ?></td>
                                    <td class="px-3 py-2 text-slate-600">
                                        <?php if ($fileAvailable): ?>
                                            <a href="<?= htmlspecialchars($downloadUrl) ?>" class="text-blue-700 hover:text-blue-800 text-xs font-semibold">Descarca</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-700">Documente relatie</div>
            <?php if (empty($relationDocs)): ?>
                <div class="mt-3 text-sm text-slate-500">Nu exista documente custom.</div>
            <?php else: ?>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Titlu</th>
                                <th class="px-3 py-2">Relatie</th>
                                <th class="px-3 py-2">Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relationDocs as $doc): ?>
                                <?php $downloadUrl = App\Support\Url::to('portal/' . $token . '/download?type=relation&id=' . (int) $doc['id']); ?>
                                <tr class="border-t border-slate-100">
                                    <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($doc['title'] ?? '')) ?></td>
                                    <td class="px-3 py-2 text-slate-600">
                                        <?= htmlspecialchars((string) ($doc['supplier_cui'] ?? '')) ?> /
                                        <?= htmlspecialchars((string) ($doc['client_cui'] ?? '')) ?>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        <a href="<?= htmlspecialchars($downloadUrl) ?>" class="text-blue-700 hover:text-blue-800 text-xs font-semibold">Descarca</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($permissions['can_upload_signed'])): ?>
            <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-sm font-semibold text-slate-700">Incarca contract semnat</div>
                <form method="POST" action="<?= App\Support\Url::to('portal/' . $token . '/upload') ?>" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-3">
                    <?= App\Support\Csrf::input() ?>
                    <input type="hidden" name="upload_type" value="signed">
                    <select name="contract_id" class="rounded border border-slate-300 px-3 py-2 text-sm" required>
                        <option value="">Selecteaza contract</option>
                        <?php foreach ($contracts as $contract): ?>
                            <option value="<?= (int) $contract['id'] ?>">
                                <?= htmlspecialchars((string) ($contract['title'] ?? 'Contract')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" name="file" required class="text-sm text-slate-600">
                    <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Incarca
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($permissions['can_upload_custom'])): ?>
            <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-sm font-semibold text-slate-700">Incarca document custom</div>
                <form method="POST" action="<?= App\Support\Url::to('portal/' . $token . '/upload') ?>" enctype="multipart/form-data" class="mt-3 grid gap-3 md:grid-cols-4">
                    <?= App\Support\Csrf::input() ?>
                    <input type="hidden" name="upload_type" value="custom">
                    <input
                        type="text"
                        name="title"
                        placeholder="Titlu document"
                        class="rounded border border-slate-300 px-3 py-2 text-sm"
                        required
                    >
                    <?php if (($scope['type'] ?? '') !== 'relation'): ?>
                        <input
                            type="text"
                            name="relation_supplier_cui"
                            placeholder="CUI furnizor"
                            class="rounded border border-slate-300 px-3 py-2 text-sm"
                            required
                        >
                        <input
                            type="text"
                            name="relation_client_cui"
                            placeholder="CUI client"
                            class="rounded border border-slate-300 px-3 py-2 text-sm"
                            required
                        >
                    <?php endif; ?>
                    <input type="file" name="file" required class="text-sm text-slate-600">
                    <div>
                        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Incarca
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
