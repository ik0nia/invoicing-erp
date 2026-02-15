<?php
    $title = 'Profil';
?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Profil</h1>
            <p class="mt-1 text-sm text-slate-500">Schimba parola contului tau.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div>
            <div class="text-sm font-medium text-slate-700">Nume</div>
            <div class="mt-1 text-sm text-slate-600"><?= htmlspecialchars($user?->name ?? '-') ?></div>
        </div>
        <div>
            <div class="text-sm font-medium text-slate-700">Email</div>
            <div class="mt-1 text-sm text-slate-600"><?= htmlspecialchars($user?->email ?? '-') ?></div>
        </div>
    </div>

    <form method="POST" action="<?= App\Support\Url::to('admin/profil/parola') ?>" class="mt-6 space-y-4">
        <?= App\Support\Csrf::input() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="current_password">Parola curenta</label>
            <input
                id="current_password"
                name="current_password"
                type="password"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="password">Parola noua</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    required
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="password_confirmation">Confirma parola</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    required
                >
            </div>
        </div>
        <div class="flex flex-wrap gap-3">
            <button
                type="submit"
                class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
            >
                Salveaza parola
            </button>
        </div>
    </form>
</div>
