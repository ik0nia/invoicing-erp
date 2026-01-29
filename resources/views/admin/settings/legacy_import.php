<?php $title = 'Import date vechi'; ?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Import date vechi</h1>
    <p class="mt-2 text-sm text-slate-500">
        Importa partenerii si comisioanele din baza veche (tabelele parteneri/comisioane).
    </p>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <div class="font-medium text-slate-700">Status tabele vechi</div>
            <ul class="mt-2 space-y-1">
                <li>parteneri: <?= $legacyPartnersExists ? 'gasit' : 'lipsa' ?></li>
                <li>comisioane: <?= $legacyCommissionsExists ? 'gasit' : 'lipsa' ?></li>
            </ul>
        </div>
        <div class="rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <div class="font-medium text-slate-700">Statistici import</div>
            <ul class="mt-2 space-y-1">
                <li>parteneri vechi: <?= (int) ($stats['legacy_partners'] ?? 0) ?></li>
                <li>comisioane vechi: <?= (int) ($stats['legacy_commissions'] ?? 0) ?></li>
                <li>parteneri importati: <?= (int) ($stats['partners'] ?? 0) ?></li>
                <li>comisioane importate: <?= (int) ($stats['commissions'] ?? 0) ?></li>
            </ul>
        </div>
    </div>

    <div class="mt-6 rounded border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
        <p>Daca nu ai tabelele vechi, importa SQL-ul primit in phpMyAdmin.</p>
        <p class="mt-2">Dupa import poti rula acest buton de cate ori doresti.</p>
    </div>

    <form method="POST" action="<?= App\Support\Url::to('admin/setari/import-date') ?>" class="mt-6">
        <?= App\Support\Csrf::input() ?>
        <button
            type="submit"
            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Importa datele
        </button>
    </form>
</div>
