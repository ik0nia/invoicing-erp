<?php $title = 'Setari'; ?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Setari</h1>
        <p class="mt-1 text-sm text-slate-600">Branding si integrare API.</p>
    </div>
</div>

<form method="POST" action="<?= App\Support\Url::to('admin/setari') ?>" enctype="multipart/form-data" class="mt-6 space-y-6">
    <?= App\Support\Csrf::input() ?>

    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Branding</h2>
        <p class="mt-1 text-sm text-slate-600">Actualizeaza logo-ul aplicatiei ERP.</p>

        <div class="mt-4 grid gap-6 md:grid-cols-[200px_1fr]">
            <div class="rounded border border-slate-200 bg-slate-50 p-4 text-center">
                <?php if (!empty($logoUrl)): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo ERP" class="mx-auto h-16 w-auto">
                <?php else: ?>
                    <div class="text-sm text-slate-600">Fara logo incarcat</div>
                <?php endif; ?>
            </div>

            <div class="space-y-3">
                <label class="block text-sm font-medium text-slate-700" for="logo">
                    Logo ERP (png, jpg, svg)
                </label>
                <input
                    id="logo"
                    name="logo"
                    type="file"
                    accept=".png,.jpg,.jpeg,.svg"
                    class="block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
                <p class="text-xs text-slate-600">Poti lasa gol daca nu schimbi logo-ul.</p>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Integrare FGO</h2>
        <p class="mt-1 text-sm text-slate-600">Chei pentru generarea facturilor prin API.</p>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="fgo_api_key">Cod unic (API key)</label>
                <input
                    id="fgo_api_key"
                    name="fgo_api_key"
                    type="text"
                    value="<?= htmlspecialchars($fgoApiKey ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="fgo_secret_key">Secret key</label>
                <input
                    id="fgo_secret_key"
                    name="fgo_secret_key"
                    type="password"
                    value=""
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
                <?php if (!empty($fgoSecretMasked)): ?>
                    <p class="mt-1 text-xs text-slate-600">Cheie existenta: <?= htmlspecialchars($fgoSecretMasked) ?></p>
                <?php else: ?>
                    <p class="mt-1 text-xs text-slate-600">Nu exista cheie salvata.</p>
                <?php endif; ?>
                <p class="text-xs text-slate-600">Lasa gol pentru a pastra cheia actuala.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="fgo_series">Serie factura FGO</label>
                <input
                    id="fgo_series"
                    name="fgo_series"
                    type="text"
                    value="<?= htmlspecialchars($fgoSeries ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
                <p class="text-xs text-slate-600">Serie definita in FGO (Setari → Serii documente).</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="fgo_base_url">API URL (optional)</label>
                <input
                    id="fgo_base_url"
                    name="fgo_base_url"
                    type="text"
                    value="<?= htmlspecialchars($fgoBaseUrl ?? '') ?>"
                    placeholder="https://api.fgo.ro/v1"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
                <p class="text-xs text-slate-600">Lasa gol pentru productia FGO.</p>
            </div>
        </div>
    </div>

    <div>
        <button
            type="submit"
            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Salveaza setarile
        </button>
    </div>
</form>
