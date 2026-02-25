<?php $title = 'Import extras bancar'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Import extras bancar</h1>
        <p class="mt-1 text-sm text-slate-500">Incarca un fisier CSV exportat din ING Business.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/incasari/extras') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi la extras
    </a>
</div>

<?php if (!empty($importError)): ?>
    <div class="mt-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($importError) ?></div>
<?php endif; ?>

<div class="mt-6 rounded border border-slate-200 bg-white p-6 max-w-lg">
    <h2 class="mb-4 text-sm font-semibold text-slate-700">Selecteaza fisierul CSV</h2>
    <form method="POST" enctype="multipart/form-data" action="<?= App\Support\Url::to('admin/incasari/import-extras') ?>" class="flex flex-col gap-4">
        <?= App\Support\Csrf::input() ?>
        <input
            type="file"
            name="csv_file"
            accept=".csv,text/csv"
            required
            class="block rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-slate-700"
        >
        <div>
            <button
                type="submit"
                class="rounded bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
            >
                Importa
            </button>
        </div>
    </form>
    <p class="mt-4 text-xs text-slate-400">Fisierul trebuie sa fie in format CSV cu separator <code>;</code>, exportat din ING Business. Tranzactiile deja importate sunt ignorate automat.</p>
</div>
