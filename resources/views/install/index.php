<?php $title = 'Instalare ERP'; ?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-900">Instalare ERP</h1>
        <p class="text-sm text-slate-500">
            Completeaza datele de conectare. Dupa salvare vei putea crea primul admin.
        </p>
    </div>

    <form method="POST" action="<?= App\Support\Url::to('install') ?>" class="space-y-5">
        <?= App\Support\Csrf::input() ?>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="app_name">Nume aplicatie</label>
                <input
                    id="app_name"
                    name="app_name"
                    type="text"
                    value="<?= htmlspecialchars($app_name ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    required
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="app_url">URL aplicatie</label>
                <input
                    id="app_url"
                    name="app_url"
                    type="url"
                    value="<?= htmlspecialchars($app_url ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    required
                >
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="app_timezone">Timezone</label>
                <input
                    id="app_timezone"
                    name="app_timezone"
                    type="text"
                    value="<?= htmlspecialchars($app_timezone ?? 'Europe/Bucharest') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="db_charset">DB Charset</label>
                <input
                    id="db_charset"
                    name="db_charset"
                    type="text"
                    value="<?= htmlspecialchars($db_charset ?? 'utf8mb4') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                >
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="db_host">DB Host</label>
                <input
                    id="db_host"
                    name="db_host"
                    type="text"
                    value="<?= htmlspecialchars($db_host ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    required
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="db_port">DB Port</label>
                <input
                    id="db_port"
                    name="db_port"
                    type="text"
                    value="<?= htmlspecialchars($db_port ?? '3306') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                >
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="db_database">DB Name</label>
                <input
                    id="db_database"
                    name="db_database"
                    type="text"
                    value="<?= htmlspecialchars($db_database ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    required
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="db_username">DB User</label>
                <input
                    id="db_username"
                    name="db_username"
                    type="text"
                    value="<?= htmlspecialchars($db_username ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    required
                >
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="db_password">DB Parola</label>
            <input
                id="db_password"
                name="db_password"
                type="password"
                value="<?= htmlspecialchars($db_password ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
            >
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button
                type="submit"
                class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
            >
                Salveaza configurarea
            </button>
        </div>
    </form>

    <div class="mt-6 rounded border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
        <p class="font-medium text-slate-700">Dupa instalare:</p>
        <ul class="mt-2 list-disc pl-5">
            <li>Importa schema SQL din <code>database/schema.sql</code></li>
            <li>Optional: ruleaza <code>database/seed_roles.sql</code></li>
        </ul>
    </div>
</div>
