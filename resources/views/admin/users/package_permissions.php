<?php
    $title = 'Permisiuni pachete';
    $currentUserId = $currentUserId ?? 0;
?>

<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Permisiuni redenumire pachete</h1>
        <p class="mt-1 text-sm text-slate-500">Alege utilizatorii care pot redenumi pachetele.</p>
    </div>
    <?php if (App\Support\Auth::user()?->isSuperAdmin()): ?>
        <a
            href="<?= App\Support\Url::to('admin/utilizatori') ?>"
            class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
        >
            Inapoi la utilizatori
        </a>
    <?php endif; ?>
</div>

<div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm md:table">
        <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3">Nume</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Rol</th>
                <th class="px-4 py-3">Permisiune</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-slate-500">
                        Nu exista utilizatori definiti.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr class="border-b border-slate-100 block md:table-row">
                        <td class="px-4 py-3 font-medium text-slate-900 block md:table-cell" data-label="Nume">
                            <?= htmlspecialchars($user['name']) ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Email">
                            <?= htmlspecialchars($user['email']) ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Rol">
                            <?php if (!empty($user['role_labels'])): ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($user['role_labels'] as $label): ?>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                            <?= htmlspecialchars($label) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Fara rol</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Permisiune">
                            <form method="POST" action="<?= App\Support\Url::to('admin/utilizatori/permisiuni-pachete') ?>" class="flex items-center gap-2">
                                <?= App\Support\Csrf::input() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                    <input type="checkbox" name="can_rename_packages" value="1" <?= !empty($user['can_rename']) ? 'checked' : '' ?>>
                                    Permite redenumire
                                </label>
                                <button class="rounded border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                    Salveaza
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
