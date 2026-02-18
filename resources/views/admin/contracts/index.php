<?php
    use App\Support\Auth;

    $title = 'Contracte';
    $templates = $templates ?? [];
    $contracts = $contracts ?? [];
    $companyNamesByCui = is_array($companyNamesByCui ?? null) ? $companyNamesByCui : [];
    $pdfAvailable = !empty($pdfAvailable);
    $canApproveContracts = Auth::isInternalStaff();
    $statusLabels = [
        'draft' => 'Ciorna',
        'generated' => 'Generat',
        'sent' => 'Trimis',
        'signed_uploaded' => 'Semnat (incarcat)',
        'approved' => 'Aprobat',
    ];
    $statusClasses = [
        'draft' => 'bg-slate-100 text-slate-700',
        'generated' => 'bg-blue-100 text-blue-700',
        'sent' => 'bg-amber-100 text-amber-700',
        'signed_uploaded' => 'bg-purple-100 text-purple-700',
        'approved' => 'bg-emerald-100 text-emerald-700',
    ];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Contracte</h1>
        <p class="mt-1 text-sm text-slate-500">Contracte generate si gestionate.</p>
    </div>
</div>

<?php if (!$pdfAvailable): ?>
    <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Generarea PDF nu este disponibila (wkhtmltopdf lipsa). Contractele pot fi salvate, dar download-ul PDF va fi indisponibil
        pana la configurarea utilitarului pe server.
    </div>
<?php endif; ?>

