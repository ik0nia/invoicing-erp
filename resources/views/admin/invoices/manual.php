<?php $title = 'Adauga factura manual'; ?>
<?php
    $form = $form ?? [];
    $partners = $partners ?? [];
    $lines = $form['lines'] ?? [];
    $commissions = $commissions ?? [];
    $unitOptions = [
        'BUC', 'SET', 'PACH', 'BAX', 'CUT', 'ROLA',
        'KG', 'G', 'L', 'ML', 'M', 'M2', 'M3',
        'ORE', 'SERV', 'KWH', 'KM', 'MP', 'MC',
        'DOZA', 'FLACON', 'SAC', 'BIDON',
    ];
    $vatOptions = ['21', '11', '0'];
    $initialSupplierCui = preg_replace('/\D+/', '', (string) ($form['supplier_cui'] ?? ''));
    $initialSupplierName = trim((string) ($form['supplier_name'] ?? ''));
    $initialCustomerCui = preg_replace('/\D+/', '', (string) ($form['customer_cui'] ?? ''));
    $initialCustomerName = trim((string) ($form['customer_name'] ?? ''));
    $initialCommissionDisplay = '-';
    if ($initialSupplierCui !== '' && $initialCustomerCui !== '') {
        foreach ($commissions as $row) {
            $supplierCui = preg_replace('/\D+/', '', (string) ($row['supplier_cui'] ?? ''));
            $clientCui = preg_replace('/\D+/', '', (string) ($row['client_cui'] ?? ''));
            if ($supplierCui !== $initialSupplierCui || $clientCui !== $initialCustomerCui) {
                continue;
            }
            $initialCommissionDisplay = rtrim(rtrim(number_format((float) ($row['commission'] ?? 0), 4, '.', ''), '0'), '.');
            if ($initialCommissionDisplay === '') {
                $initialCommissionDisplay = '0';
            }
            break;
        }
    }
    $supplierDisplayText = $initialSupplierName !== '' && $initialSupplierCui !== ''
        ? ($initialSupplierName . ' - ' . $initialSupplierCui)
        : ($initialSupplierName !== '' ? $initialSupplierName : $initialSupplierCui);
    $customerDisplayText = $initialCustomerName !== '' && $initialCustomerCui !== ''
        ? ($initialCustomerName . ' - ' . $initialCustomerCui)
        : ($initialCustomerName !== '' ? $initialCustomerName : $initialCustomerCui);

    if (empty($lines)) {
        $lines = [
            [
                'product_name' => '',
                'quantity' => '',
                'unit_code' => 'BUC',
                'unit_price' => '',
                'tax_percent' => '21',
            ],
        ];
    }
?>

