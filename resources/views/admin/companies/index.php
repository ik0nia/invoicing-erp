<?php $title = 'Companii'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Companii</h1>
        <p class="mt-1 text-sm text-slate-600">Lista de companii din sistem.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/companii/edit') ?>"
        class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
    >
        Adauga companie
    </a>
</div>

<?php if (empty($companies)): ?>
    <div class="mt-6 rounded border border-slate-200 bg-white p-6 text-sm text-slate-600">
        Nu exista companii disponibile. Importa datele vechi sau adauga o companie noua.
    </div>
<?php else: ?>
    <div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-4 py-3">Denumire</th>
                    <th class="px-4 py-3">CUI</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">
                            <?= htmlspecialchars($company['denumire'] ?? '') ?>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            <?= htmlspecialchars($company['cui'] ?? '') ?>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            <?php if (!empty($company['company_id'])): ?>
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">
                                    Detalii complete
                                </span>
                            <?php else: ?>
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                                    Detalii lipsa
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a
                                href="<?= App\Support\Url::to('admin/companii/edit') ?>?cui=<?= urlencode($company['cui'] ?? '') ?>"
                                class="text-blue-700 hover:text-blue-800"
                            >
                                Detalii →
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
