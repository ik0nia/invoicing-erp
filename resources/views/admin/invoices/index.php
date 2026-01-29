<?php $title = 'Facturi intrare'; ?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Facturi intrare</h1>
        <p class="mt-1 text-sm text-slate-500">Factura primite prin XML.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/facturi/import') ?>"
        class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
    >
        Importa XML
    </a>
</div>

<div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
            <tr>
                <th class="px-4 py-3">Factura</th>
                <th class="px-4 py-3">Serie</th>
                <th class="px-4 py-3">Numar</th>
                <th class="px-4 py-3">Furnizor</th>
                <th class="px-4 py-3">Data</th>
                <th class="px-4 py-3">Total (RON)</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-slate-500">
                        Nu exista facturi importate.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): ?>
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">
                            <?= htmlspecialchars($invoice->invoice_number) ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            <?= htmlspecialchars($invoice->invoice_series ?: '-') ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            <?= htmlspecialchars($invoice->invoice_no ?: '-') ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            <?= htmlspecialchars($invoice->supplier_name) ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            <?= htmlspecialchars($invoice->issue_date) ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            <?= number_format($invoice->total_with_vat, 2, '.', ' ') ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a
                                href="<?= App\Support\Url::to('admin/facturi') ?>?invoice_id=<?= (int) $invoice->id ?>"
                                class="text-blue-700 hover:text-blue-800"
                            >
                                Detalii →
                            </a>
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
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
