<?php $title = 'Pagina inexistenta'; ?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Pagina nu exista</h1>
    <p class="mt-2 text-sm text-slate-500"><?= htmlspecialchars($message ?? 'Nu am gasit pagina ceruta.') ?></p>
</div>
