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

<div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm md:table">
        <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3">Factura</th>
                <th class="px-4 py-3">Serie</th>
                <th class="px-4 py-3">Numar</th>
                <th class="px-4 py-3">Furnizor</th>
                <th class="px-4 py-3">Data</th>
                <th class="px-4 py-3">Total (RON)</th>
                <th class="px-4 py-3">Incasare client</th>
                <th class="px-4 py-3">Plata furnizor</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="9" class="px-4 py-6 text-center text-slate-500">
                        Nu exista facturi importate.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): ?>
                    <?php $status = $invoiceStatuses[$invoice->id] ?? null; ?>
                    <tr class="border-b border-slate-100 block md:table-row">
                        <td class="px-4 py-3 font-medium text-slate-900 block md:table-cell" data-label="Factura">
                            <?= htmlspecialchars($invoice->invoice_number) ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Serie">
                            <?= htmlspecialchars($invoice->invoice_series ?: '-') ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Numar">
                            <?= htmlspecialchars($invoice->invoice_no ?: '-') ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Furnizor">
                            <?= htmlspecialchars($invoice->supplier_name) ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Data">
                            <?= htmlspecialchars($invoice->issue_date) ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Total (RON)">
                            <?= number_format($invoice->total_with_vat, 2, '.', ' ') ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Incasare client">
                            <?php if ($status && $status['client_total'] !== null): ?>
                                <div class="font-medium text-slate-900">
                                    <?= number_format($status['collected'], 2, '.', ' ') ?> / <?= number_format($status['client_total'], 2, '.', ' ') ?>
                                </div>
                                <div class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $status['client_class'] ?>">
                                    <?= htmlspecialchars($status['client_label']) ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-slate-500">Client nesetat</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Plata furnizor">
                            <?php if ($status): ?>
                                <div class="font-medium text-slate-900">
                                    <?= number_format($status['paid'], 2, '.', ' ') ?> / <?= number_format($invoice->total_with_vat, 2, '.', ' ') ?>
                                </div>
                                <div class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $status['supplier_class'] ?>">
                                    <?= htmlspecialchars($status['supplier_label']) ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-slate-500">—</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right block md:table-cell" data-label="Actiuni">
                            <a
                                href="<?= App\Support\Url::to('admin/facturi') ?>?invoice_id=<?= (int) $invoice->id ?>"
                                class="text-blue-700 hover:text-blue-800"
                            >
                                Detalii →
                            </a>
                            <?php if (!empty($isPlatform)): ?>
                                <form method="POST" action="<?= App\Support\Url::to('admin/facturi/sterge') ?>" class="inline">
                                    <?= App\Support\Csrf::input() ?>
                                    <input type="hidden" name="invoice_id" value="<?= (int) $invoice->id ?>">
                                    <button
                                        type="submit"
                                        class="ml-2 text-red-600 hover:text-red-700"
                                        onclick="return confirm('Sigur vrei sa stergi factura?')"
                                    >
                                        Sterge
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
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
