<?php $title = 'Istoric plati'; ?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Istoric plati</h1>
        <p class="mt-1 text-sm text-slate-500">Filtreaza platile dupa data si furnizor.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/plati') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi
    </a>
</div>

<form method="GET" action="<?= App\Support\Url::to('admin/plati/istoric') ?>" class="mt-4 grid gap-4 md:grid-cols-4">
    <div>
        <label class="block text-sm font-medium text-slate-700" for="supplier_cui">Furnizor</label>
        <select
            id="supplier_cui"
            name="supplier_cui"
            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        >
            <option value="">Toti furnizorii</option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?= htmlspecialchars($supplier['supplier_cui']) ?>" <?= $supplierCui === $supplier['supplier_cui'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($supplier['supplier_name'] ?? $supplier['supplier_cui']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700" for="date_from">De la</label>
        <input
            id="date_from"
            name="date_from"
            type="date"
            value="<?= htmlspecialchars($dateFrom ?? '') ?>"
            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        >
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700" for="date_to">Pana la</label>
        <input
            id="date_to"
            name="date_to"
            type="date"
            value="<?= htmlspecialchars($dateTo ?? '') ?>"
            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        >
    </div>
    <div class="flex items-end gap-2">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Filtreaza
        </button>
        <a
            href="<?= App\Support\Url::to('admin/plati/istoric') ?>"
            class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Reseteaza
        </a>
    </div>
</form>

<div class="mt-4 flex flex-wrap items-center justify-between gap-3">
    <form method="POST" action="<?= App\Support\Url::to('admin/plati/ordine-plata') ?>" id="payment-order-form">
        <?= App\Support\Csrf::input() ?>
        <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom ?? '') ?>">
        <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo ?? '') ?>">
        <button
            type="submit"
            class="rounded border border-blue-600 bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"
        >
            Genereaza OP CSV
        </button>
    </form>
    <a
        href="<?= App\Support\Url::to('admin/plati/export?' . http_build_query(['supplier_cui' => $supplierCui, 'date_from' => $dateFrom, 'date_to' => $dateTo])) ?>"
        class="rounded border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
    >
        Export CSV
    </a>
</div>

<div class="mt-6 space-y-4">
    <?php $highlightId = isset($paymentId) ? (int) $paymentId : 0; ?>
    <?php if (empty($payments)): ?>
        <div class="rounded border border-slate-200 bg-white p-6 text-sm text-slate-500">
            Nu exista plati in acest interval.
        </div>
    <?php else: ?>
        <?php foreach ($payments as $payment): ?>
            <?php $isHighlight = $highlightId > 0 && (int) $payment['id'] === $highlightId; ?>
            <div
                id="payment-out-<?= (int) $payment['id'] ?>"
                class="rounded border <?= $isHighlight ? 'border-blue-400 bg-blue-50' : 'border-slate-200 bg-white' ?> p-4 text-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="font-semibold text-slate-900">
                        Plata #<?= (int) $payment['id'] ?> · <?= htmlspecialchars($payment['supplier_name'] ?? $payment['supplier_cui']) ?>
                    </div>
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                    <?php
                        $cui = (string) ($payment['supplier_cui'] ?? '');
                        $mark = $orderMarks[$cui] ?? '';
                    ?>
                    <label class="inline-flex items-center gap-2 rounded border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-700">
                        <input type="checkbox" name="supplier_cuis[]" value="<?= htmlspecialchars($cui) ?>" form="payment-order-form" data-has-order="<?= $mark !== '' ? '1' : '0' ?>">
                        <span>OP</span>
                        <?php if ($mark !== ''): ?>
                            <span class="text-[10px] text-amber-700">OP: <?= htmlspecialchars(date('d.m.Y', strtotime($mark))) ?></span>
                        <?php endif; ?>
                    </label>
                        <span><?= htmlspecialchars($payment['paid_at']) ?> · <?= number_format((float) $payment['amount'], 2, '.', ' ') ?> RON</span>
                        <form method="POST" action="<?= App\Support\Url::to('admin/plati/sterge') ?>">
                            <?= App\Support\Csrf::input() ?>
                            <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                            <button
                                class="rounded border border-red-200 bg-red-50 px-2 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-100"
                                onclick="return confirm('Stergi plata selectata?')"
                            >
                                Sterge
                            </button>
                        </form>
                    </div>
                </div>
                <?php if (!empty($payment['notes'])): ?>
                    <div class="mt-2 text-xs text-slate-600"><?= htmlspecialchars($payment['notes']) ?></div>
                <?php endif; ?>

                <?php $rows = $allocations[$payment['id']] ?? []; ?>
                <?php if (!empty($rows)): ?>
                    <div class="mt-3 overflow-x-auto rounded border border-slate-100">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-3 py-2">Factura furnizor</th>
                                    <th class="px-3 py-2">Client</th>
                                    <th class="px-3 py-2">Factura client</th>
                                    <th class="px-3 py-2">Suma alocata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                        $clientName = trim((string) ($row['customer_name'] ?? ''));
                                        $clientCui = preg_replace('/\D+/', '', (string) ($row['selected_client_cui'] ?? ''));
                                        $clientLabel = $clientName !== '' ? $clientName : ($clientCui !== '' ? $clientCui : '—');
                                        $clientInvoice = trim((string) ($row['fgo_series'] ?? '') . ' ' . (string) ($row['fgo_number'] ?? ''));
                                    ?>
                                    <tr class="border-t border-slate-100">
                                        <td class="px-3 py-2"><?= htmlspecialchars($row['invoice_number'] ?? '') ?></td>
                                        <td class="px-3 py-2"><?= htmlspecialchars($clientLabel) ?></td>
                                        <td class="px-3 py-2"><?= htmlspecialchars($clientInvoice !== '' ? $clientInvoice : '—') ?></td>
                                        <td class="px-3 py-2"><?= number_format((float) $row['amount'], 2, '.', ' ') ?> RON</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="mt-2 text-xs font-semibold text-amber-700">
                        Plata nealocata.
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    (function () {
        const form = document.getElementById('payment-order-form');
        if (!form) {
            return;
        }
        form.addEventListener('submit', (event) => {
            const checks = Array.from(document.querySelectorAll('input[name="supplier_cuis[]"]:checked'));
            if (checks.length === 0) {
                event.preventDefault();
                alert('Selecteaza cel putin un furnizor.');
                return;
            }
            const hasGenerated = checks.some((item) => item.getAttribute('data-has-order') === '1');
            if (hasGenerated) {
                const ok = confirm('Exista deja ordin de plata generat pentru unul dintre furnizori. Vrei sa continui?');
                if (!ok) {
                    event.preventDefault();
                }
            }
        });
    })();
</script>
