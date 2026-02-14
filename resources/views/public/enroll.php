<?php
    $title = 'Inrolare partener';
    $link = $link ?? null;
    $prefill = $prefill ?? [];
    $error = $error ?? '';
?>

<div class="max-w-3xl">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Inrolare partener</h1>
        <p class="mt-1 text-sm text-slate-600">Completeaza datele pentru inrolare.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <?= htmlspecialchars((string) $error) ?>
        </div>
    <?php endif; ?>

    <?php if ($link): ?>
        <form method="POST" action="" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <?= App\Support\Csrf::input() ?>
            <input type="hidden" name="type" value="<?= htmlspecialchars((string) ($link['type'] ?? '')) ?>">
            <?php if (!empty($link['supplier_cui'])): ?>
                <input type="hidden" name="supplier_cui" value="<?= htmlspecialchars((string) $link['supplier_cui']) ?>">
            <?php endif; ?>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="cui">CUI</label>
                    <div class="mt-1 flex gap-2">
                        <input
                            id="cui"
                            name="cui"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['cui'] ?? '')) ?>"
                            class="block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            required
                        >
                        <button
                            type="button"
                            id="openapi-fetch"
                            class="rounded border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            OpenAPI
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="denumire">Denumire</label>
                    <input
                        id="denumire"
                        name="denumire"
                        type="text"
                        value="<?= htmlspecialchars((string) ($prefill['denumire'] ?? '')) ?>"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        required
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="nr_reg_comertului">Nr. Reg. Comertului</label>
                    <input
                        id="nr_reg_comertului"
                        name="nr_reg_comertului"
                        type="text"
                        value="<?= htmlspecialchars((string) ($prefill['nr_reg_comertului'] ?? '')) ?>"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="<?= htmlspecialchars((string) ($prefill['email'] ?? '')) ?>"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="adresa">Adresa</label>
                    <input
                        id="adresa"
                        name="adresa"
                        type="text"
                        value="<?= htmlspecialchars((string) ($prefill['adresa'] ?? '')) ?>"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="localitate">Localitate</label>
                    <input
                        id="localitate"
                        name="localitate"
                        type="text"
                        value="<?= htmlspecialchars((string) ($prefill['localitate'] ?? '')) ?>"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="judet">Judet</label>
                    <input
                        id="judet"
                        name="judet"
                        type="text"
                        value="<?= htmlspecialchars((string) ($prefill['judet'] ?? '')) ?>"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="telefon">Telefon</label>
                    <input
                        id="telefon"
                        name="telefon"
                        type="text"
                        value="<?= htmlspecialchars((string) ($prefill['telefon'] ?? '')) ?>"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
                    Trimite
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
    (function () {
        const button = document.getElementById('openapi-fetch');
        const cuiInput = document.getElementById('cui');
        if (!button || !cuiInput) {
            return;
        }
        const setValue = (id, value) => {
            const input = document.getElementById(id);
            if (!input || value === null || value === undefined || value === '') {
                return;
            }
            input.value = value;
        };
        button.addEventListener('click', () => {
            const cui = cuiInput.value.trim();
            if (!cui) {
                alert('Completeaza CUI-ul pentru prefill.');
                return;
            }
            const url = new URL(window.location.href);
            url.pathname = url.pathname.replace(/\/$/, '') + '/lookup';
            url.searchParams.set('cui', cui);
            fetch(url.toString(), { method: 'GET' })
                .then((response) => response.json())
                .then((data) => {
                    if (!data || !data.success) {
                        alert(data && data.message ? data.message : 'Eroare la OpenAPI.');
                        return;
                    }
                    const payload = data.data || {};
                    setValue('denumire', payload.denumire || '');
                    setValue('nr_reg_comertului', payload.nr_reg_comertului || '');
                    setValue('adresa', payload.adresa || '');
                    setValue('localitate', payload.localitate || '');
                    setValue('judet', payload.judet || '');
                    setValue('telefon', payload.telefon || '');
                })
                .catch(() => alert('Eroare la OpenAPI.'));
        });
    })();
</script>
