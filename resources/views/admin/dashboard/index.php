<?php
    $title = 'Dashboard';
    $canAccessSaga = $canAccessSaga ?? false;
    $showEnrollmentPendingCard = !empty($showEnrollmentPendingCard);
    $pendingEnrollmentSummary = is_array($pendingEnrollmentSummary ?? null) ? $pendingEnrollmentSummary : [];
    $showCommissionDailyChart = !empty($showCommissionDailyChart);
    $commissionDailyChart = is_array($commissionDailyChart ?? null) ? $commissionDailyChart : [];
?>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-900">Dashboard</h1>
    <p class="mt-2 text-sm text-slate-500">
        Bine ai venit<?= isset($user) ? ', ' . htmlspecialchars($user->name) : '' ?>.
    </p>

    <?php if ($showEnrollmentPendingCard): ?>
        <?php
            $pendingTotal = (int) ($pendingEnrollmentSummary['total'] ?? 0);
            $pendingSuppliers = (int) ($pendingEnrollmentSummary['suppliers'] ?? 0);
            $pendingClients = (int) ($pendingEnrollmentSummary['clients'] ?? 0);
            $pendingToday = (int) ($pendingEnrollmentSummary['submitted_today'] ?? 0);
            $pendingAssociations = (int) ($pendingEnrollmentSummary['association_pending'] ?? 0);
        ?>
        <div class="mt-4 rounded border border-amber-200 bg-amber-50 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-medium text-amber-900">Inrolari in asteptare</div>
                    <div class="mt-1 text-2xl font-semibold text-amber-800"><?= $pendingTotal ?></div>
                    <div class="text-xs text-amber-700">
                        Furnizori: <?= $pendingSuppliers ?> |
                        Clienti: <?= $pendingClients ?> |
                        Trimise azi: <?= $pendingToday ?>
                    </div>
                    <?php if ($pendingAssociations > 0): ?>
                        <div class="mt-1 text-xs text-amber-700">
                            Solicitari asociere in asteptare: <?= $pendingAssociations ?>
                        </div>
                    <?php endif; ?>
                </div>
                <a
                    href="<?= App\Support\Url::to('admin/inrolari') ?>"
                    class="inline-flex items-center rounded border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                >
                    Vezi inrolarile
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($isSupplierUser)): ?>
        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-medium text-slate-700">Facturi emise luna curenta</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">
                    <?= (int) ($supplierMonthCount ?? 0) ?>
                </div>
                <div class="text-xs text-slate-500">Total facturi din luna aceasta.</div>
            </div>

            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-sm font-medium text-slate-700">Ultimele 5 facturi emise</div>
                    <a href="<?= App\Support\Url::to('admin/facturi') ?>" class="text-xs font-semibold text-blue-700 hover:text-blue-800">
                        Vezi toate
                    </a>
                </div>
                <?php if (empty($supplierLatestInvoices ?? [])): ?>
                    <div class="mt-3 text-sm text-slate-500">Nu exista facturi emise.</div>
                <?php else: ?>
                    <ul class="mt-3 space-y-2 text-sm">
                        <?php foreach ($supplierLatestInvoices as $invoice): ?>
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
                                    Catre: <?= htmlspecialchars($invoice['client_label'] ?? '—') ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-medium text-slate-700">Facturi de incasat</div>
                <?php if (empty($supplierDueInvoices ?? [])): ?>
                    <div class="mt-3 text-sm text-slate-500">Nu exista facturi de incasat.</div>
                <?php else: ?>
                    <ul class="mt-3 space-y-2 text-sm">
                        <?php foreach ($supplierDueInvoices as $invoice): ?>
                            <li class="rounded border border-slate-200 bg-white px-3 py-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
                                    <span class="text-xs text-slate-500"><?= htmlspecialchars($invoice['issue_date'] ?? '') ?></span>
                                </div>
                                <div class="text-xs text-slate-600">
                                    Catre: <?= htmlspecialchars($invoice['client_label'] ?? '—') ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="mt-6 grid gap-4 lg:grid-cols-4">
            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-medium text-slate-700">Total facturat luna curenta</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">
                    <?= number_format((float) ($monthIssuedTotal ?? 0), 2, '.', ' ') ?> RON
                </div>
                <div class="text-xs text-slate-500"><?= (int) ($monthIssuedCount ?? 0) ?> facturi emise.</div>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-medium text-slate-700">Incasari luna curenta</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-700">
                    <?= number_format((float) ($monthCollectedTotal ?? 0), 2, '.', ' ') ?> RON
                </div>
                <div class="text-xs text-slate-500">Total incasat de la clienti.</div>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-medium text-slate-700">Plati furnizori luna curenta</div>
                <div class="mt-2 text-2xl font-semibold text-blue-700">
                    <?= number_format((float) ($monthPaidTotal ?? 0), 2, '.', ' ') ?> RON
                </div>
                <div class="text-xs text-slate-500">Total plati efectuate.</div>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-medium text-slate-700">Facturi neincasate integral</div>
                <div class="mt-2 text-2xl font-semibold text-amber-700">
                    <?= (int) ($uncollectedCount ?? 0) ?>
                </div>
                <div class="text-xs text-slate-500">Facturi emise cu sold restant.</div>
            </div>
        </div>

        <?php if ($showCommissionDailyChart): ?>
            <?php
                $chartDays = is_array($commissionDailyChart['days'] ?? null) ? $commissionDailyChart['days'] : [];
                $chartMax = (float) ($commissionDailyChart['max'] ?? 0.0);
                $chartTotal = (float) ($commissionDailyChart['total'] ?? 0.0);
                $chartMonthLabel = (string) ($commissionDailyChart['month_label'] ?? '');
                $chartHasData = !empty($commissionDailyChart['has_data']);
                $daysCount = count($chartDays);
            ?>
            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <div class="rounded border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm font-medium text-slate-700">
                            Comision zilnic (luna <?= htmlspecialchars($chartMonthLabel !== '' ? $chartMonthLabel : date('m.Y')) ?>)
                        </div>
                        <div class="text-xs font-semibold text-blue-700">
                            Total comision: <?= number_format($chartTotal, 2, '.', ' ') ?> RON
                        </div>
                    </div>
                    <?php if (empty($chartDays) || !$chartHasData || $chartMax <= 0.0): ?>
                        <div class="mt-3 text-sm text-slate-500">Nu exista comision inregistrat pentru luna curenta.</div>
                    <?php else: ?>
                        <div class="mt-4 overflow-x-auto overflow-y-visible">
                            <div class="min-w-[560px]">
                                <div class="flex h-52 items-end gap-1 rounded border border-slate-200 bg-white px-2 pb-2 pt-8">
                                    <?php foreach ($chartDays as $point): ?>
                                        <?php
                                            $dayNo = (int) ($point['day'] ?? 0);
                                            $value = (float) ($point['value'] ?? 0.0);
                                            $height = $chartMax > 0.0 ? (int) round(($value / $chartMax) * 140) : 0;
                                            if ($value > 0.0 && $height < 8) {
                                                $height = 8;
                                            } elseif ($height <= 0) {
                                                $height = 3;
                                            }
                                            $showTick = $dayNo === 1 || $dayNo === $daysCount || $dayNo % 2 === 0;
                                        ?>
                                        <div class="group flex min-w-0 flex-1 flex-col items-center justify-end">
                                            <div class="relative flex w-full items-end justify-center">
                                                <div class="pointer-events-none absolute -top-8 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[10px] font-medium text-white shadow group-hover:block">
                                                    <?= number_format($value, 2, '.', ' ') ?> RON
                                                </div>
                                                <div
                                                    class="<?= $value > 0.0 ? 'bg-blue-500 group-hover:bg-blue-600' : 'bg-slate-200 group-hover:bg-slate-300' ?> w-full rounded-t transition-colors"
                                                    style="height: <?= $height ?>px"
                                                    aria-label="Ziua <?= $dayNo ?>: <?= number_format($value, 2, '.', ' ') ?> RON"
                                                ></div>
                                            </div>
                                            <div class="mt-1 h-3 text-[10px] leading-3 text-slate-500">
                                                <?= $showTick ? (string) $dayNo : '' ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 text-[11px] text-slate-500">
                            Barele arata comisionul zilnic calculat ca diferenta dintre total client si total furnizor pentru facturile emise.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rounded border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-sm font-medium text-slate-700">Facturi neincasate integral</div>
                        <a
                            href="<?= App\Support\Url::to('admin/incasari') ?>"
                            class="text-xs font-semibold text-blue-700 hover:text-blue-800"
                        >
                            Vezi incasari
                        </a>
                    </div>
                    <?php if (empty($uncollectedInvoices ?? [])): ?>
                        <div class="mt-3 text-sm text-slate-500">Nu exista facturi restante.</div>
                    <?php else: ?>
                        <ul class="mt-3 space-y-2 text-sm">
                            <?php foreach (array_slice($uncollectedInvoices, 0, 10) as $invoice): ?>
                                <li class="rounded border border-slate-200 bg-white px-3 py-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
                                        <span class="text-xs text-slate-500"><?= htmlspecialchars($invoice['issue_date'] ?? '') ?></span>
                                    </div>
                                    <div class="text-xs text-slate-600">
                                        <?= htmlspecialchars($invoice['supplier_name'] ?? '') ?> → <?= htmlspecialchars($invoice['client_name'] ?? '—') ?>
                                    </div>
                                    <div class="text-xs text-slate-600">
                                        Rest: <?= number_format((float) ($invoice['remaining'] ?? 0), 2, '.', ' ') ?> RON
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-6 rounded border border-slate-200 bg-slate-50 p-4">
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
        <?php else: ?>
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
                        <div class="text-sm font-medium text-slate-700">Facturi neincasate integral</div>
                        <a
                            href="<?= App\Support\Url::to('admin/incasari') ?>"
                            class="text-xs font-semibold text-blue-700 hover:text-blue-800"
                        >
                            Vezi incasari
                        </a>
                    </div>
                    <?php if (empty($uncollectedInvoices ?? [])): ?>
                        <div class="mt-3 text-sm text-slate-500">Nu exista facturi restante.</div>
                    <?php else: ?>
                        <ul class="mt-3 space-y-2 text-sm">
                            <?php foreach (array_slice($uncollectedInvoices, 0, 10) as $invoice): ?>
                                <li class="rounded border border-slate-200 bg-white px-3 py-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
                                        <span class="text-xs text-slate-500"><?= htmlspecialchars($invoice['issue_date'] ?? '') ?></span>
                                    </div>
                                    <div class="text-xs text-slate-600">
                                        <?= htmlspecialchars($invoice['supplier_name'] ?? '') ?> → <?= htmlspecialchars($invoice['client_name'] ?? '—') ?>
                                    </div>
                                    <div class="text-xs text-slate-600">
                                        Rest: <?= number_format((float) ($invoice['remaining'] ?? 0), 2, '.', ' ') ?> RON
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canAccessSaga): ?>
            <div class="mt-6 rounded border border-slate-200 bg-slate-50 p-4">
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
                            <?php
                                $labelText = trim((string) ($package['label'] ?? ''));
                                if ($labelText === '') {
                                    $labelText = 'Pachet de produse';
                                }
                                $label = $labelText . ' #' . $package['package_no'];
                            ?>
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
        <?php endif; ?>
    <?php endif; ?>
</div>
