<?php $title = 'Asocieri clienti - furnizori'; ?>

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

<form method="POST" action="<?= App\Support\Url::to('admin/asocieri/salveaza') ?>" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <?= App\Support\Csrf::input() ?>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="supplier_cui">Furnizor</label>
            <select id="supplier_cui" name="supplier_cui" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Alege furnizor</option>
                <?php foreach ($partners as $partner): ?>
                    <option value="<?= htmlspecialchars($partner->cui) ?>">
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
                            <form method="POST" action="<?= App\Support\Url::to('admin/asocieri/sterge') ?>">
                                <?= App\Support\Csrf::input() ?>
                                <input type="hidden" name="id" value="<?= (int) $association['id'] ?>">
                                <button class="text-red-600 hover:text-red-700">Sterge</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
