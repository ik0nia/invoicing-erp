<?php $title = 'Dashboard'; ?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Dashboard</h1>
    <p class="mt-2 text-sm text-slate-500">
        Bine ai venit<?= isset($user) ? ', ' . htmlspecialchars($user->name) : '' ?>.
    </p>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded border border-slate-200 bg-slate-50 p-4">
            <div class="text-sm font-medium text-slate-700">Setari</div>
            <p class="mt-1 text-sm text-slate-500">Branding si chei API FGO.</p>
            <a
                href="<?= App\Support\Url::to('admin/setari') ?>"
                class="mt-3 inline-flex text-sm font-semibold text-blue-700 hover:text-blue-800"
            >
                Configureaza →
            </a>
        </div>
        <div class="rounded border border-slate-200 bg-slate-50 p-4">
            <div class="text-sm font-medium text-slate-700">Structura ERP</div>
            <p class="mt-1 text-sm text-slate-500">
                Urmatoarele module: Companii, Utilizatori, Facturi.
            </p>
        </div>
    </div>
</div>
