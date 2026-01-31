<?php if (empty($invoices)): ?>
    <tr>
        <td colspan="11" class="px-4 py-6 text-center text-slate-500">
            Nu exista facturi importate.
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($invoices as $invoice): ?>
        <?php
            $status = $invoiceStatuses[$invoice->id] ?? null;
            $rowClass = 'border-b border-slate-100 block md:table-row';
            $collected = $status['collected'] ?? 0.0;
            $paid = $status['paid'] ?? 0.0;
            $clientTotal = $status['client_total'] ?? null;
            $supplierTotal = (float) $invoice->total_with_vat;
            $clientCollected = $clientTotal !== null ? ($collected + 0.01 >= $clientTotal) : ($collected > 0.01);

            if ($paid <= 0.009 && $clientCollected) {
                $rowClass = 'border-b border-slate-100 bg-rose-50 block md:table-row';
            } elseif ($paid > 0.009 && $paid + 0.01 < $supplierTotal) {
                $rowClass = 'border-b border-slate-100 bg-amber-50 block md:table-row';
            } elseif ($paid + 0.01 >= $supplierTotal) {
                $rowClass = 'border-b border-slate-100 bg-emerald-50 block md:table-row';
            }

            $clientFinal = $clientFinals[$invoice->id] ?? ['name' => '', 'cui' => ''];
            $clientLabel = $clientFinal['name'] !== '' ? $clientFinal['name'] : '—';
            $supplierInvoice = trim((string) ($invoice->invoice_series ?? '') . ' ' . (string) ($invoice->invoice_no ?? ''));
            if ($supplierInvoice === '') {
                $supplierInvoice = (string) ($invoice->invoice_number ?? '');
            }
            $fgoNumber = trim((string) ($invoice->fgo_series ?? '') . ' ' . (string) ($invoice->fgo_number ?? ''));
            $clientDate = (string) ($invoice->fgo_date ?? '');
            if ($clientDate === '' && !empty($invoice->fgo_number) && !empty($invoice->packages_confirmed_at)) {
                $clientDate = date('Y-m-d', strtotime((string) $invoice->packages_confirmed_at));
            }
        ?>
        <tr class="<?= $rowClass ?>">
            <td class="px-4 py-3 font-medium text-slate-900 block md:table-cell" data-label="Furnizor">
                <?= htmlspecialchars($invoice->supplier_name) ?>
            </td>
            <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Factura furnizor">
                <?= htmlspecialchars($supplierInvoice !== '' ? $supplierInvoice : '—') ?>
            </td>
            <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Data factura furnizor">
                <?= htmlspecialchars($invoice->issue_date) ?>
            </td>
            <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Total factura furnizor">
                <?= number_format($invoice->total_with_vat, 2, '.', ' ') ?>
            </td>
            <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Client final">
                <?= htmlspecialchars($clientLabel) ?>
            </td>
            <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Factura client">
                <?= htmlspecialchars($fgoNumber !== '' ? $fgoNumber : '—') ?>
            </td>
            <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Data factura client">
                <?= htmlspecialchars($clientDate !== '' ? $clientDate : '—') ?>
            </td>
            <td class="px-4 py-3 text-slate-600 block md:table-cell" data-label="Total factura client">
                <?= $clientTotal !== null ? number_format($clientTotal, 2, '.', ' ') : '—' ?>
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
