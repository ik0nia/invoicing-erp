<?php
    $title = 'Setari';
    $documentRegistry = $documentRegistry ?? [];
    $registryAvailable = !empty($documentRegistry['available']);
    $registrySeries = (string) ($documentRegistry['series'] ?? '');
    $registryStartNo = max(1, (int) ($documentRegistry['start_no'] ?? 1));
    $registryNextNo = max($registryStartNo, (int) ($documentRegistry['next_no'] ?? $registryStartNo));
    $registryUpdatedAt = (string) ($documentRegistry['updated_at'] ?? '');
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Setari</h1>
        <p class="mt-1 text-sm text-slate-600">Branding si integrare API.</p>
    </div>
</div>

<form method="POST" action="<?= App\Support\Url::to('admin/setari') ?>" enctype="multipart/form-data" class="mt-6 space-y-6">
    <?= App\Support\Csrf::input() ?>

    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Date companie</h2>
        <p class="mt-1 text-sm text-slate-600">Informatii despre firma emitenta.</p>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_denumire">Denumire</label>
                <input
                    id="company_denumire"
                    name="company_denumire"
                    type="text"
                    value="<?= htmlspecialchars($company['denumire'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_cui">CUI</label>
                <input
                    id="company_cui"
                    name="company_cui"
                    type="text"
                    value="<?= htmlspecialchars($company['cui'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_nr_reg_comertului">Nr. Reg. Comertului</label>
                <input
                    id="company_nr_reg_comertului"
                    name="company_nr_reg_comertului"
                    type="text"
                    value="<?= htmlspecialchars($company['nr_reg_comertului'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_platitor_tva">Platitor TVA</label>
                <select
                    id="company_platitor_tva"
                    name="company_platitor_tva"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="0" <?= empty($company['platitor_tva']) ? 'selected' : '' ?>>Nu</option>
                    <option value="1" <?= !empty($company['platitor_tva']) ? 'selected' : '' ?>>Da</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_tara">Tara</label>
                <input
                    id="company_tara"
                    name="company_tara"
                    type="text"
                    value="<?= htmlspecialchars($company['tara'] ?? 'Romania') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700" for="company_adresa">Adresa</label>
                <input
                    id="company_adresa"
                    name="company_adresa"
                    type="text"
                    value="<?= htmlspecialchars($company['adresa'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_localitate">Localitate</label>
                <input
                    id="company_localitate"
                    name="company_localitate"
                    type="text"
                    value="<?= htmlspecialchars($company['localitate'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_judet">Judet</label>
                <input
                    id="company_judet"
                    name="company_judet"
                    type="text"
                    value="<?= htmlspecialchars($company['judet'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_email">Email</label>
                <input
                    id="company_email"
                    name="company_email"
                    type="email"
                    value="<?= htmlspecialchars($company['email'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_telefon">Telefon</label>
                <input
                    id="company_telefon"
                    name="company_telefon"
                    type="text"
                    value="<?= htmlspecialchars($company['telefon'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_banca">Banca</label>
                <input
                    id="company_banca"
                    name="company_banca"
                    type="text"
                    value="<?= htmlspecialchars($company['banca'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="company_iban">Cont bancar (IBAN)</label>
                <input
                    id="company_iban"
                    name="company_iban"
                    type="text"
                    value="<?= htmlspecialchars($company['iban'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Branding</h2>
        <p class="mt-1 text-sm text-slate-600">Actualizeaza logo-ul aplicatiei ERP pentru mod normal si dark mode.</p>

        <div class="mt-4 grid gap-6 md:grid-cols-2">
            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-semibold text-slate-800">Logo mod normal</div>
                <div class="mt-3 rounded border border-slate-200 bg-white p-4 text-center">
                    <?php if (!empty($logoUrl)): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo ERP mod normal" class="mx-auto h-16 w-auto">
                    <?php else: ?>
                        <div class="text-sm text-slate-600">Fara logo incarcat</div>
                    <?php endif; ?>
                </div>
                <div class="mt-3 space-y-2">
                    <label class="block text-sm font-medium text-slate-700" for="logo">
                        Incarca logo mod normal (png, jpg, svg)
                    </label>
                    <input
                        id="logo"
                        name="logo"
                        type="file"
                        accept=".png,.jpg,.jpeg,.svg"
                        class="block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-slate-900 p-4">
                <div class="text-sm font-semibold text-slate-100">Logo dark mode</div>
                <div class="mt-3 rounded border border-slate-700 bg-slate-950 p-4 text-center">
                    <?php if (!empty($logoDarkUrl)): ?>
                        <img src="<?= htmlspecialchars($logoDarkUrl) ?>" alt="Logo ERP dark mode" class="mx-auto h-16 w-auto">
                    <?php elseif (!empty($logoUrl)): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo ERP fallback dark mode" class="mx-auto h-16 w-auto opacity-80">
                    <?php else: ?>
                        <div class="text-sm text-slate-300">Fara logo dark incarcat</div>
                    <?php endif; ?>
                </div>
                <div class="mt-3 space-y-2">
                    <label class="block text-sm font-medium text-slate-100" for="logo_dark">
                        Incarca logo dark mode (png, jpg, svg)
                    </label>
                    <input
                        id="logo_dark"
                        name="logo_dark"
                        type="file"
                        accept=".png,.jpg,.jpeg,.svg"
                        class="block w-full rounded border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-slate-100"
                    >
                    <p class="text-xs text-slate-300">Daca lipseste, in dark mode se foloseste logo-ul normal.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Integrare FGO</h2>
        <p class="mt-1 text-sm text-slate-600">Chei pentru generarea facturilor prin API.</p>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="fgo_api_key">Cheie API FGO</label>
                <input
                    id="fgo_api_key"
                    name="fgo_api_key"
                    type="text"
                    value="<?= htmlspecialchars($fgoApiKey ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
                <p class="mt-1 text-xs text-slate-600">CUI-ul emitentului se ia din Date companie.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="fgo_series_list">Serii FGO disponibile</label>
                <textarea
                    id="fgo_series_list"
                    name="fgo_series_list"
                    rows="2"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                ><?= htmlspecialchars($fgoSeriesListText ?? '') ?></textarea>
                <p class="text-xs text-slate-600">Separat prin virgula sau rand nou (ex: RO1, RO2).</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="fgo_series">Serie implicita</label>
                <?php if (!empty($fgoSeriesList)): ?>
                    <select
                        id="fgo_series"
                        name="fgo_series"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                        <option value="">Alege serie</option>
                        <?php foreach ($fgoSeriesList as $series): ?>
                            <option value="<?= htmlspecialchars($series) ?>" <?= ($fgoSeries ?? '') === $series ? 'selected' : '' ?>>
                                <?= htmlspecialchars($series) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input
                        id="fgo_series"
                        name="fgo_series"
                        type="text"
                        value="<?= htmlspecialchars($fgoSeries ?? '') ?>"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                    <p class="text-xs text-slate-600">Completeaza lista de serii pentru dropdown.</p>
                <?php endif; ?>
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

    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">OpenAPI companii</h2>
        <p class="mt-1 text-sm text-slate-600">Cheie pentru preluarea datelor firmelor din OpenAPI.</p>

        <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700" for="openapi_api_key">Cheie API OpenAPI</label>
            <input
                id="openapi_api_key"
                name="openapi_api_key"
                type="text"
                value="<?= htmlspecialchars($openApiKey ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
            <p class="mt-1 text-xs text-slate-600">Se trimite ca header <code>x-api-key</code> catre api.openapi.ro.</p>
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

