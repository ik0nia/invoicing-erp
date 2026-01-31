<?php
    $title = 'Facturi intrare';
    $isPlatform = $isPlatform ?? false;
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Facturi intrare</h1>
        <p class="mt-1 text-sm text-slate-500">Facturi importate din XML sau adaugate manual.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a
            href="<?= App\Support\Url::to('admin/facturi/export') ?>"
            class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow hover:bg-slate-50"
        >
            Export CSV
        </a>
        <a
            href="<?= App\Support\Url::to('admin/facturi/adauga') ?>"
            class="rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Adauga factura
        </a>
        <a
            href="<?= App\Support\Url::to('admin/facturi/import') ?>"
            class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow hover:bg-slate-50"
        >
            Importa XML
        </a>
    </div>
</div>

<div class="mt-4">
    <label class="block text-sm font-medium text-slate-700" for="invoice-search">Cauta factura</label>
    <input
        id="invoice-search"
        type="text"
        placeholder="Cauta dupa factura, client, furnizor, CUI, serie, numar"
        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        data-search-url="<?= App\Support\Url::to('admin/facturi/search') ?>"
    >
</div>

<div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm md:table">
        <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3">Furnizor</th>
                <th class="px-4 py-3">Factura furnizor</th>
                <th class="px-4 py-3">Data factura furnizor</th>
                <th class="px-4 py-3">Total factura furnizor</th>
                <th class="px-4 py-3">Client final</th>
                <th class="px-4 py-3">Factura client</th>
                <th class="px-4 py-3">Data factura client</th>
                <th class="px-4 py-3">Total factura client</th>
                <th class="px-4 py-3">Incasare client</th>
                <th class="px-4 py-3">Plata furnizor</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody id="invoice-table-body">
            <?php include BASE_PATH . '/resources/views/admin/invoices/rows.php'; ?>
        </tbody>
    </table>
</div>

<style>
    @media (max-width: 768px) {
        table thead {
            display: none;
        }
        table tbody tr {
            display: block;
            padding: 0.75rem 0.75rem;
        }
        table tbody td {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.4rem 0;
        }
        table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #334155;
        }
    }
</style>

<script>
    (function () {
        const input = document.getElementById('invoice-search');
        const body = document.getElementById('invoice-table-body');
        if (!input || !body) {
            return;
        }

        const url = input.getAttribute('data-search-url');
        if (!url) {
            return;
        }

        let timer = null;
        const runSearch = () => {
            const query = input.value || '';
            const fetchUrl = new URL(url, window.location.origin);
            fetchUrl.searchParams.set('q', query);

            fetch(fetchUrl.toString(), { credentials: 'same-origin' })
                .then((response) => response.text())
                .then((html) => {
                    body.innerHTML = html;
                })
                .catch(() => {
                    // ignore
                });
        };

        input.addEventListener('input', () => {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(runSearch, 200);
        });
    })();
</script>
