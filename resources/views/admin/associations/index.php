<?php
    $title = 'Asocieri clienti - furnizori';
    $canDeleteAssociations = $canDeleteAssociations ?? false;
    $pendingAssociationRequests = $pendingAssociationRequests ?? [];
?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Asocieri clienti - furnizori</h1>
        <p class="mt-1 text-sm text-slate-600">Gestioneaza comisioanele dintre furnizori si clienti.</p>
    </div>
</div>

<?php if (empty($hasPartners)): ?>
    <div class="mt-6 rounded border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        Nu exista companii importate. Importa datele vechi inainte de a crea asocieri.
    </div>
<?php endif; ?>

<div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="text-sm font-semibold text-amber-900">Solicitari asociere client</div>
            <p class="mt-1 text-xs text-amber-800">
                Cereri generate din pagina de adaugare partener atunci cand clientul exista deja la alt furnizor.
            </p>
        </div>
    </div>

    <?php if (empty($pendingAssociationRequests)): ?>
        <div class="mt-4 rounded border border-amber-200 bg-white px-4 py-3 text-sm text-amber-800">
            Nu exista solicitari in asteptare.
        </div>
    <?php else: ?>
        <div class="mt-4 overflow-x-auto rounded border border-amber-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-amber-100 text-amber-900">
                    <tr>
                        <th class="px-4 py-3">Solicitat la</th>
                        <th class="px-4 py-3">Furnizor</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Comision implicit</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingAssociationRequests as $request): ?>
                        <tr class="border-t border-amber-100">
                            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars((string) ($request['requested_at'] ?? $request['created_at'] ?? '')) ?></td>
                            <td class="px-4 py-3 text-slate-800">
                                <?= htmlspecialchars((string) ($request['supplier_name'] ?? $request['supplier_cui'] ?? '')) ?>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars((string) ($request['supplier_cui'] ?? '')) ?></div>
                            </td>
                            <td class="px-4 py-3 text-slate-800">
                                <?= htmlspecialchars((string) ($request['client_name'] ?? $request['client_cui'] ?? '')) ?>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars((string) ($request['client_cui'] ?? '')) ?></div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= number_format((float) ($request['commission_percent'] ?? 0), 2, '.', ' ') ?>%
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="<?= App\Support\Url::to('admin/asocieri/solicitari/aproba') ?>">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="id" value="<?= (int) ($request['id'] ?? 0) ?>">
                                        <button class="rounded border border-emerald-600 bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                            Aproba
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= App\Support\Url::to('admin/asocieri/solicitari/refuza') ?>">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="id" value="<?= (int) ($request['id'] ?? 0) ?>">
                                        <button class="rounded border border-rose-600 bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">
                                            Refuza
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
</div>