<form method="POST" action="<?= App\Support\Url::to('admin/contracts/generate') ?>" class="mt-4 rounded-xl border border-blue-100 bg-blue-50 p-6 shadow-sm ring-1 ring-blue-100">
    <?= App\Support\Csrf::input() ?>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template_id">Model</label>
            <select id="template_id" name="template_id" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">(fara model)</option>
                <?php foreach ($templates as $template): ?>
                    <option value="<?= (int) $template['id'] ?>">
                        <?= htmlspecialchars((string) ($template['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="contract-title">Titlu</label>
            <input
                id="contract-title"
                name="title"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="partner-cui">Partner CUI (optional)</label>
            <input
                id="partner-cui"
                name="partner_cui"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="supplier-cui">Supplier CUI (optional)</label>
            <input
                id="supplier-cui"
                name="supplier_cui"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="client-cui">Client CUI (optional)</label>
            <input
                id="client-cui"
                name="client_cui"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="contract-date">Data contract</label>
            <input
                id="contract-date"
                name="contract_date"
                type="date"
                value="<?= htmlspecialchars(date('Y-m-d')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>

    <div class="mt-4">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Genereaza contract
        </button>
    </div>
</form>

<form method="POST" action="<?= App\Support\Url::to('admin/contracts/upload-signed') ?>" enctype="multipart/form-data" class="mt-6 rounded-xl border border-blue-100 bg-blue-50 p-6 shadow-sm ring-1 ring-blue-100">
    <?= App\Support\Csrf::input() ?>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="signed-upload-company">Firma</label>
            <select
                id="signed-upload-company"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                data-source-url="<?= App\Support\Url::to('admin/contracts/upload-signed/companies') ?>"
                required
            >
                <option value="">Se incarca firmele...</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700" for="signed-upload-contract">Document</label>
            <select
                id="signed-upload-contract"
                name="contract_id"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                data-source-url="<?= App\Support\Url::to('admin/contracts/upload-signed/contracts') ?>"
                disabled
                required
            >
                <option value="">Selectati mai intai firma</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="signed-upload-file">Fisier semnat</label>
            <input id="signed-upload-file" type="file" name="file" required class="mt-1 block w-full text-sm text-slate-600">
        </div>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-3">
        <button id="signed-upload-submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60" disabled>
            Incarca contract semnat
        </button>
        <span id="signed-upload-status" class="text-xs text-slate-500">Alege firma pentru a incarca documentul semnat.</span>
    </div>
</form>

<script>
    (function () {
        const companySelect = document.getElementById('signed-upload-company');
        const contractSelect = document.getElementById('signed-upload-contract');
        const submitButton = document.getElementById('signed-upload-submit');
        const statusNode = document.getElementById('signed-upload-status');
        if (!companySelect || !contractSelect || !submitButton || !statusNode) {
            return;
        }

        const companiesUrl = companySelect.dataset.sourceUrl || '';
        const contractsUrl = contractSelect.dataset.sourceUrl || '';
        if (!companiesUrl || !contractsUrl) {
            return;
        }

        let companiesRequestId = 0;
        let contractsRequestId = 0;

        const setStatus = (message, tone) => {
            statusNode.textContent = message;
            statusNode.classList.remove('text-slate-500', 'text-rose-600', 'text-emerald-700');
            if (tone === 'error') {
                statusNode.classList.add('text-rose-600');
                return;
            }
            if (tone === 'success') {
                statusNode.classList.add('text-emerald-700');
                return;
            }
            statusNode.classList.add('text-slate-500');
        };

        const setSubmitEnabled = () => {
            submitButton.disabled = contractSelect.value === '';
        };

        const resetContractSelect = (placeholder) => {
            contractSelect.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = placeholder;
            contractSelect.appendChild(option);
            contractSelect.disabled = true;
            contractSelect.value = '';
            setSubmitEnabled();
        };

        const loadCompanies = () => {
            const currentRequestId = ++companiesRequestId;
            setStatus('Se incarca firmele cu documente...', 'info');
            fetch(companiesUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (currentRequestId !== companiesRequestId) {
                        return;
                    }
                    companySelect.innerHTML = '';
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Selecteaza firma';
                    companySelect.appendChild(defaultOption);

                    const items = payload && payload.success === true && Array.isArray(payload.items)
                        ? payload.items
                        : [];
                    if (items.length === 0) {
                        setStatus('Nu exista firme cu documente disponibile.', 'error');
                        resetContractSelect('Nu exista documente disponibile');
                        return;
                    }

                    items.forEach((item) => {
                        const option = document.createElement('option');
                        const cui = String(item && item.cui ? item.cui : '').trim();
                        if (cui === '') {
                            return;
                        }
                        const count = Number(item && item.contracts_count ? item.contracts_count : 0);
                        option.value = cui;
                        option.textContent = `${String(item && item.name ? item.name : cui)} (${cui})${count > 0 ? ` - ${count} doc.` : ''}`;
                        companySelect.appendChild(option);
                    });
                    setStatus('Selecteaza firma pentru a vedea documentele disponibile.', 'info');
                })
                .catch(() => {
                    if (currentRequestId !== companiesRequestId) {
                        return;
                    }
                    setStatus('Nu am putut incarca lista firmelor.', 'error');
                    resetContractSelect('Eroare la incarcarea documentelor');
                });
        };

        const loadContractsForCompany = (companyCui) => {
            const currentRequestId = ++contractsRequestId;
            const url = new URL(contractsUrl, window.location.origin);
            url.searchParams.set('company_cui', companyCui);
            setStatus('Se incarca documentele firmei selectate...', 'info');
            resetContractSelect('Se incarca documentele...');

            fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (currentRequestId !== contractsRequestId) {
                        return;
                    }
                    contractSelect.innerHTML = '';
                    const first = document.createElement('option');
                    first.value = '';
                    first.textContent = 'Selecteaza document';
                    contractSelect.appendChild(first);

                    const items = payload && payload.success === true && Array.isArray(payload.items)
                        ? payload.items
                        : [];
                    if (items.length === 0) {
                        resetContractSelect('Nu exista documente pentru firma selectata');
                        setStatus('Firma selectata nu are documente disponibile pentru incarcare.', 'error');
                        return;
                    }

                    items.forEach((item) => {
                        const id = Number(item && item.id ? item.id : 0);
                        if (!Number.isFinite(id) || id <= 0) {
                            return;
                        }
                        const option = document.createElement('option');
                        option.value = String(id);
                        option.textContent = String(item && item.label ? item.label : `Document #${id}`);
                        contractSelect.appendChild(option);
                    });

                    contractSelect.disabled = false;
                    setSubmitEnabled();
                    setStatus('Selecteaza documentul pentru care incarci fisierul semnat.', 'success');
                })
                .catch(() => {
                    if (currentRequestId !== contractsRequestId) {
                        return;
                    }
                    resetContractSelect('Eroare la incarcarea documentelor');
                    setStatus('Nu am putut incarca documentele pentru firma selectata.', 'error');
                });
        };

        companySelect.addEventListener('change', () => {
            const companyCui = String(companySelect.value || '').trim();
            if (companyCui === '') {
                resetContractSelect('Selectati mai intai firma');
                setStatus('Alege firma pentru a incarca documentul semnat.', 'info');
                return;
            }
            loadContractsForCompany(companyCui);
        });

        contractSelect.addEventListener('change', () => {
            setSubmitEnabled();
        });

        resetContractSelect('Selectati mai intai firma');
        loadCompanies();
    })();
