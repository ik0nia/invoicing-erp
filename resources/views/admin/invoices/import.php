<?php $title = 'Import factura XML'; ?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Import factura XML</h1>
    <p class="mt-2 text-sm text-slate-500">
        Incarca un fisier XML (UBL e-Factura) pentru a crea factura de intrare.
    </p>

    <form method="POST" action="<?= App\Support\Url::to('admin/facturi/import') ?>" enctype="multipart/form-data" class="mt-6 space-y-4">
        <?= App\Support\Csrf::input() ?>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="xml">Fisier XML</label>
            <input
                id="xml"
                name="xml"
                type="file"
                accept=".xml"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                required
            >
        </div>

        <button
            type="submit"
            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Importa factura
        </button>
    </form>
</div>
