<?php $title = 'Adauga incasare'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Adauga incasare</h1>
        <p class="mt-1 text-sm text-slate-500">Selecteaza clientul si aloca incasarea pe facturi.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/incasari') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi
    </a>
</div>

<?php
    $prefillAmount = $prefillAmount ?? '';
    $prefillPaidAt = $prefillPaidAt ?? '';
    $prefillNotes  = $prefillNotes  ?? '';
    $rowHash       = $rowHash       ?? '';
?>

<form method="GET" action="<?= App\Support\Url::to('admin/incasari/adauga') ?>" class="mt-4">
    <?php if ($prefillAmount !== ''): ?>
        <input type="hidden" name="amount" value="<?= htmlspecialchars($prefillAmount) ?>">
    <?php endif; ?>
    <?php if ($prefillPaidAt !== ''): ?>
        <input type="hidden" name="paid_at" value="<?= htmlspecialchars($prefillPaidAt) ?>">
    <?php endif; ?>
    <?php if ($prefillNotes !== ''): ?>
        <input type="hidden" name="notes" value="<?= htmlspecialchars($prefillNotes) ?>">
    <?php endif; ?>
    <?php if ($rowHash !== ''): ?>
        <input type="hidden" name="row_hash" value="<?= htmlspecialchars($rowHash) ?>">
    <?php endif; ?>
    <label class="block text-sm font-medium text-slate-700" for="client_cui">Client</label>
    <select
        id="client_cui"
        name="client_cui"
        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        onchange="this.form.submit()"
    >
        <option value="">Selecteaza client</option>
        <?php foreach ($clients as $client): ?>
            <option value="<?= htmlspecialchars($client['cui']) ?>" <?= $clientCui === $client['cui'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($client['name']) ?> · <?= htmlspecialchars($client['cui']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<form method="POST" action="<?= App\Support\Url::to('admin/incasari/adauga') ?>" class="mt-6 space-y-6">
    <?= App\Support\Csrf::input() ?>
    <input type="hidden" name="client_cui" value="<?= htmlspecialchars($clientCui) ?>">
    <?php if ($rowHash !== ''): ?>
        <input type="hidden" name="row_hash" value="<?= htmlspecialchars($rowHash) ?>">
    <?php endif; ?>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="paid_at">Data incasarii</label>
            <input
                id="paid_at"
                name="paid_at"
                type="date"
                value="<?= htmlspecialchars($prefillPaidAt ?: date('Y-m-d')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="amount">Suma incasata</label>
            <div class="mt-1 flex gap-2">
                <input
                    id="amount"
                    name="amount"
                    type="text"
                    value="<?= htmlspecialchars($prefillAmount) ?>"
                    class="block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
                <button
                    type="button"
                    id="btn-distribute"
                    class="shrink-0 rounded border border-blue-300 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100"
                >
                    Aloca
                </button>
            </div>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700" for="notes">Observatii</label>
            <input
                id="notes"
                name="notes"
                type="text"
                value="<?= htmlspecialchars($prefillNotes) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>

    <div class="rounded border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700">
            Facturi client
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-2">Factura</th>
                        <th class="px-4 py-2">Data</th>
                        <th class="px-4 py-2">Total client</th>
                        <th class="px-4 py-2">Incasat</th>
                        <th class="px-4 py-2">Rest</th>
                        <th class="px-4 py-2">Aloca</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Selecteaza un client pentru a vedea facturile.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-2">
                                    <a
                                        href="<?= App\Support\Url::to('admin/facturi?invoice_id=' . (int) $invoice['id']) ?>"
                                        class="text-blue-700 hover:text-blue-800"
                                    >
                                        <?= htmlspecialchars($invoice['invoice_number']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($invoice['issue_date']) ?></td>
                                <td class="px-4 py-2"><?= number_format($invoice['total_client'], 2, '.', ' ') ?> RON</td>
                                <td class="px-4 py-2"><?= number_format($invoice['collected'], 2, '.', ' ') ?> RON</td>
                                <td class="px-4 py-2"><?= number_format($invoice['balance'], 2, '.', ' ') ?> RON</td>
                                <td class="px-4 py-2">
                                    <input
                                        name="allocations[<?= (int) $invoice['id'] ?>]"
                                        type="text"
                                        class="w-28 rounded border border-slate-300 px-2 py-1 text-sm allocation-input"
                                        data-balance="<?= htmlspecialchars(number_format($invoice['balance'], 2, '.', '')) ?>"
                                    >
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-end">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Salveaza incasarea
        </button>
    </div>
</form>

<script>
    (function () {
        const amountInput = document.getElementById('amount');
        const allocations = Array.from(document.querySelectorAll('.allocation-input'));

        const parseAmount = (value) => {
            if (!value) {
                return 0;
            }
            const normalized = String(value).replace(/\s+/g, '').replace(',', '.');
            const number = parseFloat(normalized);
            return Number.isFinite(number) ? number : 0;
        };

        const distribute = () => {
            const total = parseAmount(amountInput?.value);
            if (!amountInput || allocations.length === 0) {
                return;
            }

            if (total <= 0) {
                allocations.forEach((input) => {
                    input.value = '';
                });
                return;
            }

            const exactMatch = allocations.find((input) => {
                const balance = parseAmount(input.dataset.balance || '0');
                return Math.abs(balance - total) < 0.01;
            });

            if (exactMatch) {
                allocations.forEach((input) => {
                    input.value = '';
                });
                exactMatch.value = total.toFixed(2);
                return;
            }

            const sorted = allocations
                .map((input) => ({
                    input,
                    balance: parseAmount(input.dataset.balance || '0'),
                }))
                .filter((item) => item.balance > 0)
                .sort((a, b) => a.balance - b.balance);

            let remaining = total;
            allocations.forEach((input) => {
                input.value = '';
            });

            sorted.forEach((item) => {
                const allocate = Math.min(item.balance, remaining);
                item.input.value = allocate > 0 ? allocate.toFixed(2) : '';
                remaining = Math.max(0, remaining - allocate);
            });
        };

        if (amountInput) {
            amountInput.addEventListener('input', distribute);
        }

        const btnDistribute = document.getElementById('btn-distribute');
        if (btnDistribute) {
            btnDistribute.addEventListener('click', distribute);
        }

        // Auto-aloca daca suma e precompletata (venit din import extras bancar)
        if (amountInput && amountInput.value.trim() !== '') {
            distribute();
        }
    })();
</script>
