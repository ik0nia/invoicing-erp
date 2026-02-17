<?php
    $invoiceColspan = !empty($canViewPaymentDetails) ? 11 : 9;
?>
<?php if (empty($invoices)): ?>
    <tr>
        <td colspan="<?= (int) $invoiceColspan ?>" class="px-3 py-6 text-center text-slate-500">
            Nu exista facturi importate.
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($invoices as $invoice): ?>
        <?php
            $status = $invoiceStatuses[$invoice->id] ?? null;
            $rowClass = 'border-b border-slate-100 block md:table-row';
            $clientTotal = $status['client_total'] ?? null;
            $hasStorno = !empty($invoice->fgo_storno_number) || !empty($invoice->fgo_storno_series) || !empty($invoice->fgo_storno_link);
            if ($hasStorno) {
                $rowClass = 'border-b border-slate-100 bg-slate-100 block md:table-row';
            } elseif ($status) {
                if ($status['supplier_label'] === 'Platit integral') {
                    $rowClass = 'border-b border-slate-100 bg-emerald-50 block md:table-row';
                } elseif ($status['supplier_label'] === 'Platit partial') {
                    $rowClass = 'border-b border-slate-100 bg-amber-50 block md:table-row';
                } elseif ($status['supplier_label'] === 'Neplatit' && $status['client_label'] === 'Incasat integral') {
                    $rowClass = 'border-b border-slate-100 bg-rose-50 block md:table-row';
                }
            }
            $hoverClass = str_contains($rowClass, 'bg-') ? 'hover:brightness-95' : 'hover:bg-slate-50';

            $clientFinal = $clientFinals[$invoice->id] ?? ['name' => '', 'cui' => ''];
            $clientLabel = $clientFinal['name'] !== '' ? $clientFinal['name'] : '—';
            $supplierInvoice = trim((string) ($invoice->invoice_series ?? '') . ' ' . (string) ($invoice->invoice_no ?? ''));
            if ($supplierInvoice === '') {
                $supplierInvoice = (string) ($invoice->invoice_number ?? '');
            }
            $fgoNumber = trim((string) ($invoice->fgo_series ?? '') . ' ' . (string) ($invoice->fgo_number ?? ''));
            $fgoLink = (string) ($invoice->fgo_link ?? '');
            $clientDate = (string) ($invoice->fgo_date ?? '');
            if ($clientDate === '' && !empty($invoice->fgo_number) && !empty($invoice->packages_confirmed_at)) {
                $clientDate = date('Y-m-d', strtotime((string) $invoice->packages_confirmed_at));
            }
            $rowUrl = App\Support\Url::to('admin/facturi') . '?invoice_id=' . (int) $invoice->id;
            $createdAt = (string) ($invoice->created_at ?? '');
            $createdLabel = '—';
            if ($createdAt !== '') {
                $createdTs = strtotime($createdAt);
                if ($createdTs !== false) {
                    $createdLabel = date('d.m.y H:i', $createdTs);
                }
            }
        ?>
        <tr class="<?= $rowClass ?> invoice-row cursor-pointer <?= $hoverClass ?>" data-url="<?= htmlspecialchars($rowUrl) ?>">
            <td class="px-3 py-3 text-xs text-slate-500 block md:table-cell" data-label="Creat">
                <?= htmlspecialchars($createdLabel) ?>
            </td>
            <td class="px-3 py-3 font-medium text-slate-900 block md:table-cell" data-label="Furnizor">
                <?= htmlspecialchars($invoice->supplier_name) ?>
            </td>
            <td class="px-3 py-3 text-slate-600 block md:table-cell" data-label="Factura furnizor">
                <div class="inline-flex items-center gap-2">
                    <?php if (empty($invoice->xml_path) && !empty($canShowRequestAlert)): ?>
                        <?php
                            $warningTitle = 'Lipseste factura furnizor. Intra pe factura pentru incarcare.';
                            if (!empty($invoice->supplier_request_at)) {
                                $requestTs = strtotime((string) $invoice->supplier_request_at);
                                $requestLabel = $requestTs ? date('d.m.Y H:i', $requestTs) : (string) $invoice->supplier_request_at;
                                $warningTitle = 'Lipseste factura furnizor. Solicitare la ' . $requestLabel . '.';
                            }
                        ?>
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-100 text-rose-700" title="<?= htmlspecialchars($warningTitle) ?>">
                            !
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($invoice->xml_path)): ?>
                        <a
                            href="<?= App\Support\Url::to('admin/facturi/fisier') ?>?invoice_id=<?= (int) $invoice->id ?>"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-700 hover:text-blue-800"
                        >
                            <?= htmlspecialchars($supplierInvoice !== '' ? $supplierInvoice : '—') ?>
                        </a>
                    <?php else: ?>
                        <?= htmlspecialchars($supplierInvoice !== '' ? $supplierInvoice : '—') ?>
                    <?php endif; ?>
                </div>
            </td>
            <td class="px-3 py-3 text-slate-600 block md:table-cell" data-label="Data factura furnizor">
                <?= htmlspecialchars($invoice->issue_date) ?>
            </td>
            <td class="px-3 py-3 text-slate-600 block md:table-cell" data-label="Total factura furnizor">
                <?= number_format($invoice->total_with_vat, 2, '.', ' ') ?>
            </td>
            <td class="px-3 py-3 text-slate-600 block md:table-cell" data-label="Client final">
                <?php if ($hasStorno): ?>
                    <span class="font-semibold text-slate-400 line-through"><?= htmlspecialchars($clientLabel) ?></span>
                <?php else: ?>
                    <span class="font-semibold text-slate-900"><?= htmlspecialchars($clientLabel) ?></span>
                <?php endif; ?>
            </td>
            <td class="px-3 py-3 text-slate-600 block md:table-cell" data-label="Factura client">
                <?php if ($fgoNumber !== '' && $fgoLink !== ''): ?>
                    <a href="<?= htmlspecialchars($fgoLink) ?>" target="_blank" rel="noopener" class="text-blue-700 hover:text-blue-800">
                        <?= htmlspecialchars($fgoNumber) ?>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($fgoNumber !== '' ? $fgoNumber : '—') ?>
                <?php endif; ?>
            </td>
            <td class="px-3 py-3 text-slate-600 block md:table-cell" data-label="Data factura client">
                <?= htmlspecialchars($clientDate !== '' ? $clientDate : '—') ?>
            </td>
            <td class="px-3 py-3 text-slate-600 block md:table-cell" data-label="Total factura client">
                <?= $clientTotal !== null ? number_format($clientTotal, 2, '.', ' ') : '—' ?>
            </td>
            <?php if (!empty($canViewPaymentDetails)): ?>
                <td class="px-3 py-3 text-slate-600 block md:table-cell" data-label="Incasare client">
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
                <td class="px-3 py-3 text-slate-600 block md:table-cell" data-label="Plata furnizor">
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
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
