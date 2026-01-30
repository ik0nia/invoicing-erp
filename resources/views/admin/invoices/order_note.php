<?php
    $number = $invoice->order_note_no ?? 0;
    $date = $invoice->order_note_date ? date('d.m.Y', strtotime($invoice->order_note_date)) : '';
    $title = 'Nota de comanda';
?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">
                Nota de comanda nr. <?= htmlspecialchars((string) $number) ?> din data de <?= htmlspecialchars($date) ?>
            </h1>
        </div>
        <a
            href="<?= App\Support\Url::to('admin/facturi?invoice_id=' . (int) $invoice->id) ?>"
            class="no-print rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
        >
            Inapoi la factura
        </a>
    </div>

    <div class="mt-6 rounded border border-slate-200 bg-slate-50 p-4 text-sm">
        <div class="text-xs font-semibold text-slate-600">Client</div>
        <div class="mt-1 font-semibold text-slate-900"><?= htmlspecialchars($clientName ?: $clientCui) ?></div>
        <div class="mt-2 space-y-1 text-slate-600">
            <div>CUI: <?= htmlspecialchars($clientCui) ?></div>
            <?php if (!empty($clientCompany)): ?>
                <div>Adresa: <?= htmlspecialchars($clientCompany->adresa ?? '') ?>, <?= htmlspecialchars($clientCompany->localitate ?? '') ?>, <?= htmlspecialchars($clientCompany->judet ?? '') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto rounded border border-slate-200">
        <table class="w-full table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 70%">
                <col style="width: 30%">
            </colgroup>
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-4 py-2">Produs</th>
                    <th class="px-4 py-2">Cantitate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2 break-words"><?= htmlspecialchars($line->product_name) ?></td>
                        <td class="px-4 py-2"><?= number_format($line->quantity, 2, '.', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($lines)): ?>
                    <tr class="border-t border-slate-100">
                        <td colspan="2" class="px-4 py-3 text-sm text-slate-500">Nu exista produse in factura.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
