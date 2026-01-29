<?php $title = 'Configurare initiala'; ?>

<div class="mx-auto max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="mb-2 text-xl font-semibold text-slate-900">Configurare initiala</h1>
    <p class="mb-6 text-sm text-slate-500">Creeaza primul utilizator administrator.</p>

    <form method="POST" action="<?= App\Support\Url::to('setup') ?>" class="space-y-4">
        <?= App\Support\Csrf::input() ?>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="name">Nume complet</label>
            <input
                id="name"
                name="name"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="password">Parola</label>
            <input
                id="password"
                name="password"
                type="password"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="password_confirmation">Confirma parola</label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                required
            >
        </div>

        <button
            type="submit"
            class="w-full rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Creeaza admin
        </button>
    </form>
</div>
