<?php $title = 'Adauga factura manual'; ?>
<?php
    $form = $form ?? [];
    $lines = $form['lines'] ?? [];
    if (empty($lines)) {
        $lines = [
            [
                'product_name' => '',
                'quantity' => '',
                'unit_code' => 'BUC',
                'unit_price' => '',
                'tax_percent' => '19',
            ],
        ];
    }
?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Adauga factura manual</h1>
            <p class="mt-1 text-sm text-slate-500">Completeaza datele facturii si produsele.</p>
        </div>
        <a
            href="<?= App\Support\Url::to('admin/facturi/import') ?>"
            class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
        >
            Inapoi la import
        </a>
    </div>

    <form method="POST" action="<?= App\Support\Url::to('admin/facturi/adauga') ?>" class="mt-6 space-y-6">
        <?= App\Support\Csrf::input() ?>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-semibold text-slate-700">Furnizor</div>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="supplier_name">Denumire</label>
                        <input
                            id="supplier_name"
                            name="supplier_name"
                            type="text"
                            value="<?= htmlspecialchars($form['supplier_name'] ?? '') ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="supplier_cui">CUI</label>
                        <input
                            id="supplier_cui"
                            name="supplier_cui"
                            type="text"
                            value="<?= htmlspecialchars($form['supplier_cui'] ?? '') ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        >
                    </div>
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-semibold text-slate-700">Client</div>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="customer_name">Denumire</label>
                        <input
                            id="customer_name"
                            name="customer_name"
                            type="text"
                            value="<?= htmlspecialchars($form['customer_name'] ?? '') ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="customer_cui">CUI</label>
                        <input
                            id="customer_cui"
                            name="customer_cui"
                            type="text"
                            value="<?= htmlspecialchars($form['customer_cui'] ?? '') ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="invoice_number">Numar factura</label>
                <input
                    id="invoice_number"
                    name="invoice_number"
                    type="text"
                    value="<?= htmlspecialchars($form['invoice_number'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="invoice_series">Serie</label>
                <input
                    id="invoice_series"
                    name="invoice_series"
                    type="text"
                    value="<?= htmlspecialchars($form['invoice_series'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="invoice_no">Numar (serie+numar)</label>
                <input
                    id="invoice_no"
                    name="invoice_no"
                    type="text"
                    value="<?= htmlspecialchars($form['invoice_no'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="currency">Moneda</label>
                <input
                    id="currency"
                    name="currency"
                    type="text"
                    value="<?= htmlspecialchars($form['currency'] ?? 'RON') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="issue_date">Data emitere</label>
                <input
                    id="issue_date"
                    name="issue_date"
                    type="date"
                    value="<?= htmlspecialchars($form['issue_date'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="due_date">Data scadenta</label>
                <input
                    id="due_date"
                    name="due_date"
                    type="date"
                    value="<?= htmlspecialchars($form['due_date'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
        </div>

        <div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">Produse</h2>
                <button
                    type="button"
                    class="rounded border border-blue-600 bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"
                    id="add-line"
                >
                    Adauga produs
                </button>
            </div>

            <div class="mt-3 overflow-x-auto rounded border border-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Denumire</th>
                            <th class="px-3 py-2">Cantitate</th>
                            <th class="px-3 py-2">UM</th>
                            <th class="px-3 py-2">Pret unit.</th>
                            <th class="px-3 py-2">TVA %</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="lines-body">
                        <?php foreach ($lines as $index => $line): ?>
                            <tr class="border-b border-slate-100" data-line-row>
                                <td class="px-3 py-2">
                                    <input
                                        name="lines[<?= (int) $index ?>][product_name]"
                                        type="text"
                                        value="<?= htmlspecialchars($line['product_name'] ?? '') ?>"
                                        class="w-full rounded border border-slate-300 px-2 py-1 text-sm"
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        name="lines[<?= (int) $index ?>][quantity]"
                                        type="text"
                                        value="<?= htmlspecialchars($line['quantity'] ?? '') ?>"
                                        class="w-24 rounded border border-slate-300 px-2 py-1 text-sm"
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        name="lines[<?= (int) $index ?>][unit_code]"
                                        type="text"
                                        value="<?= htmlspecialchars($line['unit_code'] ?? 'BUC') ?>"
                                        class="w-20 rounded border border-slate-300 px-2 py-1 text-sm"
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        name="lines[<?= (int) $index ?>][unit_price]"
                                        type="text"
                                        value="<?= htmlspecialchars($line['unit_price'] ?? '') ?>"
                                        class="w-28 rounded border border-slate-300 px-2 py-1 text-sm"
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        name="lines[<?= (int) $index ?>][tax_percent]"
                                        type="text"
                                        value="<?= htmlspecialchars($line['tax_percent'] ?? '19') ?>"
                                        class="w-20 rounded border border-slate-300 px-2 py-1 text-sm"
                                    >
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="text-xs font-semibold text-red-600 hover:text-red-700" data-remove-line>
                                        Sterge
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <button
                type="submit"
                class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
            >
                Salveaza factura
            </button>
        </div>
    </form>
</div>

<script>
    (function () {
        const body = document.getElementById('lines-body');
        const addButton = document.getElementById('add-line');
        if (!body || !addButton) {
            return;
        }

        const buildRow = (index) => {
            return `
                <tr class="border-b border-slate-100" data-line-row>
                    <td class="px-3 py-2">
                        <input name="lines[${index}][product_name]" type="text" class="w-full rounded border border-slate-300 px-2 py-1 text-sm">
                    </td>
                    <td class="px-3 py-2">
                        <input name="lines[${index}][quantity]" type="text" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm">
                    </td>
                    <td class="px-3 py-2">
                        <input name="lines[${index}][unit_code]" type="text" value="BUC" class="w-20 rounded border border-slate-300 px-2 py-1 text-sm">
                    </td>
                    <td class="px-3 py-2">
                        <input name="lines[${index}][unit_price]" type="text" class="w-28 rounded border border-slate-300 px-2 py-1 text-sm">
                    </td>
                    <td class="px-3 py-2">
                        <input name="lines[${index}][tax_percent]" type="text" value="19" class="w-20 rounded border border-slate-300 px-2 py-1 text-sm">
                    </td>
                    <td class="px-3 py-2 text-right">
                        <button type="button" class="text-xs font-semibold text-red-600 hover:text-red-700" data-remove-line> Sterge </button>
                    </td>
                </tr>
            `;
        };

        const refreshRemove = () => {
            body.querySelectorAll('[data-remove-line]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const row = btn.closest('[data-line-row]');
                    if (row) {
                        row.remove();
                    }
                });
            });
        };

        addButton.addEventListener('click', () => {
            const index = body.querySelectorAll('[data-line-row]').length;
            body.insertAdjacentHTML('beforeend', buildRow(index));
            refreshRemove();
        });

        refreshRemove();
    })();
</script>
