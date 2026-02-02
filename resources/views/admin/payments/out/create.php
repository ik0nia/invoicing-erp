<?php $title = 'Adauga plata'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Adauga plata</h1>
        <p class="mt-1 text-sm text-slate-500">Selecteaza furnizorul si aloca plata pe facturi.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/plati') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi
    </a>
</div>

<form method="GET" action="<?= App\Support\Url::to('admin/plati/adauga') ?>" class="mt-4">
    <label class="block text-sm font-medium text-slate-700" for="supplier_cui">Furnizor</label>
    <select
        id="supplier_cui"
        name="supplier_cui"
        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        onchange="this.form.submit()"
    >
        <option value="">Selecteaza furnizor</option>
        <?php foreach ($suppliers as $supplier): ?>
            <option value="<?= htmlspecialchars($supplier['cui']) ?>" <?= $supplierCui === $supplier['cui'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($supplier['name']) ?> · <?= htmlspecialchars($supplier['cui']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<form method="POST" action="<?= App\Support\Url::to('admin/plati/adauga') ?>" class="mt-6 space-y-6">
    <?= App\Support\Csrf::input() ?>
    <input type="hidden" name="supplier_cui" value="<?= htmlspecialchars($supplierCui) ?>">

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="paid_at">Data platii</label>
            <input
                id="paid_at"
                name="paid_at"
                type="date"
                value="<?= htmlspecialchars(date('Y-m-d')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="amount">Suma de plata</label>
            <input
                id="amount"
                name="amount"
                type="text"
                inputmode="decimal"
                value=""
                placeholder="<?= htmlspecialchars(number_format((float) ($supplierSummary['due'] ?? 0), 2, '.', '')) ?>"
                data-available="<?= htmlspecialchars(number_format((float) ($supplierSummary['due'] ?? 0), 2, '.', '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
            <p class="mt-1 text-xs text-slate-500">
                Disponibil incasat net: <strong><?= number_format((float) ($supplierSummary['due'] ?? 0), 2, '.', ' ') ?> RON</strong>.
                Introdu suma si o voi distribui automat pe facturi (dupa incasarile nete).
            </p>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700" for="notes">Observatii</label>
            <input
                id="notes"
                name="notes"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>

    <div class="rounded border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700">
            Facturi furnizor
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-2">Plata</th>
                        <th class="px-4 py-2">Factura</th>
                        <th class="px-4 py-2">Data</th>
                        <th class="px-4 py-2">Total furnizor</th>
                        <th class="px-4 py-2">Incasat net</th>
                        <th class="px-4 py-2">Platit</th>
                        <th class="px-4 py-2">Disponibil</th>
                        <th class="px-4 py-2">Aloca</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-500">Selecteaza un furnizor pentru a vedea facturile.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-2">
                                    <input
                                        type="checkbox"
                                        class="invoice-check"
                                        data-allocatable="<?= htmlspecialchars(number_format($invoice['available'], 2, '.', '')) ?>"
                                        <?= $invoice['available'] <= 0 ? 'disabled' : '' ?>
                                    >
                                </td>
                                <td class="px-4 py-2">
                                    <a
                                        href="<?= App\Support\Url::to('admin/facturi?invoice_id=' . (int) $invoice['id']) ?>"
                                        class="text-blue-700 hover:text-blue-800"
                                    >
                                        <?= htmlspecialchars($invoice['invoice_number']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($invoice['issue_date']) ?></td>
                                <td class="px-4 py-2"><?= number_format($invoice['total_supplier'], 2, '.', ' ') ?> RON</td>
                                <td class="px-4 py-2">
                                    <?= number_format($invoice['collected_net'], 2, '.', ' ') ?> RON
                                    <?php if (!empty($invoice['commission'])): ?>
                                        <div class="text-xs text-slate-500">Comision <?= number_format($invoice['commission'], 2, '.', ' ') ?>%</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2"><?= number_format($invoice['paid'], 2, '.', ' ') ?> RON</td>
                                <td class="px-4 py-2"><?= number_format($invoice['available'], 2, '.', ' ') ?> RON</td>
                                <td class="px-4 py-2">
                                    <input
                                        name="allocations[<?= (int) $invoice['id'] ?>]"
                                        type="text"
                                        class="w-28 rounded border border-slate-300 px-2 py-1 text-sm allocation-input bg-white"
                                        data-allocatable="<?= htmlspecialchars(number_format($invoice['available'], 2, '.', '')) ?>"
                                        disabled
                                    >
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <div class="text-xs text-slate-500" id="payment-warning">Selecteaza cel putin o factura.</div>
        <button
            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
            id="submit-payment"
            disabled
        >
            Salveaza plata
        </button>
    </div>
</form>

<script>
    (function () {
        const amountInput = document.getElementById('amount');
        const checks = Array.from(document.querySelectorAll('.invoice-check'));
        const allocations = Array.from(document.querySelectorAll('.allocation-input'));
        const submitBtn = document.getElementById('submit-payment');
        const warning = document.getElementById('payment-warning');

        if (!amountInput || checks.length === 0 || allocations.length === 0) {
            return;
        }

        const parseAmount = (value) => {
            if (!value) {
                return 0;
            }
            const normalized = String(value).replace(/\s+/g, '').replace(',', '.');
            const number = parseFloat(normalized);
            return Number.isFinite(number) ? number : 0;
        };

        const formatAmount = (value) => {
            return Number.isFinite(value) ? value.toFixed(2) : '0.00';
        };

        const availableTotal = parseAmount(amountInput.dataset.available || '0');

        const setWarning = (allocated, target, hasSelection) => {
            if (!warning) {
                return;
            }
            if (!hasSelection) {
                warning.textContent = 'Selecteaza cel putin o factura.';
                return;
            }
            if (allocated > availableTotal + 0.01) {
                warning.textContent = 'Suma alocata depaseste disponibilul incasat.';
                return;
            }
            if (target > availableTotal + 0.01) {
                warning.textContent = `Ai introdus ${formatAmount(target)} RON, dar disponibilul este ${formatAmount(availableTotal)} RON.`;
                return;
            }
            if (target > 0) {
                warning.textContent = `Total alocat: ${formatAmount(allocated)} RON din ${formatAmount(target)} RON.`;
                return;
            }
            warning.textContent = `Total alocat: ${formatAmount(allocated)} RON.`;
        };

        const clearAll = () => {
            checks.forEach((check, idx) => {
                const input = allocations[idx];
                check.checked = false;
                input.value = '';
                input.disabled = true;
            });
        };

        const autoAllocate = (target) => {
            let remaining = target;
            let allocated = 0;

            checks.forEach((check, idx) => {
                const input = allocations[idx];
                const allocatable = parseAmount(input.dataset.allocatable || '0');
                if (allocatable <= 0 || remaining <= 0) {
                    check.checked = false;
                    input.value = '';
                    input.disabled = true;
                    return;
                }
                check.checked = true;
                input.disabled = false;
                const value = Math.min(allocatable, remaining);
                input.value = value > 0 ? value.toFixed(2) : '';
                remaining -= value;
                allocated += value;
            });

            return allocated;
        };

        const recomputeManual = () => {
            let allocated = 0;
            let hasSelection = false;

            checks.forEach((check, idx) => {
                const input = allocations[idx];
                const allocatable = parseAmount(input.dataset.allocatable || '0');

                if (!check.checked) {
                    input.value = '';
                    input.disabled = true;
                    return;
                }

                hasSelection = true;
                input.disabled = false;
                let value = parseAmount(input.value || '0');
                if (value > allocatable) {
                    value = allocatable;
                    input.value = value.toFixed(2);
                }
                allocated += value;
            });

            if (submitBtn) {
                submitBtn.disabled = !hasSelection || allocated <= 0 || allocated > availableTotal + 0.01;
            }
            setWarning(allocated, parseAmount(amountInput.value || '0'), hasSelection);
        };

        amountInput.addEventListener('input', () => {
            const targetRaw = parseAmount(amountInput.value || '0');
            if (targetRaw <= 0) {
                clearAll();
                recomputeManual();
                return;
            }
            const target = Math.min(targetRaw, availableTotal);
            const allocated = autoAllocate(target);
            if (submitBtn) {
                submitBtn.disabled = allocated <= 0 || allocated > availableTotal + 0.01;
            }
            setWarning(allocated, targetRaw, allocated > 0);
        });

        checks.forEach((check) => {
            check.addEventListener('change', recomputeManual);
        });

        allocations.forEach((input) => {
            input.addEventListener('input', recomputeManual);
        });

        recomputeManual();
    })();
</script>
