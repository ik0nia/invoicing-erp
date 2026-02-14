<?php $title = 'Detalii companie'; ?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Detalii companie</h1>
        <p class="mt-1 text-sm text-slate-600">Completeaza informatiile companiei.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/companii') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi la lista
    </a>
</div>

<form method="POST" action="<?= App\Support\Url::to('admin/companii/save') ?>" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <?= App\Support\Csrf::input() ?>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="denumire">Denumire</label>
            <input
                id="denumire"
                name="denumire"
                type="text"
                value="<?= htmlspecialchars($form['denumire'] ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="cui">CUI</label>
            <div class="mt-1 flex flex-wrap gap-2">
                <input
                    id="cui"
                    name="cui"
                    type="text"
                    value="<?= htmlspecialchars($form['cui'] ?? '') ?>"
                    class="block w-full flex-1 rounded border border-slate-300 px-3 py-2 text-sm"
                    required
                >
                <button
                    type="button"
                    id="openapi-fetch"
                    class="rounded border border-blue-600 bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    <?= empty($openApiEnabled) ? 'disabled' : '' ?>
                >
                    Preia OpenAPI
                </button>
            </div>
            <p id="openapi-status" class="mt-1 text-xs text-slate-500">
                <?= empty($openApiEnabled) ? 'Completeaza cheia OpenAPI in Setari.' : 'Completeaza CUI si apasa Preia OpenAPI.' ?>
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="nr_reg_comertului">Nr. Reg. Comertului</label>
            <input
                id="nr_reg_comertului"
                name="nr_reg_comertului"
                type="text"
                value="<?= htmlspecialchars($form['nr_reg_comertului'] ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="adresa">Adresa</label>
            <input
                id="adresa"
                name="adresa"
                type="text"
                value="<?= htmlspecialchars($form['adresa'] ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="localitate">Localitate</label>
            <input
                id="localitate"
                name="localitate"
                type="text"
                value="<?= htmlspecialchars($form['localitate'] ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="judet">Judet</label>
            <input
                id="judet"
                name="judet"
                type="text"
                value="<?= htmlspecialchars($form['judet'] ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="tara">Tara</label>
            <input
                id="tara"
                name="tara"
                type="text"
                value="<?= htmlspecialchars($form['tara'] ?? 'România') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="<?= htmlspecialchars($form['email'] ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="telefon">Telefon</label>
            <input
                id="telefon"
                name="telefon"
                type="text"
                value="<?= htmlspecialchars($form['telefon'] ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="banca">Banca</label>
            <input
                id="banca"
                name="banca"
                type="text"
                value="<?= htmlspecialchars($form['banca'] ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="iban">IBAN</label>
            <input
                id="iban"
                name="iban"
                type="text"
                value="<?= htmlspecialchars($form['iban'] ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>

    </div>

    <div class="mt-4 flex flex-wrap items-center gap-6 text-sm text-slate-700">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="platitor_tva" class="rounded border-slate-300" <?= !empty($form['platitor_tva']) ? 'checked' : '' ?>>
            Platitor TVA
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="activ" class="rounded border-slate-300" <?= !empty($form['activ']) ? 'checked' : '' ?>>
            Activ
        </label>
        <?php if (!empty($partner) && ($partner->is_supplier || $partner->is_client)): ?>
            <div class="inline-flex items-center gap-2 text-xs text-slate-500">
                <?php if ($partner->is_supplier): ?>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">Supplier</span>
                <?php endif; ?>
                <?php if ($partner->is_client): ?>
                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-blue-700">Client</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-6">
        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Salveaza compania
        </button>
    </div>
</form>

<div class="mt-8 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Contacte</h2>
            <p class="mt-1 text-sm text-slate-500">Contacte asociate companiei.</p>
        </div>
    </div>

    <form method="POST" action="<?= App\Support\Url::to('admin/contacts/create') ?>" class="mt-4 grid gap-3 md:grid-cols-4">
        <?= App\Support\Csrf::input() ?>
        <input type="hidden" name="partner_cui" value="<?= htmlspecialchars($form['cui'] ?? '') ?>">
        <input
            type="text"
            name="name"
            placeholder="Nume"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
            required
        >
        <input
            type="text"
            name="email"
            placeholder="Email"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
        <input
            type="text"
            name="phone"
            placeholder="Telefon"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
        <input
            type="text"
            name="role"
            placeholder="Rol"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
        <div class="md:col-span-4">
            <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Adauga contact
            </button>
        </div>
    </form>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2">Nume</th>
                    <th class="px-3 py-2">Email</th>
                    <th class="px-3 py-2">Telefon</th>
                    <th class="px-3 py-2">Rol</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contacts)): ?>
                    <tr>
                        <td colspan="5" class="px-3 py-4 text-sm text-slate-500">Nu exista contacte.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contacts as $contact): ?>
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contact['name'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['email'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['phone'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['role'] ?? '')) ?></td>
                            <td class="px-3 py-2 text-right">
                                <form method="POST" action="<?= App\Support\Url::to('admin/contacts/delete') ?>">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= (int) $contact['id'] ?>">
                                    <button class="text-xs font-semibold text-rose-600 hover:text-rose-700">
                                        Sterge
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    (function () {
        const button = document.getElementById('openapi-fetch');
        const status = document.getElementById('openapi-status');
        const cuiInput = document.getElementById('cui');
        const token = '<?= App\Support\Csrf::token() ?>';
        const enabled = <?= empty($openApiEnabled) ? 'false' : 'true' ?>;

        if (!button || !status || !cuiInput || !enabled) {
            return;
        }

        const setValue = (id, value) => {
            const input = document.getElementById(id);
            if (!input || value === null || value === undefined || value === '') {
                return;
            }
            input.value = value;
        };

        const setCheckbox = (name, value) => {
            const input = document.querySelector('input[name="' + name + '"]');
            if (!input || value === null || value === undefined) {
                return;
            }
            input.checked = !!value;
        };

        const setSelect = (id, value) => {
            const select = document.getElementById(id);
            if (!select || !value) {
                return;
            }
            select.value = value;
        };

        button.addEventListener('click', () => {
            const cui = (cuiInput.value || '').replace(/\D+/g, '');
            if (!cui) {
                status.textContent = 'Completeaza CUI-ul inainte de cautare.';
                status.className = 'mt-1 text-xs text-rose-600';
                return;
            }

            button.disabled = true;
            status.textContent = 'Se preiau datele din OpenAPI...';
            status.className = 'mt-1 text-xs text-slate-500';

            const body = new URLSearchParams();
            body.set('_token', token);
            body.set('cui', cui);

            fetch('<?= App\Support\Url::to('admin/companii/openapi') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload || !payload.success) {
                        status.textContent = payload?.message || 'Nu am putut prelua datele.';
                        status.className = 'mt-1 text-xs text-rose-600';
                        return;
                    }

                    const data = payload.data || {};
                    setValue('denumire', data.denumire);
                    setValue('cui', data.cui);
                    setValue('nr_reg_comertului', data.nr_reg_comertului);
                    setValue('adresa', data.adresa);
                    setValue('localitate', data.localitate);
                    setValue('judet', data.judet);
                    setValue('telefon', data.telefon);
                    setCheckbox('platitor_tva', data.platitor_tva);
                    if (data.activ !== null && data.activ !== undefined) {
                        setCheckbox('activ', data.activ);
                    }

                    status.textContent = 'Datele au fost completate. Verifica si salveaza.';
                    status.className = 'mt-1 text-xs text-emerald-600';
                })
                .catch(() => {
                    status.textContent = 'Eroare la conectarea cu OpenAPI.';
                    status.className = 'mt-1 text-xs text-rose-600';
                })
                .finally(() => {
                    button.disabled = false;
                });
        });
    })();
</script>
