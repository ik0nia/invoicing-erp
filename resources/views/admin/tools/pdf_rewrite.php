<?php
$title = 'Prelucrare PDF aviz';
$company = is_array($company ?? null) ? $company : [];
$defaultSeriesFrom = (string) ($defaultSeriesFrom ?? 'A-MVN');
$defaultSeriesTo = (string) ($defaultSeriesTo ?? 'A-DEON');

$companyRows = [
    'Denumire' => (string) ($company['denumire'] ?? ''),
    'CUI' => (string) ($company['cui'] ?? ''),
    'Nr. Reg. Comertului' => (string) ($company['nr_reg_comertului'] ?? ''),
    'Adresa' => (string) ($company['adresa'] ?? ''),
    'Localitate' => (string) ($company['localitate'] ?? ''),
    'Judet' => (string) ($company['judet'] ?? ''),
    'Tara' => (string) ($company['tara'] ?? ''),
    'Email' => (string) ($company['email'] ?? ''),
    'Telefon' => (string) ($company['telefon'] ?? ''),
    'Banca' => (string) ($company['banca'] ?? ''),
    'IBAN' => (string) ($company['iban'] ?? ''),
];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Prelucrare PDF aviz</h1>
        <p class="mt-1 text-sm text-slate-600">
            Incarca un aviz PDF, inlocuieste prefixul seriei (ex: A-MVN -> A-DEON) si actualizeaza datele de furnizor din Setari.
        </p>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="xl:col-span-2 rounded-xl border border-blue-200 bg-blue-50 p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Incarcare si prelucrare</h2>
        <p class="mt-1 text-sm text-slate-600">
            Se inlocuiesc automat campurile furnizorului (denumire, CUI, Reg. Com., adresa, banca, IBAN, email, telefon) cu valorile din Setari.
        </p>

        <form
            method="POST"
            action="<?= App\Support\Url::to('admin/utile/prelucrare-pdf') ?>"
            enctype="multipart/form-data"
            class="mt-4 grid gap-4 md:grid-cols-2"
        >
            <?= App\Support\Csrf::input() ?>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700" for="source_pdf">PDF aviz</label>
                <input
                    id="source_pdf"
                    name="source_pdf"
                    type="file"
                    accept=".pdf,application/pdf"
                    required
                    class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm"
                >
                <p class="mt-1 text-xs text-slate-600">Fisier PDF text, max. 15 MB.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="series_from">Prefix serie sursa</label>
                <input
                    id="series_from"
                    name="series_from"
                    type="text"
                    value="<?= htmlspecialchars($defaultSeriesFrom) ?>"
                    class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700" for="series_to">Prefix serie noua</label>
                <input
                    id="series_to"
                    name="series_to"
                    type="text"
                    value="<?= htmlspecialchars($defaultSeriesTo) ?>"
                    class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm"
                >
            </div>

            <div class="md:col-span-2 flex items-center gap-3 pt-1">
                <button
                    type="submit"
                    class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
                >
                    Prelucreaza si descarca PDF
                </button>
                <span class="text-xs text-slate-600">Daca generatorul PDF este indisponibil, se descarca textul prelucrat (.txt).</span>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Date emitent din Setari</h2>
        <p class="mt-1 text-sm text-slate-600">Aceste informatii se folosesc la prelucrare.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php foreach ($companyRows as $label => $value): ?>
                        <tr>
                            <td class="w-44 px-3 py-2 font-medium text-slate-700"><?= htmlspecialchars($label) ?></td>
                            <td class="px-3 py-2 text-slate-900"><?= htmlspecialchars($value !== '' ? $value : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a
            href="<?= App\Support\Url::to('admin/setari') ?>"
            class="mt-4 inline-flex items-center text-sm font-medium text-blue-700 hover:text-blue-800"
        >
            Editeaza Date companie in Setari
        </a>
    </div>
</div>