</script>

<div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-3 py-2">Nr. registru</th>
                <th class="px-3 py-2">Relatie</th>
                <th class="px-3 py-2">Titlu</th>
                <th class="px-3 py-2">Data contract</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Descarcare</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contracts)): ?>
                <tr>
                    <td colspan="7" class="px-3 py-4 text-sm text-slate-500">
                        Nu exista contracte inca. Dupa confirmarea inrolarii, contractele vor aparea automat aici.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($contracts as $contract): ?>
                    <?php
                        $downloadUrl = App\Support\Url::to('admin/contracts/download?id=' . (int) $contract['id']);
                        $relationSupplierCui = preg_replace('/\D+/', '', (string) ($contract['supplier_cui'] ?? ''));
                        $relationClientCui = preg_replace('/\D+/', '', (string) ($contract['client_cui'] ?? ''));
                        $relationPartnerCui = preg_replace('/\D+/', '', (string) ($contract['partner_cui'] ?? ''));
                        $relationSupplierName = $relationSupplierCui !== ''
                            ? (string) ($companyNamesByCui[$relationSupplierCui] ?? $relationSupplierCui)
                            : '';
                        $relationClientName = $relationClientCui !== ''
                            ? (string) ($companyNamesByCui[$relationClientCui] ?? $relationClientCui)
                            : '';
                        $relationPartnerName = $relationPartnerCui !== ''
                            ? (string) ($companyNamesByCui[$relationPartnerCui] ?? $relationPartnerCui)
                            : '';
                        $statusKey = (string) ($contract['status'] ?? '');
                        $statusLabel = $statusLabels[$statusKey] ?? $statusKey;
                        $statusClass = $statusClasses[$statusKey] ?? 'bg-slate-100 text-slate-700';
                        $docNoDisplay = trim((string) ($contract['doc_full_no'] ?? ''));
                        if ($docNoDisplay === '') {
                            $docNo = (int) ($contract['doc_no'] ?? 0);
                            if ($docNo > 0) {
                                $series = trim((string) ($contract['doc_series'] ?? ''));
                                $docNoPadded = str_pad((string) $docNo, 6, '0', STR_PAD_LEFT);
                                $docNoDisplay = $series !== '' ? ($series . '-' . $docNoPadded) : $docNoPadded;
                            }
                        }
                        $contractDateRaw = trim((string) ($contract['contract_date'] ?? ''));
                        $contractDateDisplay = '—';
                        if ($contractDateRaw !== '') {
                            $contractTimestamp = strtotime($contractDateRaw);
                            $contractDateDisplay = $contractTimestamp !== false
                                ? date('d.m.Y', $contractTimestamp)
                                : $contractDateRaw;
                        }
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-600">
                            <?php if ($docNoDisplay !== ''): ?>
                                <span class="font-mono"><?= htmlspecialchars($docNoDisplay) ?></span>
                            <?php else: ?>
                                <span class="text-amber-700">Fara numar</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <?php if ($relationSupplierName === '' && $relationClientName === '' && $relationPartnerName === ''): ?>
                                —
                            <?php else: ?>
                                <?php if ($relationSupplierName !== ''): ?>
                                    <div><span class="text-xs text-slate-500">Furnizor:</span> <?= htmlspecialchars($relationSupplierName) ?></div>
                                <?php endif; ?>
                                <?php if ($relationClientName !== ''): ?>
                                    <div><span class="text-xs text-slate-500">Client:</span> <?= htmlspecialchars($relationClientName) ?></div>
                                <?php endif; ?>
                                <?php if ($relationSupplierName === '' && $relationClientName === '' && $relationPartnerName !== ''): ?>
                                    <div><span class="text-xs text-slate-500">Companie:</span> <?= htmlspecialchars($relationPartnerName) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contract['title'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($contractDateDisplay) ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $statusClass ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <a href="<?= htmlspecialchars($downloadUrl) ?>" class="text-xs font-semibold text-blue-700 hover:text-blue-800">Descarca</a>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <?php if (($contract['status'] ?? '') !== 'approved'): ?>
                                <?php if ($canApproveContracts): ?>
                                    <form method="POST" action="<?= App\Support\Url::to('admin/contracts/approve') ?>">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
                                        <button
                                            class="text-xs font-semibold text-emerald-600 hover:text-emerald-700"
                                            onclick="return confirm('Sigur doriti sa aprobati contractul? Aceasta actiune confirma validitatea documentului.')"
                                        >
                                            Aproba contractul
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Aprobat</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
