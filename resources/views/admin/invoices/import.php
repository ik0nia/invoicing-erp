<?php $title = 'Import factura XML'; ?>

<div class="mx-auto max-w-3xl rounded-2xl border border-blue-100 bg-blue-50 p-8 shadow-sm ring-1 ring-blue-100">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Import factura XML</h1>
            <p class="mt-2 text-sm text-slate-600">
                Incarca un fisier XML (UBL e-Factura) pentru a crea automat factura de intrare.
            </p>
        </div>
        <a
            href="<?= App\Support\Url::to('admin/facturi') ?>"
            class="inline-flex rounded border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Inapoi la facturi
        </a>
    </div>

    <form method="POST" action="<?= App\Support\Url::to('admin/facturi/import') ?>" enctype="multipart/form-data" class="mt-8 space-y-5">
        <?= App\Support\Csrf::input() ?>

        <div class="rounded-xl border-2 border-dashed border-blue-300 bg-white p-7 shadow-sm">
            <label class="block text-base font-semibold text-slate-800" for="xml">Fisier XML</label>
            <p class="mt-1 text-sm text-slate-500">
                Selecteaza fisierul XML de import. Sunt acceptate doar fisiere cu extensia .xml.
            </p>
            <input
                id="xml"
                name="xml"
                type="file"
                accept=".xml"
                class="mt-4 block w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-4 py-4 text-base text-slate-700 file:mr-4 file:rounded file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                required
            >
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="inline-flex rounded border border-blue-700 bg-blue-700 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800"
            >
                Importa factura XML
            </button>
        </div>
    </form>
</div>
