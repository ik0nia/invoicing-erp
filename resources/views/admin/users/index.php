<?php
    $title = 'Utilizatori';
    $currentUserId = $currentUserId ?? 0;
?>

<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Utilizatori</h1>
        <p class="mt-1 text-sm text-slate-500">Administrare roluri si acces furnizori.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/utilizatori/adauga') ?>"
        class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
    >
        Adauga utilizator
    </a>
</div>

<div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm md:table">
        <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3">Nume</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Rol</th>
                <th class="px-4 py-3">Furnizori</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-slate-500">
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
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Furnizori">
                            <?php if (!empty($user['supplier_names'])): ?>
                                <?php
                                    $display = array_slice($user['supplier_names'], 0, 3);
                                    $remaining = count($user['supplier_names']) - count($display);
                                ?>
                                <div class="text-xs text-slate-700">
                                    <?= htmlspecialchars(implode(', ', $display)) ?>
                                    <?php if ($remaining > 0): ?>
                                        <span class="text-slate-400">+<?= $remaining ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right block md:table-cell" data-label="Actiuni">
                            <a
                                href="<?= App\Support\Url::to('admin/utilizatori/edit') ?>?id=<?= (int) $user['id'] ?>"
                                class="text-blue-700 hover:text-blue-800"
                            >
                                Editeaza
                            </a>
                            <?php if ((int) $currentUserId !== (int) $user['id']): ?>
                                <form method="POST" action="<?= App\Support\Url::to('admin/utilizatori/sterge') ?>" class="inline">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <button
                                        type="submit"
                                        class="ml-2 text-red-600 hover:text-red-700"
                                        onclick="return confirm('Sigur vrei sa stergi utilizatorul?')"
                                    >
                                        Sterge
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    @media (max-width: 768px) {
        table thead {
            display: none;
        }
        table tbody tr {
            display: block;
            padding: 0.75rem 0.75rem;
        }
        table tbody td {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.4rem 0;
        }
        table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #334155;
        }
    }
</style>
