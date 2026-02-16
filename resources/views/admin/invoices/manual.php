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
    $commissionOptions = [];
    foreach ($commissions as $row) {
        $commissionOptions[] = [
            'supplier_cui' => (string) ($row['supplier_cui'] ?? ''),
            'client_cui' => (string) ($row['client_cui'] ?? ''),
            'client_name' => (string) ($row['client_name'] ?? ''),
            'commission' => (float) ($row['commission'] ?? 0),
        ];
    }

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
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="supplier_select">Denumire</label>
                            <select
                                id="supplier_select"
                                name="supplier_cui"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">Selecteaza furnizor</option>
                                <?php foreach ($partners as $partner): ?>
                                    <option
                                        value="<?= htmlspecialchars($partner->cui) ?>"
                                        data-name="<?= htmlspecialchars($partner->denumire) ?>"
                                        <?= ($form['supplier_cui'] ?? '') === $partner->cui ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($partner->denumire) ?> · <?= htmlspecialchars($partner->cui) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="supplier_name" value="<?= htmlspecialchars($form['supplier_name'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="supplier_cui_display">CUI</label>
                            <input
                                id="supplier_cui_display"
                                type="text"
                                value="<?= htmlspecialchars($form['supplier_cui'] ?? '') ?>"
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
                    <?php if (!empty($commissionOptions)): ?>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="customer_select">Client asociat</label>
                            <select
                                id="customer_select"
                                name="customer_cui"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">Selecteaza furnizorul</option>
                            </select>
                            <input type="hidden" name="customer_name" value="<?= htmlspecialchars($form['customer_name'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="customer_cui_display">CUI</label>
                            <input
                                id="customer_cui_display"
                                type="text"
                                value="<?= htmlspecialchars($form['customer_cui'] ?? '') ?>"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm bg-slate-100"
                                readonly
                            >
                        </div>
                        <div class="text-xs font-semibold text-slate-600" id="customer_commission">Comision: -</div>
                    <?php elseif (!empty($partners)): ?>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="customer_select">Denumire</label>
                            <select
                                id="customer_select"
                                name="customer_cui"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">Selecteaza client</option>
                                <?php foreach ($partners as $partner): ?>
                                    <option
                                        value="<?= htmlspecialchars($partner->cui) ?>"
                                        data-name="<?= htmlspecialchars($partner->denumire) ?>"
                                        <?= ($form['customer_cui'] ?? '') === $partner->cui ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($partner->denumire) ?> · <?= htmlspecialchars($partner->cui) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="customer_name" value="<?= htmlspecialchars($form['customer_name'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="customer_cui_display">CUI</label>
                            <input
                                id="customer_cui_display"
                                type="text"
                                value="<?= htmlspecialchars($form['customer_cui'] ?? '') ?>"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm bg-slate-100"
                                readonly
                            >
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
                            <th class="px-3 py-2">Cantitate</th>
                            <th class="px-3 py-2">UM</th>
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
                                    <input
                                        name="lines[<?= (int) $index ?>][quantity]"
                                        type="text"
                                        value="<?= htmlspecialchars($line['quantity'] ?? '') ?>"
                                        class="w-24 rounded border border-slate-300 px-2 py-1 text-sm"
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
        const commissions = <?= json_encode($commissionOptions, JSON_UNESCAPED_UNICODE) ?>;
        const initialSupplier = <?= json_encode($form['supplier_cui'] ?? '') ?>;
        const initialClient = <?= json_encode($form['customer_cui'] ?? '') ?>;

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
                        <input name="lines[${index}][quantity]" type="text" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm">
                    </td>
                    <td class="px-3 py-2">
                        <select name="lines[${index}][unit_code]" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm">
                            ${buildOptions(units, 'BUC')}
                        </select>
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

        addButton.addEventListener('click', () => {
            const index = body.querySelectorAll('[data-line-row]').length;
            body.insertAdjacentHTML('beforeend', buildRow(index));
            refreshRemove();
            bindLineInputs();
            requestTotals();
        });

        refreshRemove();

        const bindLineInputs = () => {
            body.querySelectorAll('input, select').forEach((input) => {
                input.addEventListener('input', scheduleTotals);
                input.addEventListener('change', scheduleTotals);
            });
        };

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

        const supplierSelect = document.getElementById('supplier_select');
        const supplierCui = document.getElementById('supplier_cui_display');
        const supplierName = document.querySelector('input[name="supplier_name"]');
        const customerSelect = document.getElementById('customer_select');
        const customerCui = document.getElementById('customer_cui_display');
        const customerName = document.querySelector('input[name="customer_name"]');
        const commissionDisplay = document.getElementById('customer_commission');

        const commissionsBySupplier = {};
        commissions.forEach((item) => {
            if (!item.supplier_cui || !item.client_cui) {
                return;
            }
            if (!commissionsBySupplier[item.supplier_cui]) {
                commissionsBySupplier[item.supplier_cui] = [];
            }
            commissionsBySupplier[item.supplier_cui].push(item);
        });

        const updateSupplier = () => {
            if (!supplierSelect || !supplierCui || !supplierName) {
                return;
            }
            const option = supplierSelect.selectedOptions[0];
            supplierCui.value = supplierSelect.value || '';
            supplierName.value = option ? (option.dataset.name || '') : '';
        };

        const updateCustomerMeta = () => {
            if (!customerSelect || !customerCui || !customerName) {
                return;
            }
            const option = customerSelect.selectedOptions[0];
            customerCui.value = customerSelect.value || '';
            customerName.value = option ? (option.dataset.name || '') : '';
            if (commissionDisplay) {
                const commission = option ? (option.dataset.commission || '') : '';
                commissionDisplay.textContent = commission ? `Comision: ${commission}%` : 'Comision: -';
            }
        };

        const updateCustomerOptions = (supplierCuiValue, selectedCui) => {
            if (!customerSelect) {
                return;
            }
            const items = commissionsBySupplier[supplierCuiValue] || [];
            customerSelect.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = items.length ? 'Selecteaza client' : 'Nu exista clienti asociati';
            customerSelect.appendChild(placeholder);
            customerSelect.disabled = items.length === 0;

            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.client_cui;
                option.dataset.name = item.client_name || '';
                option.dataset.commission = String(item.commission ?? '');
                const labelName = item.client_name || item.client_cui;
                option.textContent = `${labelName} · ${item.client_cui} · ${item.commission}%`;
                if (selectedCui && selectedCui === item.client_cui) {
                    option.selected = true;
                }
                customerSelect.appendChild(option);
            });

            updateCustomerMeta();
        };

        if (supplierSelect) {
            supplierSelect.addEventListener('change', () => {
                updateSupplier();
                updateCustomerOptions(supplierSelect.value, '');
            });
        }

        if (customerSelect) {
            customerSelect.addEventListener('change', updateCustomerMeta);
        }

        updateSupplier();

        if (supplierSelect && commissions.length > 0) {
            if (initialSupplier && supplierSelect.value !== initialSupplier) {
                supplierSelect.value = initialSupplier;
                updateSupplier();
            }
            updateCustomerOptions(supplierSelect.value, initialClient);
        } else {
            updateCustomerMeta();
        }
    })();
</script>
