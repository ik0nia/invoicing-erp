<?php $title = 'Dashboard'; ?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Dashboard</h1>
    <p class="mt-2 text-sm text-slate-500">
        Bine ai venit<?= isset($user) ? ', ' . htmlspecialchars($user->name) : '' ?>.
    </p>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded border border-slate-200 bg-slate-50 p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="text-sm font-medium text-slate-700">Ultimele 10 facturi introduse</div>
                <a
                    href="<?= App\Support\Url::to('admin/facturi') ?>"
                    class="text-xs font-semibold text-blue-700 hover:text-blue-800"
                >
                    Vezi toate
                </a>
            </div>
            <?php if (empty($latestInvoices ?? [])): ?>
                <div class="mt-3 text-sm text-slate-500">Nu exista facturi importate.</div>
            <?php else: ?>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach ($latestInvoices as $invoice): ?>
                        <li class="rounded border border-slate-200 bg-white px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <a
                                    href="<?= App\Support\Url::to('admin/facturi?invoice_id=' . (int) $invoice['id']) ?>"
                                    class="font-semibold text-blue-700 hover:text-blue-800"
                                >
                                    <?= htmlspecialchars($invoice['invoice_number']) ?>
                                </a>
                                <span class="text-xs text-slate-500"><?= htmlspecialchars($invoice['issue_date'] ?? '') ?></span>
                            </div>
                            <div class="text-xs text-slate-600">
                                <?= htmlspecialchars($invoice['supplier_name'] ?? '') ?>
                            </div>
                            <div class="text-xs text-slate-600">
                                Total: <?= number_format((float) ($invoice['total_with_vat'] ?? 0), 2, '.', ' ') ?> RON
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="rounded border border-slate-200 bg-slate-50 p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="text-sm font-medium text-slate-700">Pachete confirmate si nefacturate</div>
                <a
                    href="<?= App\Support\Url::to('admin/pachete-confirmate') ?>"
                    class="text-xs font-semibold text-blue-700 hover:text-blue-800"
                >
                    Vezi pachete
                </a>
            </div>
            <?php if (empty($pendingPackages ?? [])): ?>
                <div class="mt-3 text-sm text-slate-500">Nu exista pachete de facturat.</div>
            <?php else: ?>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach ($pendingPackages as $package): ?>
                        <?php $label = 'Pachet de produse #' . $package['package_no']; ?>
                        <li class="rounded border border-slate-200 bg-white px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <a
                                    href="<?= App\Support\Url::to('admin/facturi?invoice_id=' . (int) $package['invoice_in_id']) ?>"
                                    class="font-semibold text-blue-700 hover:text-blue-800"
                                >
                                    <?= htmlspecialchars($label) ?>
                                </a>
                                <span class="text-xs text-slate-500"><?= htmlspecialchars($package['invoice_number'] ?? '') ?></span>
                            </div>
                            <div class="text-xs text-slate-600">
                                <?= htmlspecialchars($package['supplier_name'] ?? '') ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
