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
            <input
                id="cui"
                name="cui"
                type="text"
                value="<?= htmlspecialchars($form['cui'] ?? '') ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700" for="tip_firma">Tip firma</label>
            <select id="tip_firma" name="tip_firma" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Alege</option>
                <?php foreach (['SRL', 'SA', 'PFA', 'II', 'IF'] as $tip): ?>
                    <option value="<?= $tip ?>" <?= ($form['tip_firma'] ?? '') === $tip ? 'selected' : '' ?>>
                        <?= $tip ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
            <label class="block text-sm font-medium text-slate-700" for="tip_companie">Tip companie</label>
            <select id="tip_companie" name="tip_companie" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Alege</option>
                <?php foreach (['client' => 'Client', 'furnizor' => 'Furnizor', 'intermediar' => 'Intermediar'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= ($form['tip_companie'] ?? '') === $value ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
    </div>

    <div class="mt-6">
        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Salveaza compania
        </button>
    </div>
</form>
