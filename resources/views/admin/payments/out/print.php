<?php $title = 'Plata furnizor #' . htmlspecialchars((string) ($payment['id'] ?? '')); ?>

<div class="mx-auto w-full max-w-5xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4 rounded border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">
                Plata furnizor #<?= (int) ($payment['id'] ?? 0) ?>
            </h1>
            <p class="mt-1 text-sm text-slate-600">
                <?= htmlspecialchars($payment['supplier_name'] ?? $payment['supplier_cui']) ?>
            </p>
        </div>
        <div class="text-right text-sm text-slate-600">
            <div>Data plata: <strong><?= htmlspecialchars($payment['paid_at'] ?? '') ?></strong></div>
            <div class="mt-2 inline-flex rounded bg-blue-50 px-3 py-1 text-base font-semibold text-blue-700">
                Total plata: <?= number_format((float) ($payment['amount'] ?? 0), 2, '.', ' ') ?> RON
            </div>
            <div class="mt-1 text-xs text-slate-500">Total alocat: <?= number_format((float) ($totalAllocated ?? 0), 2, '.', ' ') ?> RON</div>
        </div>
    </div>

    <div class="rounded border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Detalii alocare plata</h2>
            <button
                onclick="window.print()"
                class="no-print rounded border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
            >
                Printeaza / Salveaza PDF
            </button>
        </div>
        <?php if (empty($allocations)): ?>
            <div class="mt-4 text-sm text-slate-500">Plata nu are alocari.</div>
        <?php else: ?>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Factura furnizor</th>
                            <th class="px-3 py-2">Client refacturat</th>
                            <th class="px-3 py-2">Factura client</th>
                            <th class="px-3 py-2 text-right">Suma alocata</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allocations as $row): ?>
                            <?php
                                $clientName = trim((string) ($row['selected_client_name'] ?? ''));
                                $clientCui = preg_replace('/\D+/', '', (string) ($row['selected_client_cui'] ?? ''));
                                $clientLabel = $clientName !== '' ? $clientName : ($clientCui !== '' ? $clientCui : '—');
                                $clientInvoice = trim((string) ($row['fgo_series'] ?? '') . ' ' . (string) ($row['fgo_number'] ?? ''));
                            ?>
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2"><?= htmlspecialchars((string) ($row['invoice_number'] ?? '')) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($clientLabel) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($clientInvoice !== '' ? $clientInvoice : '—') ?></td>
                                <td class="px-3 py-2 text-right"><?= number_format((float) ($row['amount'] ?? 0), 2, '.', ' ') ?> RON</td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="border-t border-slate-200 bg-slate-50 font-semibold">
                            <td class="px-3 py-2" colspan="3">Total alocat</td>
                            <td class="px-3 py-2 text-right"><?= number_format((float) ($totalAllocated ?? 0), 2, '.', ' ') ?> RON</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