<div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-900">Registru documente - setari globale</h2>
    <p class="mt-1 text-sm text-slate-600">
        Configureaza seria si numerotarea globala folosita pentru documentele contractuale.
    </p>

    <?php if (!$registryAvailable): ?>
        <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Registrul documentelor nu este disponibil momentan.
        </div>
    <?php else: ?>
        <div class="mt-4 grid gap-6 lg:grid-cols-2">
            <form method="POST" action="<?= App\Support\Url::to('admin/registru-documente/save') ?>" class="space-y-4 rounded border border-slate-200 bg-slate-50 p-4">
                <?= App\Support\Csrf::input() ?>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Serie</label>
                        <input
                            type="text"
                            name="series"
                            value="<?= htmlspecialchars($registrySeries) ?>"
                            maxlength="16"
                            placeholder="ex: CTR"
                            class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Start</label>
                        <input
                            type="number"
                            min="1"
                            name="start_no"
                            value="<?= $registryStartNo ?>"
                            class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Urmatorul nr.</label>
                        <input
                            type="number"
                            min="1"
                            name="next_no"
                            value="<?= $registryNextNo ?>"
                            class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                        >
                    </div>
                </div>
                <button class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
                    Salveaza registrul
                </button>
            </form>

            <div class="space-y-4 rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm text-slate-700">
                    Ultima actualizare:
                    <span class="font-semibold text-slate-900"><?= htmlspecialchars($registryUpdatedAt !== '' ? $registryUpdatedAt : '—') ?></span>
                </div>
                <form method="POST" action="<?= App\Support\Url::to('admin/registru-documente/set-start') ?>" class="inline-flex items-end gap-3">
                    <?= App\Support\Csrf::input() ?>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">Start nou</label>
                        <input
                            type="number"
                            min="1"
                            name="start_no"
                            value="<?= $registryStartNo ?>"
                            class="mt-1 w-28 rounded border border-slate-300 px-2 py-1.5 text-sm"
                        >
                        <input type="hidden" name="series" value="<?= htmlspecialchars($registrySeries) ?>">
                    </div>
                    <button class="rounded border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                        Seteaza start
                    </button>
                </form>
                <form method="POST" action="<?= App\Support\Url::to('admin/registru-documente/reset-start') ?>">
                    <?= App\Support\Csrf::input() ?>
                    <button
                        class="rounded border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-semibold text-rose-700 hover:bg-rose-100"
                        onclick="return confirm('Resetezi numerotarea la start pentru registrul global?')"
                    >
                        Reseteaza la start
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="mt-6 rounded-lg border border-rose-200 bg-rose-50/40 p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-900">Date test</h2>
    <p class="mt-1 text-sm text-slate-600">
        Genereaza un set de ~30 facturi demo cu plati partiale/integrale sau goleste datele operationale.
    </p>
    <div class="mt-4 flex flex-wrap gap-3">
        <form method="POST" action="<?= App\Support\Url::to('admin/setari/demo-generate') ?>">
            <?= App\Support\Csrf::input() ?>
            <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
                Genereaza continut demo
            </button>
        </form>
        <form method="POST" action="<?= App\Support\Url::to('admin/setari/demo-reset') ?>" onsubmit="return confirm('Sigur vrei sa stergi toate facturile si platile?')">
            <?= App\Support\Csrf::input() ?>
            <button class="rounded border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                Golire date demo
            </button>
        </form>
    </div>
</div>
