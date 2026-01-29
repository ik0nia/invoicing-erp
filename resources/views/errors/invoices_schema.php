<?php $title = 'Schema facturi lipsa'; ?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Tabelele pentru facturi nu exista</h1>
    <p class="mt-2 text-sm text-slate-500">
        Modulul de facturi necesita tabelele <strong>invoices_in</strong>, <strong>invoice_in_lines</strong>
        si <strong>packages</strong>.
    </p>

    <div class="mt-4 rounded border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
        Importa fisierul <code>database/invoices_schema.sql</code> in phpMyAdmin,
        apoi revino la <a class="underline" href="<?= App\Support\Url::to('admin/facturi') ?>">/admin/facturi</a>.
    </div>
</div>
