<?php
    $form = $form ?? [];
    $selectedRole = $form['role'] ?? ($selectedRole ?? '');
    $selectedSuppliers = $form['supplier_cuis'] ?? ($selectedSuppliers ?? []);
    $selectedSuppliers = array_map('strval', (array) $selectedSuppliers);
    $currentUserId = $currentUserId ?? 0;
?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900"><?= htmlspecialchars($title ?? 'Utilizator') ?></h1>
            <p class="mt-1 text-sm text-slate-500">Configureaza rolurile si accesul la furnizori.</p>
        </div>
        <a
            href="<?= App\Support\Url::to('admin/utilizatori') ?>"
            class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
        >
            Inapoi la lista
        </a>
    </div>

    <form method="POST" action="<?= App\Support\Url::to($action ?? '') ?>" class="mt-6 space-y-6">
        <?= App\Support\Csrf::input() ?>
        <?php if (!empty($user?->id)): ?>
            <input type="hidden" name="user_id" value="<?= (int) $user->id ?>">
        <?php endif; ?>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="name">Nume</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="<?= htmlspecialchars($form['name'] ?? ($user->name ?? '')) ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    required
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="<?= htmlspecialchars($form['email'] ?? ($user->email ?? '')) ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    required
                >
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="password">
                    Parola <?= !empty($user?->id) ? '(optional)' : '' ?>
                </label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="password_confirmation">
                    Confirma parola <?= !empty($user?->id) ? '(optional)' : '' ?>
                </label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="role">Rol</label>
            <select
                id="role"
                name="role"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                data-role-select
            >
                <?php foreach ($roles as $role): ?>
                    <option value="<?= htmlspecialchars($role['key']) ?>" <?= $selectedRole === $role['key'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($canManagePackagePermission)): ?>
            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-semibold text-slate-700">Permisiuni pachete</div>
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        name="can_rename_packages"
                        value="1"
                        <?= !empty($form['can_rename_packages']) ? 'checked' : '' ?>
                    >
                    Permite redenumirea pachetelor
                </label>
            </div>
        <?php endif; ?>

        <div class="rounded border border-slate-200 bg-slate-50 p-4" data-supplier-section>
            <div class="text-sm font-semibold text-slate-700">Acces furnizori</div>
            <p class="mt-1 text-xs text-slate-500">Selecteaza furnizorii pe care ii poate gestiona utilizatorul.</p>

            <div class="mt-3">
                <label class="block text-sm font-medium text-slate-700" for="supplier-search">Cauta furnizor</label>
                <input
                    id="supplier-search"
                    type="text"
                    placeholder="Cauta dupa nume sau CUI"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    data-supplier-search
                >
            </div>

            <div class="mt-3 max-h-64 overflow-auto rounded border border-slate-200 bg-white p-3 text-sm">
                <?php if (empty($suppliers)): ?>
                    <div class="text-sm text-slate-500">Nu exista furnizori disponibili.</div>
                <?php else: ?>
                    <div class="space-y-2" data-supplier-list>
                        <?php foreach ($suppliers as $supplier): ?>
                            <?php
                                $cui = (string) ($supplier['cui'] ?? '');
                                $name = (string) ($supplier['name'] ?? $cui);
                                $search = strtolower($name . ' ' . $cui);
                            ?>
                            <label class="flex items-center gap-2 text-slate-700" data-supplier-item data-search="<?= htmlspecialchars($search) ?>">
                                <input
                                    type="checkbox"
                                    name="supplier_cuis[]"
                                    value="<?= htmlspecialchars($cui) ?>"
                                    <?= in_array($cui, $selectedSuppliers, true) ? 'checked' : '' ?>
                                >
                                <span><?= htmlspecialchars($name) ?> · <?= htmlspecialchars($cui) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button
                type="submit"
                class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
            >
                Salveaza
            </button>
            <a
                href="<?= App\Support\Url::to('admin/utilizatori') ?>"
                class="rounded border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Renunta
            </a>
            <?php if (!empty($user?->id) && (int) $currentUserId !== (int) $user->id): ?>
                <form method="POST" action="<?= App\Support\Url::to('admin/utilizatori/sterge') ?>">
                    <?= App\Support\Csrf::input() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $user->id ?>">
                    <button
                        type="submit"
                        class="rounded border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100"
                        onclick="return confirm('Sigur vrei sa stergi utilizatorul?')"
                    >
                        Sterge
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    (function () {
        const roleSelect = document.querySelector('[data-role-select]');
        const supplierSection = document.querySelector('[data-supplier-section]');
        const supplierSearch = document.querySelector('[data-supplier-search]');
        const supplierItems = document.querySelectorAll('[data-supplier-item]');

        const toggleSuppliers = () => {
            if (!roleSelect || !supplierSection) {
                return;
            }
            const shouldShow = roleSelect.value === 'supplier_user';
            supplierSection.style.display = shouldShow ? 'block' : 'none';
        };

        if (roleSelect) {
            roleSelect.addEventListener('change', toggleSuppliers);
        }
        toggleSuppliers();

        if (supplierSearch) {
            supplierSearch.addEventListener('input', () => {
                const value = supplierSearch.value.toLowerCase();
                supplierItems.forEach((item) => {
                    const hay = item.getAttribute('data-search') || '';
                    item.style.display = hay.includes(value) ? 'flex' : 'none';
                });
            });
        }
    })();
</script>
