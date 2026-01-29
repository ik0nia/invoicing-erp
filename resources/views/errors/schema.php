<?php $title = 'Schema baza de date lipsa'; ?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Schema bazei de date nu este importata</h1>
    <p class="mt-2 text-sm text-slate-500">
        Nu am gasit tabelele necesare pentru ERP. Te rog importa schema SQL in baza de date.
    </p>

    <?php if (!empty($missingTables)): ?>
        <div class="mt-4 rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <div class="font-medium text-slate-700">Tabele lipsa:</div>
            <ul class="mt-2 list-disc pl-5">
                <?php foreach ($missingTables as $table): ?>
                    <li><?= htmlspecialchars($table) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="mt-6 rounded border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
        Importa fisierul <code>database/schema.sql</code> in phpMyAdmin, apoi revino la
        <a class="underline" href="<?= App\Support\Url::to('setup') ?>">/setup</a>.
    </div>
</div>