<form method="POST" action="<?= App\Support\Url::to('admin/asocieri/comision-default') ?>" class="mt-6 rounded-xl border border-blue-100 bg-blue-50 p-6 shadow-sm ring-1 ring-blue-100">
    <?= App\Support\Csrf::input() ?>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-sm font-semibold text-slate-700">Comision default furnizor</div>
            <p class="mt-1 text-xs text-slate-500">Seteaza procentul care se completeaza automat la asocieri.</p>
        </div>
        <button class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Salveaza comision
        </button>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="default_supplier_select">Furnizor</label>
            <select
                id="default_supplier_select"
                name="supplier_cui"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
                <option value="">Alege furnizor</option>
                <?php foreach ($partners as $partner): ?>
                    <option
                        value="<?= htmlspecialchars($partner->cui) ?>"
                        data-default-commission="<?= htmlspecialchars(number_format((float) ($partner->default_commission ?? 0), 2, '.', '')) ?>"
                    >
                        <?= htmlspecialchars($partner->denumire) ?> (<?= htmlspecialchars($partner->cui) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="default_commission">Comision default (%)</label>
            <input
                id="default_commission"
                name="default_commission"
                type="number"
                step="0.01"
                value=""
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
    </div>
</form>

<form method="POST" action="<?= App\Support\Url::to('admin/asocieri/salveaza') ?>" class="mt-6 rounded-xl border border-blue-100 bg-blue-50 p-6 shadow-sm ring-1 ring-blue-100">
    <?= App\Support\Csrf::input() ?>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="supplier_cui">Furnizor</label>
            <select id="supplier_cui" name="supplier_cui" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Alege furnizor</option>
                <?php foreach ($partners as $partner): ?>
                    <option
                        value="<?= htmlspecialchars($partner->cui) ?>"
                        data-default-commission="<?= htmlspecialchars(number_format((float) ($partner->default_commission ?? 0), 2, '.', '')) ?>"
                    >
                        <?= htmlspecialchars($partner->denumire) ?> (<?= htmlspecialchars($partner->cui) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="client_cui">Client</label>
            <select id="client_cui" name="client_cui" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Alege client</option>
                <?php foreach ($partners as $partner): ?>
                    <option value="<?= htmlspecialchars($partner->cui) ?>">
                        <?= htmlspecialchars($partner->denumire) ?> (<?= htmlspecialchars($partner->cui) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="commission">Comision (%)</label>
            <input
                id="commission"
                name="commission"
                type="number"
                step="0.01"
                value="0"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
    </div>

    <div class="mt-4">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Salveaza asocierea
        </button>
    </div>
</form>

<div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3">Furnizor</th>
                <th class="px-4 py-3">Client</th>
                <th class="px-4 py-3">Comision</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($associations)): ?>
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-slate-600">
                        Nu exista asocieri salvate.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($associations as $association): ?>
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-800">
                            <?= htmlspecialchars($association['supplier_name'] ?? $association['supplier_cui']) ?>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($association['supplier_cui']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-slate-800">
                            <?= htmlspecialchars($association['client_name'] ?? $association['client_cui']) ?>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($association['client_cui']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-slate-800">
                            <?= number_format((float) $association['commission'], 2, '.', ' ') ?>%
                        </td>
                        <td class="px-4 py-3 text-right">
                            <?php if ($canDeleteAssociations): ?>
                                <form method="POST" action="<?= App\Support\Url::to('admin/asocieri/sterge') ?>">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= (int) $association['id'] ?>">
                                    <button class="text-red-600 hover:text-red-700">Sterge</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-8 rounded-xl border border-blue-100 bg-blue-50 p-6 shadow-sm ring-1 ring-blue-100">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">Contacte relatie</h2>
        <p class="mt-1 text-sm text-slate-600">Contacte asociate relatiei furnizor-client.</p>
    </div>

    <form method="POST" action="<?= App\Support\Url::to('admin/contacts/create') ?>" class="mt-4 grid gap-3 md:grid-cols-5">
        <?= App\Support\Csrf::input() ?>
        <select name="supplier_cui" class="rounded border border-slate-300 px-3 py-2 text-sm" required>
            <option value="">Furnizor</option>
            <?php foreach ($partners as $partner): ?>
                <option value="<?= htmlspecialchars($partner->cui) ?>">
                    <?= htmlspecialchars($partner->denumire) ?> (<?= htmlspecialchars($partner->cui) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <select name="client_cui" class="rounded border border-slate-300 px-3 py-2 text-sm" required>
            <option value="">Client</option>
            <?php foreach ($partners as $partner): ?>
                <option value="<?= htmlspecialchars($partner->cui) ?>">
                    <?= htmlspecialchars($partner->denumire) ?> (<?= htmlspecialchars($partner->cui) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <input
            type="text"
            name="name"
            placeholder="Nume"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
            required
        >
        <input
            type="text"
            name="email"
            placeholder="Email"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
        <input
            type="text"
            name="phone"
            placeholder="Telefon"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
        <input
            type="text"
            name="role"
            placeholder="Rol"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
        <div class="md:col-span-5">
            <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
                Adauga contact relatie
            </button>
        </div>
    </form>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Relatie</th>
                    <th class="px-3 py-2">Nume</th>
                    <th class="px-3 py-2">Email</th>
                    <th class="px-3 py-2">Telefon</th>
                    <th class="px-3 py-2">Rol</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($relationContacts)): ?>
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-sm text-slate-500">Nu exista contacte relationale.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($relationContacts as $contact): ?>
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2 text-slate-600">
                                <?= htmlspecialchars((string) ($contact['supplier_cui'] ?? '')) ?> /
                                <?= htmlspecialchars((string) ($contact['client_cui'] ?? '')) ?>
                            </td>
                            <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contact['name'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['email'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['phone'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['role'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-right">
                                <form method="POST" action="<?= App\Support\Url::to('admin/contacts/delete') ?>">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= (int) $contact['id'] ?>">
                                    <button class="text-xs font-semibold text-rose-600 hover:text-rose-700">Sterge</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    (function () {
        const supplierSelect = document.getElementById('supplier_cui');
        const commissionInput = document.getElementById('commission');
        const defaultSupplierSelect = document.getElementById('default_supplier_select');
        const defaultCommissionInput = document.getElementById('default_commission');

        const readDefaultCommission = (select) => {
            if (!select) {
                return '';
            }
            const option = select.selectedOptions[0];
            return option ? (option.dataset.defaultCommission || '') : '';
        };

        const updateDefaultForm = () => {
            if (!defaultSupplierSelect || !defaultCommissionInput) {
                return;
            }
            const value = readDefaultCommission(defaultSupplierSelect);
            defaultCommissionInput.value = value;
        };

        const updateAssociationCommission = () => {
            if (!supplierSelect || !commissionInput) {
                return;
            }
            const value = readDefaultCommission(supplierSelect);
            if (value !== '') {
                commissionInput.value = value;
            }
        };

        if (defaultSupplierSelect) {
            defaultSupplierSelect.addEventListener('change', updateDefaultForm);
            updateDefaultForm();
        }

        if (supplierSelect && commissionInput) {
            supplierSelect.addEventListener('change', updateAssociationCommission);
        }
    })();
</script>