<div class="rounded-xl border border-blue-100 bg-blue-50 p-6 shadow-sm ring-1 ring-blue-100">
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
                    <?php if (!empty($partners)): ?>
                        <div
                            class="relative"
                            data-manual-supplier-picker
                            data-search-url="<?= App\Support\Url::to('admin/facturi/manual/suppliers-search') ?>"
                        >
                            <label class="block text-sm font-medium text-slate-700" for="supplier_picker_display">Denumire</label>
                            <input
                                id="supplier_picker_display"
                                type="text"
                                autocomplete="off"
                                value="<?= htmlspecialchars($supplierDisplayText) ?>"
                                placeholder="Cauta dupa denumire sau CUI"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                                data-manual-supplier-display
                            >
                            <input
                                id="supplier_cui"
                                type="hidden"
                                name="supplier_cui"
                                value="<?= htmlspecialchars($initialSupplierCui) ?>"
                                data-manual-supplier-value
                            >
                            <input
                                id="supplier_name_hidden"
                                type="hidden"
                                name="supplier_name"
                                value="<?= htmlspecialchars($initialSupplierName) ?>"
                                data-manual-supplier-name
                            >
                            <div
                                class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-slate-300 bg-white p-1 shadow-xl ring-1 ring-slate-200 divide-y divide-slate-100"
                                data-manual-supplier-list
                            ></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="supplier_cui_display">CUI</label>
                            <input
                                id="supplier_cui_display"
                                type="text"
                                value="<?= htmlspecialchars($initialSupplierCui) ?>"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm bg-slate-100"
                                readonly
                            >
                        </div>
                    <?php else: ?>
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
                    <?php endif; ?>
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-semibold text-slate-700">Client</div>
                <div class="mt-3 space-y-3">
                    <?php if (!empty($partners)): ?>
                        <div
                            class="relative"
                            data-manual-client-picker
                            data-search-url="<?= App\Support\Url::to('admin/facturi/manual/clients-search') ?>"
                        >
                            <label class="block text-sm font-medium text-slate-700" for="customer_picker_display">Client asociat</label>
                            <input
                                id="customer_picker_display"
                                type="text"
                                autocomplete="off"
                                value="<?= htmlspecialchars($customerDisplayText) ?>"
                                placeholder="Selecteaza mai intai furnizorul"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                                data-manual-client-display
                            >
                            <input
                                id="customer_cui"
                                type="hidden"
                                name="customer_cui"
                                value="<?= htmlspecialchars($initialCustomerCui) ?>"
                                data-manual-client-value
                            >
                            <input
                                id="customer_name_hidden"
                                type="hidden"
                                name="customer_name"
                                value="<?= htmlspecialchars($initialCustomerName) ?>"
                                data-manual-client-name
                            >
                            <div
                                class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-slate-300 bg-white p-1 shadow-xl ring-1 ring-slate-200 divide-y divide-slate-100"
                                data-manual-client-list
                            ></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="customer_cui_display">CUI</label>
                            <input
                                id="customer_cui_display"
                                type="text"
                                value="<?= htmlspecialchars($initialCustomerCui) ?>"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm bg-slate-100"
                                readonly
                            >
                        </div>
                        <?php $initialCommissionLabel = $initialCommissionDisplay !== '-' ? ($initialCommissionDisplay . '%') : '-'; ?>
                        <div class="text-xs font-semibold text-slate-600" id="customer_commission">
                            Comision: <?= htmlspecialchars($initialCommissionLabel) ?>
                        </div>
                    <?php else: ?>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="invoice_series">Serie (optional)</label>
                <input
                    id="invoice_series"
                    name="invoice_series"
                    type="text"
                    value="<?= htmlspecialchars($form['invoice_series'] ?? '') ?>"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="invoice_no">Numar factura</label>
                <input
                    id="invoice_no"
                    name="invoice_no"
                    type="text"
                    value="<?= htmlspecialchars($form['invoice_no'] ?? '') ?>"
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
                            <th class="px-3 py-2">UM</th>
                            <th class="px-3 py-2">Cantitate</th>
                            <th class="px-3 py-2">Pret unit.</th>
                            <th class="px-3 py-2">TVA %</th>
                            <th class="px-3 py-2">Total fara TVA</th>
                            <th class="px-3 py-2">Total cu TVA</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="lines-body">
                        <?php foreach ($lines as $index => $line): ?>
                            <tr class="border-b border-slate-100" data-line-row data-line-index="<?= (int) $index ?>">
                                <td class="px-3 py-2">
                                    <input
                                        name="lines[<?= (int) $index ?>][product_name]"
                                        type="text"
                                        value="<?= htmlspecialchars($line['product_name'] ?? '') ?>"
                                        class="w-full rounded border border-slate-300 px-2 py-1 text-sm"
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    <select
                                        name="lines[<?= (int) $index ?>][unit_code]"
                                        class="w-24 rounded border border-slate-300 px-2 py-1 text-sm"
                                    >
                                        <?php foreach ($unitOptions as $unit): ?>
                                            <option value="<?= htmlspecialchars($unit) ?>" <?= ($line['unit_code'] ?? 'BUC') === $unit ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($unit) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                                        name="lines[<?= (int) $index ?>][unit_price]"
                                        type="text"
                                        value="<?= htmlspecialchars($line['unit_price'] ?? '') ?>"
                                        class="w-28 rounded border border-slate-300 px-2 py-1 text-sm"
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    <select
                                        name="lines[<?= (int) $index ?>][tax_percent]"
                                        class="w-20 rounded border border-slate-300 px-2 py-1 text-sm"
                                    >
                                        <?php foreach ($vatOptions as $vat): ?>
                                            <option value="<?= $vat ?>" <?= (string) ($line['tax_percent'] ?? '21') === $vat ? 'selected' : '' ?>>
                                                <?= $vat ?>%
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-3 py-2 text-slate-600" data-line-total>0.00</td>
                                <td class="px-3 py-2 text-slate-600" data-line-total-vat>0.00</td>
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
            <div class="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-3">
                <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                    Total fara TVA: <strong data-total-without>0.00</strong> RON
                </div>
                <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                    TVA total: <strong data-total-vat>0.00</strong> RON
                </div>
                <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                    Total cu TVA: <strong data-total-with>0.00</strong> RON
                </div>
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

        const units = <?= json_encode($unitOptions, JSON_UNESCAPED_UNICODE) ?>;
        const vats = <?= json_encode($vatOptions, JSON_UNESCAPED_UNICODE) ?>;
        const initialSupplier = <?= json_encode($form['supplier_cui'] ?? '') ?>;
        const initialClient = <?= json_encode($form['customer_cui'] ?? '') ?>;
        const initialSupplierName = <?= json_encode($form['supplier_name'] ?? '') ?>;
        const initialClientName = <?= json_encode($form['customer_name'] ?? '') ?>;

        const buildOptions = (items, selected) => {
            return items.map((item) => {
                const isSelected = String(item) === String(selected) ? 'selected' : '';
                return `<option value="${item}" ${isSelected}>${item}${items === vats ? '%' : ''}</option>`;
            }).join('');
        };

        const buildRow = (index) => {
            return `
                <tr class="border-b border-slate-100" data-line-row data-line-index="${index}">
                    <td class="px-3 py-2">
                        <input name="lines[${index}][product_name]" type="text" class="w-full rounded border border-slate-300 px-2 py-1 text-sm">
                    </td>
                    <td class="px-3 py-2">
                        <select name="lines[${index}][unit_code]" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm">
                            ${buildOptions(units, 'BUC')}
                        </select>
                    </td>
                    <td class="px-3 py-2">
                        <input name="lines[${index}][quantity]" type="text" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm">
                    </td>
                    <td class="px-3 py-2">
                        <input name="lines[${index}][unit_price]" type="text" class="w-28 rounded border border-slate-300 px-2 py-1 text-sm">
                    </td>
                    <td class="px-3 py-2">
                        <select name="lines[${index}][tax_percent]" class="w-20 rounded border border-slate-300 px-2 py-1 text-sm">
                            ${buildOptions(vats, '21')}
                        </select>
                    </td>
                    <td class="px-3 py-2 text-slate-600" data-line-total>0.00</td>
                    <td class="px-3 py-2 text-slate-600" data-line-total-vat>0.00</td>
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
                        requestTotals();
                    }
                });
            });
        };

        const appendLine = (focusFirstField = false) => {
            const index = body.querySelectorAll('[data-line-row]').length;
            body.insertAdjacentHTML('beforeend', buildRow(index));
            refreshRemove();
            bindLineInputs();
            requestTotals();
            if (focusFirstField) {
                const rows = body.querySelectorAll('[data-line-row]');
                const lastRow = rows.length > 0 ? rows[rows.length - 1] : null;
                const firstInput = lastRow ? lastRow.querySelector('input[name*="[product_name]"]') : null;
                if (firstInput) {
                    firstInput.focus();
                }
            }
        };

        const isRowEmptyForEscapeRemove = (row) => {
            if (!row) {
                return false;
            }
            const productName = String(row.querySelector('input[name*="[product_name]"]')?.value || '').trim();
            const quantity = String(row.querySelector('input[name*="[quantity]"]')?.value || '').trim();
            const unitPrice = String(row.querySelector('input[name*="[unit_price]"]')?.value || '').trim();
            const unitCode = String(row.querySelector('select[name*="[unit_code]"]')?.value || 'BUC').trim().toUpperCase();
            const taxPercent = String(row.querySelector('select[name*="[tax_percent]"]')?.value || '21').trim();
            if (productName !== '' || quantity !== '' || unitPrice !== '') {
                return false;
            }

            return unitCode === 'BUC' && taxPercent === '21';
        };

        const handleEscapeRemoveLastEmptyLine = (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            const row = event.target.closest('[data-line-row]');
            if (!row) {
                return;
            }
            const rows = Array.from(body.querySelectorAll('[data-line-row]'));
            const lastRow = rows.length > 0 ? rows[rows.length - 1] : null;
            if (lastRow !== row || rows.length <= 1 || !isRowEmptyForEscapeRemove(row)) {
                return;
            }

            event.preventDefault();
            row.remove();
            requestTotals();
            const remainingRows = Array.from(body.querySelectorAll('[data-line-row]'));
            const remainingLastRow = remainingRows.length > 0 ? remainingRows[remainingRows.length - 1] : null;
            const focusInput = remainingLastRow
                ? remainingLastRow.querySelector('input[name*="[product_name]"]')
                : null;
            if (focusInput) {
                focusInput.focus();
            } else {
                addButton.focus();
            }
        };

        const handleTaxPercentTab = (event) => {
            if (event.key !== 'Tab' || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }
            const row = event.target.closest('[data-line-row]');
            if (!row) {
                return;
            }
            const rows = Array.from(body.querySelectorAll('[data-line-row]'));
            const lastRow = rows.length > 0 ? rows[rows.length - 1] : null;
            if (lastRow !== row) {
                return;
            }
            event.preventDefault();
            appendLine(true);
        };

        addButton.addEventListener('click', () => {
            appendLine(false);
        });

        const bindLineInputs = () => {
            body.querySelectorAll('input, select').forEach((input) => {
                if (!input.dataset.totalsInputBound) {
                    input.addEventListener('input', scheduleTotals);
                    input.dataset.totalsInputBound = '1';
                }
                if (!input.dataset.totalsChangeBound) {
                    input.addEventListener('change', scheduleTotals);
                    input.dataset.totalsChangeBound = '1';
                }
                if (input.matches('select[name*="[tax_percent]"]') && !input.dataset.taxTabBound) {
                    input.addEventListener('keydown', handleTaxPercentTab);
                    input.dataset.taxTabBound = '1';
                }
                if (!input.dataset.escapeLineBound) {
                    input.addEventListener('keydown', handleEscapeRemoveLastEmptyLine);
                    input.dataset.escapeLineBound = '1';
                }
            });
        };

        refreshRemove();

        const totals = {
            without: document.querySelector('[data-total-without]'),
            vat: document.querySelector('[data-total-vat]'),
            with: document.querySelector('[data-total-with]'),
        };

        const requestTotals = () => {
            const token = document.querySelector('input[name="_token"]');
            const lines = Array.from(body.querySelectorAll('[data-line-row]')).map((row) => {
                const index = row.dataset.lineIndex || '0';
                const quantity = row.querySelector('input[name*="[quantity]"]')?.value || '';
                const unitPrice = row.querySelector('input[name*="[unit_price]"]')?.value || '';
                const taxPercent = row.querySelector('select[name*="[tax_percent]"]')?.value || '';
                return {
                    index,
                    quantity,
                    unit_price: unitPrice,
                    tax_percent: taxPercent,
                };
            });

            const params = new URLSearchParams();
            params.append('_token', token ? token.value : '');
            params.append('lines_json', JSON.stringify(lines));

            fetch('<?= App\Support\Url::to('admin/facturi/calc-totals') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString(),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data || !data.success) {
                        return;
                    }
                    const lineMap = {};
                    (data.lines || []).forEach((line) => {
                        lineMap[String(line.index)] = line;
                    });
                    body.querySelectorAll('[data-line-row]').forEach((row) => {
                        const index = String(row.dataset.lineIndex || '');
                        const line = lineMap[index];
                        const totalCell = row.querySelector('[data-line-total]');
                        const totalVatCell = row.querySelector('[data-line-total-vat]');
                        if (line && totalCell && totalVatCell) {
                            totalCell.textContent = Number(line.total || 0).toFixed(2);
                            totalVatCell.textContent = Number(line.total_vat || 0).toFixed(2);
                        } else {
                            if (totalCell) totalCell.textContent = '0.00';
                            if (totalVatCell) totalVatCell.textContent = '0.00';
                        }
                    });

                    if (totals.without) totals.without.textContent = Number(data.totals?.without_vat || 0).toFixed(2);
                    if (totals.vat) totals.vat.textContent = Number(data.totals?.vat || 0).toFixed(2);
                    if (totals.with) totals.with.textContent = Number(data.totals?.with_vat || 0).toFixed(2);
                })
                .catch(() => {});
        };

        let totalsTimer = null;
        const scheduleTotals = () => {
            if (totalsTimer) {
                clearTimeout(totalsTimer);
            }
            totalsTimer = setTimeout(requestTotals, 250);
        };

        bindLineInputs();
        requestTotals();

        const supplierPicker = document.querySelector('[data-manual-supplier-picker]');
        const clientPicker = document.querySelector('[data-manual-client-picker]');
        const supplierDisplayInput = supplierPicker ? supplierPicker.querySelector('[data-manual-supplier-display]') : null;
        const supplierValueInput = supplierPicker ? supplierPicker.querySelector('[data-manual-supplier-value]') : null;
        const supplierNameInput = supplierPicker ? supplierPicker.querySelector('[data-manual-supplier-name]') : null;
        const supplierList = supplierPicker ? supplierPicker.querySelector('[data-manual-supplier-list]') : null;
        const supplierCuiDisplay = document.getElementById('supplier_cui_display');
        const clientDisplayInput = clientPicker ? clientPicker.querySelector('[data-manual-client-display]') : null;
        const clientValueInput = clientPicker ? clientPicker.querySelector('[data-manual-client-value]') : null;
        const clientNameInput = clientPicker ? clientPicker.querySelector('[data-manual-client-name]') : null;
        const clientList = clientPicker ? clientPicker.querySelector('[data-manual-client-list]') : null;
        const customerCuiDisplay = document.getElementById('customer_cui_display');
        const commissionDisplay = document.getElementById('customer_commission');
        const supplierSearchUrl = supplierPicker ? String(supplierPicker.getAttribute('data-search-url') || '') : '';
        const clientSearchUrl = clientPicker ? String(clientPicker.getAttribute('data-search-url') || '') : '';

        const escapeHtml = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        const digitsOnly = (value) => String(value || '').replace(/\D+/g, '');
        const buildLabel = (name, cui) => {
            const cleanName = String(name || '').trim();
            const cleanCui = digitsOnly(cui);
            if (cleanName !== '' && cleanCui !== '') {
                return `${cleanName} - ${cleanCui}`;
            }
            return cleanName !== '' ? cleanName : cleanCui;
        };
        const formatCommission = (value) => {
            const number = Number(value);
            if (!Number.isFinite(number)) {
                return '';
            }
            return number.toFixed(4).replace(/\.?0+$/, '');
        };
        const setCommissionText = (value) => {
            if (!commissionDisplay) {
                return;
            }
            const text = String(value || '').trim();
            commissionDisplay.textContent = text !== '' ? `Comision: ${text}%` : 'Comision: -';
        };

        let supplierRequestId = 0;
        let clientRequestId = 0;
        let supplierTimer = null;
        let clientTimer = null;

        const clearSupplierList = () => {
            if (!supplierList) {
                return;
            }
            supplierList.innerHTML = '';
            supplierList.classList.add('hidden');
        };
        const clearClientList = () => {
            if (!clientList) {
                return;
            }
            clientList.innerHTML = '';
            clientList.classList.add('hidden');
        };

        const applySupplierSelection = (item, preserveClient = false) => {
            if (!supplierDisplayInput || !supplierValueInput || !supplierNameInput) {
                return;
            }
            const cui = digitsOnly(item.cui || '');
            const name = String(item.name || '').trim();
            const label = buildLabel(name, cui);
            const previousSupplier = digitsOnly(supplierValueInput.value);
            supplierValueInput.value = cui;
            supplierNameInput.value = name;
            supplierDisplayInput.value = label;
            if (supplierCuiDisplay) {
                supplierCuiDisplay.value = cui;
            }
            clearSupplierList();
            if (!preserveClient || previousSupplier !== cui) {
                if (clientValueInput) {
                    clientValueInput.value = '';
                }
                if (clientNameInput) {
                    clientNameInput.value = '';
                }
                if (clientDisplayInput) {
                    clientDisplayInput.value = '';
                }
                if (customerCuiDisplay) {
                    customerCuiDisplay.value = '';
                }
                setCommissionText('');
            }
        };

        const applyClientSelection = (item) => {
            if (!clientDisplayInput || !clientValueInput || !clientNameInput) {
                return;
            }
            const cui = digitsOnly(item.cui || '');
            const name = String(item.name || '').trim();
            const commission = formatCommission(item.commission);
            clientValueInput.value = cui;
            clientNameInput.value = name;
            clientDisplayInput.value = buildLabel(name, cui);
            if (customerCuiDisplay) {
                customerCuiDisplay.value = cui;
            }
            setCommissionText(commission);
            clearClientList();
        };

        const renderSupplierItems = (items) => {
            if (!supplierList) {
                return;
            }
            if (!Array.isArray(items) || items.length === 0) {
                supplierList.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">Nu exista rezultate.</div>';
                supplierList.classList.remove('hidden');
                return;
            }
            supplierList.innerHTML = items.map((item) => {
                const name = escapeHtml(item.name || item.cui || '');
                const cui = escapeHtml(item.cui || '');
                return `
                    <button
                        type="button"
                        class="block w-full rounded px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700"
                        data-manual-supplier-item
                        data-cui="${cui}"
                        data-name="${escapeHtml(item.name || '')}"
                    >
                        <div class="font-medium text-slate-900">${name}</div>
                        <div class="text-xs text-slate-500">${cui}</div>
                    </button>
                `;
            }).join('');
            supplierList.classList.remove('hidden');
        };

        const renderClientItems = (items, supplierSelected) => {
            if (!clientList) {
                return;
            }
            if (!supplierSelected) {
                clientList.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">Selecteaza mai intai furnizorul.</div>';
                clientList.classList.remove('hidden');
                return;
            }
            if (!Array.isArray(items) || items.length === 0) {
                clientList.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">Nu exista clienti asociati.</div>';
                clientList.classList.remove('hidden');
                return;
            }
            clientList.innerHTML = items.map((item) => {
                const name = escapeHtml(item.name || item.cui || '');
                const cui = escapeHtml(item.cui || '');
                const commission = formatCommission(item.commission);
                const commissionHtml = commission !== ''
                    ? `<div class="text-[11px] text-emerald-700">Comision: ${escapeHtml(commission)}%</div>`
                    : '';
                return `
                    <button
                        type="button"
                        class="block w-full rounded px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700"
                        data-manual-client-item
                        data-cui="${cui}"
                        data-name="${escapeHtml(item.name || '')}"
                        data-commission="${escapeHtml(commission)}"
                    >
                        <div class="font-medium text-slate-900">${name}</div>
                        <div class="text-xs text-slate-500">${cui}</div>
                        ${commissionHtml}
                    </button>
                `;
            }).join('');
            clientList.classList.remove('hidden');
        };

        const fetchSuppliers = (term) => {
            if (!supplierSearchUrl) {
                return;
            }
            const currentRequestId = ++supplierRequestId;
            const url = new URL(supplierSearchUrl, window.location.origin);
            url.searchParams.set('term', term);
            url.searchParams.set('limit', '20');
            fetch(url.toString(), { credentials: 'same-origin' })
                .then((response) => response.json())
                .then((data) => {
                    if (currentRequestId !== supplierRequestId) {
                        return;
                    }
                    if (!data || data.success !== true) {
                        renderSupplierItems([]);
                        return;
                    }
                    renderSupplierItems(data.items || []);
                })
                .catch(() => {
                    if (currentRequestId !== supplierRequestId) {
                        return;
                    }
                    renderSupplierItems([]);
                });
        };

        const fetchClients = (term) => {
            if (!clientSearchUrl) {
                return;
            }
            const supplierCui = supplierValueInput ? digitsOnly(supplierValueInput.value) : '';
            const currentRequestId = ++clientRequestId;
            if (supplierCui === '') {
                renderClientItems([], false);
                return;
            }
            const url = new URL(clientSearchUrl, window.location.origin);
            url.searchParams.set('supplier_cui', supplierCui);
            url.searchParams.set('term', term);
            url.searchParams.set('limit', '20');
            fetch(url.toString(), { credentials: 'same-origin' })
                .then((response) => response.json())
                .then((data) => {
                    if (currentRequestId !== clientRequestId) {
                        return;
                    }
                    if (!data || data.success !== true) {
                        renderClientItems([], true);
                        return;
                    }
                    renderClientItems(data.items || [], true);
                })
                .catch(() => {
                    if (currentRequestId !== clientRequestId) {
                        return;
                    }
                    renderClientItems([], true);
                });
        };

        if (supplierDisplayInput && supplierValueInput && supplierNameInput) {
            supplierDisplayInput.addEventListener('focus', () => {
                fetchSuppliers(supplierDisplayInput.value.trim());
            });
            supplierDisplayInput.addEventListener('input', () => {
                supplierValueInput.value = '';
                supplierNameInput.value = '';
                if (supplierCuiDisplay) {
                    supplierCuiDisplay.value = '';
                }
                if (supplierTimer) {
                    clearTimeout(supplierTimer);
                }
                const query = supplierDisplayInput.value.trim();
                supplierTimer = window.setTimeout(() => {
                    fetchSuppliers(query);
                }, 200);
            });
            supplierDisplayInput.addEventListener('keydown', (event) => {
                if (event.key !== 'Tab' || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey) {
                    return;
                }
                if (!clientDisplayInput) {
                    return;
                }
                if (digitsOnly(supplierValueInput.value) === '' && supplierList) {
                    const firstItem = supplierList.querySelector('[data-manual-supplier-item]');
                    if (firstItem) {
                        applySupplierSelection({
                            cui: firstItem.getAttribute('data-cui') || '',
                            name: firstItem.getAttribute('data-name') || '',
                        });
                    }
                }
                if (digitsOnly(supplierValueInput.value) !== '') {
                    event.preventDefault();
                    clientDisplayInput.focus();
                    fetchClients(clientDisplayInput.value.trim());
                }
            });
            supplierDisplayInput.addEventListener('blur', () => {
                window.setTimeout(() => {
                    clearSupplierList();
                    if (digitsOnly(supplierValueInput.value) !== '') {
                        return;
                    }
                    const maybeCui = digitsOnly(supplierDisplayInput.value);
                    if (maybeCui !== '') {
                        supplierValueInput.value = maybeCui;
                        if (supplierCuiDisplay) {
                            supplierCuiDisplay.value = maybeCui;
                        }
                    }
                }, 120);
            });
        }

        if (clientDisplayInput && clientValueInput && clientNameInput) {
            clientDisplayInput.addEventListener('focus', () => {
                fetchClients(clientDisplayInput.value.trim());
            });
            clientDisplayInput.addEventListener('input', () => {
                clientValueInput.value = '';
                clientNameInput.value = '';
                if (customerCuiDisplay) {
                    customerCuiDisplay.value = '';
                }
                setCommissionText('');
                if (clientTimer) {
                    clearTimeout(clientTimer);
                }
                const query = clientDisplayInput.value.trim();
                clientTimer = window.setTimeout(() => {
                    fetchClients(query);
                }, 200);
            });
            clientDisplayInput.addEventListener('blur', () => {
                window.setTimeout(() => {
                    clearClientList();
                    if (digitsOnly(clientValueInput.value) !== '') {
                        return;
                    }
                    const maybeCui = digitsOnly(clientDisplayInput.value);
                    if (maybeCui !== '') {
                        clientValueInput.value = maybeCui;
                        if (customerCuiDisplay) {
                            customerCuiDisplay.value = maybeCui;
                        }
                    }
                }, 120);
            });
        }

        if (supplierList) {
            const handleSupplierSelect = (event) => {
                if (event.type === 'mousedown' && typeof window.PointerEvent !== 'undefined') {
                    return;
                }
                const target = event.target.closest('[data-manual-supplier-item]');
                if (!target) {
                    return;
                }
                event.preventDefault();
                applySupplierSelection({
                    cui: target.getAttribute('data-cui') || '',
                    name: target.getAttribute('data-name') || '',
                });
            };
            supplierList.addEventListener('pointerdown', handleSupplierSelect);
            supplierList.addEventListener('mousedown', handleSupplierSelect);
        }

        if (clientList) {
            const handleClientSelect = (event) => {
                if (event.type === 'mousedown' && typeof window.PointerEvent !== 'undefined') {
                    return;
                }
                const target = event.target.closest('[data-manual-client-item]');
                if (!target) {
                    return;
                }
                event.preventDefault();
                applyClientSelection({
                    cui: target.getAttribute('data-cui') || '',
                    name: target.getAttribute('data-name') || '',
                    commission: target.getAttribute('data-commission') || '',
                });
            };
            clientList.addEventListener('pointerdown', handleClientSelect);
            clientList.addEventListener('mousedown', handleClientSelect);
        }

        if (supplierValueInput && supplierDisplayInput && digitsOnly(supplierValueInput.value) === '' && digitsOnly(initialSupplier) !== '') {
            supplierValueInput.value = digitsOnly(initialSupplier);
        }
        if (supplierNameInput && supplierNameInput.value.trim() === '' && String(initialSupplierName || '').trim() !== '') {
            supplierNameInput.value = String(initialSupplierName || '').trim();
        }
        if (supplierDisplayInput && supplierDisplayInput.value.trim() === '') {
            supplierDisplayInput.value = buildLabel(
                supplierNameInput ? supplierNameInput.value : '',
                supplierValueInput ? supplierValueInput.value : ''
            );
        }
        if (supplierCuiDisplay && supplierValueInput) {
            supplierCuiDisplay.value = digitsOnly(supplierValueInput.value);
        }

        if (clientValueInput && clientDisplayInput && digitsOnly(clientValueInput.value) === '' && digitsOnly(initialClient) !== '') {
            clientValueInput.value = digitsOnly(initialClient);
        }
        if (clientNameInput && clientNameInput.value.trim() === '' && String(initialClientName || '').trim() !== '') {
            clientNameInput.value = String(initialClientName || '').trim();
        }
        if (clientDisplayInput && clientDisplayInput.value.trim() === '') {
            clientDisplayInput.value = buildLabel(
                clientNameInput ? clientNameInput.value : '',
                clientValueInput ? clientValueInput.value : ''
            );
        }
        if (customerCuiDisplay && clientValueInput) {
            customerCuiDisplay.value = digitsOnly(clientValueInput.value);
        }
        if (commissionDisplay && /Comision:\s*-\s*%?$/.test(commissionDisplay.textContent || '')) {
            setCommissionText('');
        }
    })();
</script>
