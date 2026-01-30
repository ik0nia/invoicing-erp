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

<form method="GET" action="<?= App\Support\Url::to('admin/incasari/adauga') ?>" class="mt-4">
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

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="paid_at">Data incasarii</label>
            <input
                id="paid_at"
                name="paid_at"
                type="date"
                value="<?= htmlspecialchars(date('Y-m-d')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="amount">Suma incasata</label>
            <input
                id="amount"
                name="amount"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="method">Metoda</label>
            <input
                id="method"
                name="method"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="reference">Referinta</label>
            <input
                id="reference"
                name="reference"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700" for="notes">Observatii</label>
            <input
                id="notes"
                name="notes"
                type="text"
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
                                        class="w-28 rounded border border-slate-300 px-2 py-1 text-sm"
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
