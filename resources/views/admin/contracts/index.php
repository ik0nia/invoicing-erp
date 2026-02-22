<?php
    use App\Support\Auth;

    $title = 'Contracte';
    $templates = $templates ?? [];
    $contracts = $contracts ?? [];
    $companyNamesByCui = is_array($companyNamesByCui ?? null) ? $companyNamesByCui : [];
    $pdfAvailable = !empty($pdfAvailable);
    $canApproveContracts = Auth::isInternalStaff();
    $user = Auth::user();
    $canResetGeneratedContracts = $user !== null && ($user->isPlatformUser() || $user->hasRole('operator'));
    $supplementaryAnnexDeletionState = is_array($supplementaryAnnexDeletionState ?? null) ? $supplementaryAnnexDeletionState : [];
    $latestSupplementaryAnnexContractId = (int) ($supplementaryAnnexDeletionState['latest_contract_id'] ?? 0);
    $canDeleteLastSupplementaryAnnex = $user !== null
        && $user->hasRole(['super_admin', 'admin', 'operator', 'contabil'])
        && !empty($supplementaryAnnexDeletionState['can_delete']);
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

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Contracte</h1>
        <p class="mt-1 text-sm text-slate-500">Contracte generate si gestionate.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <button
            id="contracts-open-generate"
            type="button"
            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
            aria-expanded="false"
            aria-controls="contracts-generate-card"
        >
            Genereaza document
        </button>
        <button
            id="contracts-open-upload"
            type="button"
            class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700"
            aria-expanded="false"
            aria-controls="contracts-upload-card"
        >
            Incarca document semnat
        </button>
    </div>
</div>

<?php if (!$pdfAvailable): ?>
    <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Generarea PDF nu este disponibila (wkhtmltopdf lipsa). Contractele pot fi salvate, dar download-ul PDF va fi indisponibil
        pana la configurarea utilitarului pe server.
    </div>
<?php endif; ?>

<div id="contracts-generate-card" class="mt-4 hidden">
    <form method="POST" action="<?= App\Support\Url::to('admin/contracts/generate') ?>" class="rounded-xl border border-blue-100 bg-blue-50 p-6 shadow-sm ring-1 ring-blue-100">
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
                <div
                    class="relative"
                    data-contract-company-picker
                    data-required="1"
                    data-search-url="<?= App\Support\Url::to('admin/contracts/company-search') ?>"
                >
                    <label class="block text-sm font-medium text-slate-700" for="supplier-cui-display">Furnizor</label>
                    <input
                        id="supplier-cui-display"
                        type="text"
                        autocomplete="off"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        placeholder="Cauta dupa denumire sau CUI"
                        data-company-display
                        required
                    >
                    <input type="hidden" id="supplier-cui" name="supplier_cui" data-company-value>
                    <div class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-slate-300 bg-white p-1 shadow-xl ring-1 ring-slate-200 divide-y divide-slate-100" data-company-list></div>
                </div>
            </div>
            <div>
                <div
                    class="relative"
                    data-contract-company-picker
                    data-required="0"
                    data-search-url="<?= App\Support\Url::to('admin/contracts/company-search') ?>"
                >
                    <label class="block text-sm font-medium text-slate-700" for="client-cui-display">Client (optional)</label>
                    <input
                        id="client-cui-display"
                        type="text"
                        autocomplete="off"
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                        placeholder="Cauta dupa denumire sau CUI"
                        data-company-display
                    >
                    <input type="hidden" id="client-cui" name="client_cui" data-company-value>
                    <div class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-slate-300 bg-white p-1 shadow-xl ring-1 ring-slate-200 divide-y divide-slate-100" data-company-list></div>
                </div>
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
        <p class="mt-3 text-xs text-slate-600">
            Daca completezi doar furnizorul, documentul se genereaza pentru furnizor. Daca completezi si clientul, documentul se genereaza pe relatia furnizor-client.
        </p>

        <div class="mt-4">
            <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
                Genereaza contract
            </button>
        </div>
    </form>
</div>

<div id="contracts-upload-card" class="mt-4 hidden">
    <form method="POST" action="<?= App\Support\Url::to('admin/contracts/upload-signed') ?>" enctype="multipart/form-data" class="rounded-xl border border-emerald-100 bg-emerald-50 p-6 shadow-sm ring-1 ring-emerald-100">
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
            <button id="signed-upload-submit" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60" disabled>
                Incarca contract semnat
            </button>
            <span id="signed-upload-status" class="text-xs text-slate-500">Alege firma pentru a incarca documentul semnat.</span>
        </div>
    </form>
</div>

<script>
    (function () {
        const toggleConfigs = [
            { buttonId: 'contracts-open-generate', cardId: 'contracts-generate-card' },
            { buttonId: 'contracts-open-upload', cardId: 'contracts-upload-card' },
        ];

        toggleConfigs.forEach((config) => {
            const button = document.getElementById(config.buttonId);
            const card = document.getElementById(config.cardId);
            if (!button || !card) {
                return;
            }

            const syncExpanded = () => {
                button.setAttribute('aria-expanded', card.classList.contains('hidden') ? 'false' : 'true');
            };

            button.addEventListener('click', () => {
                const willOpen = card.classList.contains('hidden');
                if (willOpen) {
                    card.classList.remove('hidden');
                    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    card.classList.add('hidden');
                }
                syncExpanded();
            });

            syncExpanded();
        });
    })();
</script>

<script>
    (function () {
        const escapeHtml = (value) => {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };
        const normalizeDigits = (value) => String(value || '').replace(/\D+/g, '');
        const extractCompanyCuiCandidate = (value) => {
            const raw = String(value || '').trim();
            if (raw === '') {
                return '';
            }
            if (/^\d+$/.test(raw)) {
                return raw;
            }
            const compact = raw.replace(/\s+/g, '').toUpperCase();
            if (/^RO\d+$/.test(compact)) {
                return compact.replace(/^RO/, '');
            }
            const suffix = raw.match(/(?:^|[-\s])(RO)?(\d{2,16})$/i);
            if (suffix && suffix[2]) {
                return normalizeDigits(suffix[2]);
            }

            return '';
        };

        const pickers = Array.from(document.querySelectorAll('[data-contract-company-picker]'));
        if (pickers.length === 0) {
            return;
        }

        pickers.forEach((picker) => {
            const displayInput = picker.querySelector('[data-company-display]');
            const valueInput = picker.querySelector('[data-company-value]');
            const list = picker.querySelector('[data-company-list]');
            const searchUrl = picker.getAttribute('data-search-url') || '';
            const required = picker.getAttribute('data-required') === '1';
            if (!displayInput || !valueInput || !list || !searchUrl) {
                return;
            }

            let requestId = 0;
            let timer = null;
            let selectedLabel = '';

            const clearList = () => {
                list.innerHTML = '';
                list.classList.add('hidden');
            };
            const syncValidity = () => {
                if (!required) {
                    displayInput.setCustomValidity('');
                    return;
                }
                const hasValue = valueInput.value.trim() !== '' || extractCompanyCuiCandidate(displayInput.value) !== '';
                displayInput.setCustomValidity(hasValue ? '' : 'Selecteaza furnizorul.');
            };
            const applySelection = (item) => {
                const cui = normalizeDigits(item.cui || '');
                const name = String(item.name || '').trim();
                const label = name !== '' && name !== cui ? `${name} - ${cui}` : cui;
                valueInput.value = cui;
                selectedLabel = label;
                displayInput.value = label;
                clearList();
                syncValidity();
            };
            const renderItems = (items) => {
                if (!Array.isArray(items) || items.length === 0) {
                    list.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">Nu exista rezultate.</div>';
                    list.classList.remove('hidden');
                    return;
                }
                list.innerHTML = items
                    .map((item) => {
                        const cui = escapeHtml(item.cui || '');
                        const name = escapeHtml(item.name || item.cui || '');
                        const label = escapeHtml(item.label || `${item.name || item.cui || ''} - ${item.cui || ''}`);
                        return `
                            <button
                                type="button"
                                class="block w-full rounded px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700"
                                data-company-item
                                data-cui="${cui}"
                                data-name="${name}"
                                data-label="${label}"
                            >
                                <div class="font-medium text-slate-900">${label}</div>
                            </button>
                        `;
                    })
                    .join('');
                list.classList.remove('hidden');
            };
            const fetchItems = (term) => {
                const currentRequestId = ++requestId;
                const url = new URL(searchUrl, window.location.origin);
                url.searchParams.set('term', term);
                url.searchParams.set('limit', '20');
                fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (currentRequestId !== requestId) {
                            return;
                        }
                        if (!data || data.success !== true) {
                            renderItems([]);
                            return;
                        }
                        renderItems(data.items || []);
                    })
                    .catch(() => {
                        if (currentRequestId !== requestId) {
                            return;
                        }
                        renderItems([]);
                    });
            };

            const parentForm = displayInput.closest('form');
            if (parentForm) {
                parentForm.addEventListener('submit', (event) => {
                    if (valueInput.value.trim() === '') {
                        const maybeCui = extractCompanyCuiCandidate(displayInput.value);
                        if (maybeCui !== '') {
                            valueInput.value = maybeCui;
                            if (selectedLabel === '') {
                                displayInput.value = maybeCui;
                            }
                        }
                    }
                    syncValidity();
                    if (required && valueInput.value.trim() === '') {
                        event.preventDefault();
                        displayInput.reportValidity();
                    }
                });
            }

            displayInput.addEventListener('focus', () => {
                fetchItems(displayInput.value.trim());
            });
            displayInput.addEventListener('input', () => {
                valueInput.value = '';
                selectedLabel = '';
                syncValidity();
                const query = displayInput.value.trim();
                if (timer) {
                    clearTimeout(timer);
                }
                timer = setTimeout(() => {
                    fetchItems(query);
                }, 200);
            });
            displayInput.addEventListener('change', () => {
                if (valueInput.value.trim() !== '') {
                    syncValidity();
                    return;
                }
                const maybeCui = extractCompanyCuiCandidate(displayInput.value);
                if (maybeCui !== '') {
                    valueInput.value = maybeCui;
                    displayInput.value = maybeCui;
                }
                syncValidity();
            });
            displayInput.addEventListener('blur', () => {
                window.setTimeout(() => {
                    clearList();
                    if (valueInput.value.trim() !== '') {
                        syncValidity();
                        return;
                    }
                    const maybeCui = extractCompanyCuiCandidate(displayInput.value);
                    if (maybeCui !== '') {
                        valueInput.value = maybeCui;
                        displayInput.value = maybeCui;
                    } else if (!required && displayInput.value.trim() === '') {
                        valueInput.value = '';
                    }
                    syncValidity();
                }, 150);
            });

            const handleSelect = (event) => {
                if (event.type === 'mousedown' && typeof window.PointerEvent !== 'undefined') {
                    return;
                }
                const target = event.target.closest('[data-company-item]');
                if (!target) {
                    return;
                }
                event.preventDefault();
                applySelection({
                    cui: target.getAttribute('data-cui') || '',
                    name: target.getAttribute('data-name') || '',
                    label: target.getAttribute('data-label') || '',
                });
            };
            list.addEventListener('pointerdown', handleSelect);
            list.addEventListener('mousedown', handleSelect);

            syncValidity();
        });
    })();
</script>

<script>
    (function () {
        const companySelect = document.getElementById('signed-upload-company');
        const contractSelect = document.getElementById('signed-upload-contract');
        const submitButton = document.getElementById('signed-upload-submit');
        const statusNode = document.getElementById('signed-upload-status');
        const uploadCard = document.getElementById('contracts-upload-card');
        const uploadCardToggleButton = document.getElementById('contracts-open-upload');
        const uploadFileInput = document.getElementById('signed-upload-file');
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
        let companiesLoaded = false;
        let pendingUploadTarget = null;

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

        const openUploadCard = () => {
            if (uploadCard && uploadCard.classList.contains('hidden')) {
                uploadCard.classList.remove('hidden');
            }
            if (uploadCardToggleButton) {
                uploadCardToggleButton.setAttribute('aria-expanded', 'true');
            }
            if (uploadCard) {
                uploadCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
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
            companiesLoaded = false;
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

                    companiesLoaded = true;
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
                    if (pendingUploadTarget) {
                        const target = pendingUploadTarget;
                        pendingUploadTarget = null;
                        openUploadForContract(target.companyCui, target.contractId);
                    }
                })
                .catch(() => {
                    if (currentRequestId !== companiesRequestId) {
                        return;
                    }
                    companiesLoaded = false;
                    setStatus('Nu am putut incarca lista firmelor.', 'error');
                    resetContractSelect('Eroare la incarcarea documentelor');
                });
        };

        const loadContractsForCompany = (companyCui, preferredContractId = '') => {
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

                    if (preferredContractId !== '') {
                        const hasPreferred = Array.from(contractSelect.options).some((option) => option.value === preferredContractId);
                        if (hasPreferred) {
                            contractSelect.value = preferredContractId;
                        }
                    }
                    contractSelect.disabled = false;
                    setSubmitEnabled();
                    if (contractSelect.value !== '') {
                        setStatus('Document selectat. Incarca fisierul semnat.', 'success');
                        if (uploadFileInput) {
                            uploadFileInput.focus();
                        }
                    } else {
                        setStatus('Selecteaza documentul pentru care incarci fisierul semnat.', 'success');
                    }
                })
                .catch(() => {
                    if (currentRequestId !== contractsRequestId) {
                        return;
                    }
                    resetContractSelect('Eroare la incarcarea documentelor');
                    setStatus('Nu am putut incarca documentele pentru firma selectata.', 'error');
                });
        };

        const openUploadForContract = (companyCui, contractId) => {
            const normalizedCompanyCui = String(companyCui || '').trim();
            const normalizedContractId = String(contractId || '').trim();
            openUploadCard();
            if (normalizedCompanyCui === '') {
                setStatus('Contractul selectat nu are firma asociata pentru filtrare.', 'error');
                return;
            }
            if (!companiesLoaded) {
                pendingUploadTarget = {
                    companyCui: normalizedCompanyCui,
                    contractId: normalizedContractId,
                };
                setStatus('Se pregateste selectia documentului...', 'info');
                return;
            }

            const hasCompany = Array.from(companySelect.options).some((option) => option.value === normalizedCompanyCui);
            if (!hasCompany) {
                setStatus('Firma documentului nu este disponibila in lista curenta.', 'error');
                return;
            }
            companySelect.value = normalizedCompanyCui;
            loadContractsForCompany(normalizedCompanyCui, normalizedContractId);
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

        document.addEventListener('click', (event) => {
            const eventTarget = event.target;
            const trigger = eventTarget && typeof eventTarget.closest === 'function'
                ? eventTarget.closest('[data-contract-upload-trigger="1"]')
                : null;
            if (!trigger) {
                return;
            }
            event.preventDefault();
            openUploadForContract(
                trigger.getAttribute('data-upload-company-cui') || '',
                trigger.getAttribute('data-upload-contract-id') || ''
            );
        });

        resetContractSelect('Selectati mai intai firma');
        loadCompanies();
    })();
</script>

<div class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <div class="xl:col-span-2">
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="contracts-filter-search">Cautare</label>
            <input
                id="contracts-filter-search"
                type="text"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                placeholder="Nr. registru, titlu, relatie"
            >
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="contracts-filter-status">Status</label>
            <select id="contracts-filter-status" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Toate statusurile</option>
                <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
                    <option value="<?= htmlspecialchars((string) $statusKey) ?>"><?= htmlspecialchars((string) $statusLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="contracts-filter-company">Firma</label>
            <select id="contracts-filter-company" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Toate firmele</option>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-2 xl:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="contracts-filter-date-from">Data de la</label>
                <input id="contracts-filter-date-from" type="date" class="mt-1 block w-full rounded border border-slate-300 px-2 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="contracts-filter-date-to">Data pana la</label>
                <input id="contracts-filter-date-to" type="date" class="mt-1 block w-full rounded border border-slate-300 px-2 py-2 text-sm">
            </div>
        </div>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tip relatie</span>
        <button
            type="button"
            class="rounded-full border border-blue-600 bg-blue-600 px-3 py-1 text-xs font-semibold text-white transition-colors"
            data-contracts-party-filter="all"
        >
            Toate
        </button>
        <button
            type="button"
            class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50"
            data-contracts-party-filter="client"
        >
            Clienti
        </button>
        <button
            type="button"
            class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50"
            data-contracts-party-filter="supplier"
        >
            Furnizori
        </button>
        <button
            id="contracts-party-filter-clear"
            type="button"
            class="hidden rounded border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
        >
            Dezactiveaza filtrul
        </button>
    </div>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
        <button id="contracts-filter-reset" type="button" class="rounded border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
            Reseteaza filtrele
        </button>
        <span id="contracts-filter-summary" class="text-xs text-slate-500"></span>
    </div>
</div>

<div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-3 py-2">Nr. registru</th>
                <th class="px-3 py-2">Data contract</th>
                <th class="px-3 py-2">Relatie</th>
                <th class="px-3 py-2">Titlu</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Creat</th>
                <th class="px-3 py-2">Incarcare document</th>
                <th class="px-3 py-2 text-right">Nesemnat</th>
                <th class="px-3 py-2">Descarcare</th>
            </tr>
        </thead>
        <tbody id="contracts-table-body">
            <?php if (empty($contracts)): ?>
                <tr>
                    <td colspan="9" class="px-3 py-4 text-sm text-slate-500">
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
                        $uploadTargetCompanyCui = $relationSupplierCui !== ''
                            ? $relationSupplierCui
                            : ($relationClientCui !== '' ? $relationClientCui : $relationPartnerCui);
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
                        $createdAtRaw = trim((string) ($contract['created_at'] ?? ''));
                        $createdAtDisplay = '—';
                        if ($createdAtRaw !== '') {
                            $createdAtTimestamp = strtotime($createdAtRaw);
                            $createdAtDisplay = $createdAtTimestamp !== false
                                ? date('d.m.Y H:i', $createdAtTimestamp)
                                : $createdAtRaw;
                        }
                        $relationLines = [];
                        $relationCompanies = [];
                        if ($relationSupplierName !== '') {
                            $relationLines[] = ['label' => 'Furnizor', 'name' => $relationSupplierName];
                            $relationCompanies[] = $relationSupplierName;
                        }
                        if ($relationClientName !== '') {
                            $relationLines[] = ['label' => 'Client', 'name' => $relationClientName];
                            $relationCompanies[] = $relationClientName;
                        }
                        if (empty($relationLines) && $relationPartnerName !== '') {
                            $relationLines[] = ['label' => 'Companie', 'name' => $relationPartnerName];
                            $relationCompanies[] = $relationPartnerName;
                        }
                        $relationPartyFilter = 'all';
                        if ($relationClientCui !== '') {
                            $relationPartyFilter = 'client';
                        } elseif ($relationSupplierCui !== '') {
                            $relationPartyFilter = 'supplier';
                        } elseif ($relationPartnerCui !== '') {
                            // Fallback safe: partner-only rows are treated as client-side, so supplier filter stays strict.
                            $relationPartyFilter = 'client';
                        }
                        $relationCompanies = array_values(array_unique(array_filter($relationCompanies, static fn ($value): bool => trim((string) $value) !== '')));
                        $metadataJson = (string) ($contract['metadata_json'] ?? '');
                        $isSupplierSupplementaryAnnex = $metadataJson !== '' && stripos($metadataJson, 'supplier_annex_generator') !== false;
                        $showDeleteSupplementaryButton = $canDeleteLastSupplementaryAnnex
                            && $isSupplierSupplementaryAnnex
                            && (int) ($contract['id'] ?? 0) === $latestSupplementaryAnnexContractId;
                        $rowSearchText = trim(implode(' ', [
                            (string) $docNoDisplay,
                            (string) ($contract['title'] ?? ''),
                            implode(' ', $relationCompanies),
                            (string) $statusLabel,
                            (string) $contractDateDisplay,
                        ]));
                    ?>
                    <tr
                        class="border-t border-slate-100"
                        data-contract-row="1"
                        data-filter-text="<?= htmlspecialchars($rowSearchText) ?>"
                        data-filter-status="<?= htmlspecialchars($statusKey) ?>"
                        data-filter-companies="<?= htmlspecialchars(implode('|', $relationCompanies)) ?>"
                        data-filter-date="<?= htmlspecialchars($contractDateRaw) ?>"
                        data-filter-party="<?= htmlspecialchars($relationPartyFilter) ?>"
                    >
                        <td class="px-3 py-2 text-slate-600">
                            <?php if ($docNoDisplay !== ''): ?>
                                <span class="font-mono"><?= htmlspecialchars($docNoDisplay) ?></span>
                            <?php else: ?>
                                <span class="text-amber-700">Fara numar</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($contractDateDisplay) ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <?php if (empty($relationLines)): ?>
                                —
                            <?php else: ?>
                                <?php foreach ($relationLines as $relationLine): ?>
                                    <div>
                                        <span class="text-xs text-slate-500"><?= htmlspecialchars((string) ($relationLine['label'] ?? '')) ?>:</span>
                                        <?= htmlspecialchars((string) ($relationLine['name'] ?? '')) ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contract['title'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $statusClass ?>">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                                <?php if ($statusKey === 'signed_uploaded' && $canApproveContracts): ?>
                                    <form method="POST" action="<?= App\Support\Url::to('admin/contracts/approve') ?>">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
                                        <button
                                            type="submit"
                                            class="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 hover:text-emerald-800"
                                            onclick="return confirm('Sigur doriti sa aprobati contractul? Aceasta actiune confirma validitatea documentului.')"
                                        >
                                            Aproba
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($createdAtDisplay) ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <?php if ($uploadTargetCompanyCui !== ''): ?>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-800"
                                    data-contract-upload-trigger="1"
                                    data-upload-company-cui="<?= htmlspecialchars($uploadTargetCompanyCui) ?>"
                                    data-upload-contract-id="<?= (int) $contract['id'] ?>"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 20V8" />
                                        <path d="m7 13 5-5 5 5" />
                                        <path d="M5 20h14" />
                                    </svg>
                                    Incarca semnat
                                </button>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="inline-flex items-center justify-end gap-2">
                                <a href="<?= App\Support\Url::to('admin/contracts/download?id=' . (int) ($contract['id'] ?? 0) . '&kind=generated') ?>" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:text-blue-800">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 4v12" />
                                        <path d="m7 11 5 5 5-5" />
                                        <path d="M5 20h14" />
                                    </svg>
                                    Ctr. nesemnat
                                </a>
                                <?php if ($canResetGeneratedContracts): ?>
                                    <form method="POST" action="<?= App\Support\Url::to('admin/contracts/reset-generated-pdf') ?>" class="inline-flex">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="contract_id" value="<?= (int) ($contract['id'] ?? 0) ?>">
                                        <button
                                            type="submit"
                                            title="Reseteaza PDF-ul generat"
                                            aria-label="Reseteaza PDF-ul generat"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-slate-300 text-slate-500 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700"
                                            onclick="return confirm('Resetezi PDF-ul nesemnat pentru acest contract? La urmatoarea descarcare se va regenera.')"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                                                <path d="M21 3v6h-6" />
                                            </svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($showDeleteSupplementaryButton): ?>
                                    <form method="POST" action="<?= App\Support\Url::to('admin/anexe-furnizor/delete-last-document') ?>" class="inline-flex">
                                        <?= App\Support\Csrf::input() ?>
                                        <input type="hidden" name="contract_id" value="<?= (int) ($contract['id'] ?? 0) ?>">
                                        <input type="hidden" name="return_to" value="/admin/contracts">
                                        <button
                                            type="submit"
                                            title="Sterge ultima anexa suplimentara si revino contorul registrului"
                                            aria-label="Sterge ultima anexa suplimentara"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-rose-300 text-rose-600 transition hover:bg-rose-50 hover:text-rose-700"
                                            onclick="return confirm('Stergi ultima anexa suplimentara? Contorul registrului de furnizori va reveni automat.')"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M3 6h18" />
                                                <path d="M8 6V4h8v2" />
                                                <path d="M19 6l-1 14H6L5 6" />
                                                <path d="M10 11v6M14 11v6" />
                                            </svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-slate-600">
                            <a href="<?= htmlspecialchars($downloadUrl) ?>" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:text-blue-800">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 4v12" />
                                    <path d="m7 11 5 5 5-5" />
                                    <path d="M5 20h14" />
                                </svg>
                                Ctr. Semnat
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr id="contracts-empty-filtered-row" class="hidden">
                    <td colspan="9" class="px-3 py-4 text-sm text-slate-500">
                        Nu exista contracte pentru filtrele selectate.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    (function () {
        const tableBody = document.getElementById('contracts-table-body');
        const searchInput = document.getElementById('contracts-filter-search');
        const statusSelect = document.getElementById('contracts-filter-status');
        const companySelect = document.getElementById('contracts-filter-company');
        const dateFromInput = document.getElementById('contracts-filter-date-from');
        const dateToInput = document.getElementById('contracts-filter-date-to');
        const resetButton = document.getElementById('contracts-filter-reset');
        const summaryNode = document.getElementById('contracts-filter-summary');
        const emptyFilteredRow = document.getElementById('contracts-empty-filtered-row');
        const partyFilterButtons = Array.from(document.querySelectorAll('[data-contracts-party-filter]'));
        const partyFilterClearButton = document.getElementById('contracts-party-filter-clear');
        if (!tableBody || !searchInput || !statusSelect || !companySelect || !dateFromInput || !dateToInput || !resetButton || !summaryNode) {
            return;
        }

        const rows = Array.from(tableBody.querySelectorAll('tr[data-contract-row="1"]'));
        if (rows.length === 0) {
            summaryNode.textContent = '0 contracte';
            return;
        }

        const normalize = (value) => String(value || '').trim().toLowerCase();
        const companyMap = new Map();
        rows.forEach((row) => {
            const companiesRaw = String(row.dataset.filterCompanies || '');
            companiesRaw.split('|').forEach((companyNameRaw) => {
                const companyName = String(companyNameRaw || '').trim();
                if (companyName === '') {
                    return;
                }
                const key = normalize(companyName);
                if (!companyMap.has(key)) {
                    companyMap.set(key, companyName);
                }
            });
        });

        Array.from(companyMap.entries())
            .sort((left, right) => left[1].localeCompare(right[1], 'ro'))
            .forEach((entry) => {
                const option = document.createElement('option');
                option.value = entry[0];
                option.textContent = entry[1];
                companySelect.appendChild(option);
            });

        let selectedPartyFilter = 'all';
        const setPartyFilterButtonState = (button, active) => {
            button.classList.remove('border-blue-600', 'bg-blue-600', 'text-white', 'border-slate-300', 'bg-white', 'text-slate-700', 'hover:bg-slate-50');
            if (active) {
                button.classList.add('border-blue-600', 'bg-blue-600', 'text-white');
            } else {
                button.classList.add('border-slate-300', 'bg-white', 'text-slate-700', 'hover:bg-slate-50');
            }
        };
        const syncPartyFilterButtons = () => {
            partyFilterButtons.forEach((button) => {
                const value = String(button.getAttribute('data-contracts-party-filter') || '').trim();
                setPartyFilterButtonState(button, value === selectedPartyFilter);
            });
            if (partyFilterClearButton) {
                partyFilterClearButton.classList.toggle('hidden', selectedPartyFilter === 'all');
            }
        };

        const applyFilters = () => {
            const searchTerm = normalize(searchInput.value);
            const statusValue = String(statusSelect.value || '').trim();
            const companyValue = normalize(companySelect.value);
            const dateFrom = String(dateFromInput.value || '').trim();
            const dateTo = String(dateToInput.value || '').trim();
            let visibleCount = 0;

            rows.forEach((row) => {
                const rowText = normalize(row.dataset.filterText || '');
                const rowStatus = String(row.dataset.filterStatus || '').trim();
                const rowDate = String(row.dataset.filterDate || '').trim();
                const rowCompanyValues = String(row.dataset.filterCompanies || '')
                    .split('|')
                    .map((value) => normalize(value))
                    .filter((value) => value !== '');
                const rowPartyValue = normalize(row.dataset.filterParty || 'all');

                let isVisible = true;
                if (searchTerm !== '' && !rowText.includes(searchTerm)) {
                    isVisible = false;
                }
                if (isVisible && statusValue !== '' && rowStatus !== statusValue) {
                    isVisible = false;
                }
                if (isVisible && companyValue !== '' && !rowCompanyValues.includes(companyValue)) {
                    isVisible = false;
                }
                if (isVisible && dateFrom !== '' && (rowDate === '' || rowDate < dateFrom)) {
                    isVisible = false;
                }
                if (isVisible && dateTo !== '' && (rowDate === '' || rowDate > dateTo)) {
                    isVisible = false;
                }
                if (isVisible && selectedPartyFilter !== 'all' && rowPartyValue !== selectedPartyFilter) {
                    isVisible = false;
                }

                row.classList.toggle('hidden', !isVisible);
                if (isVisible) {
                    visibleCount++;
                }
            });

            if (emptyFilteredRow) {
                emptyFilteredRow.classList.toggle('hidden', visibleCount > 0);
            }
            summaryNode.textContent = visibleCount + ' / ' + rows.length + ' contracte afisate';
        };

        searchInput.addEventListener('input', applyFilters);
        statusSelect.addEventListener('change', applyFilters);
        companySelect.addEventListener('change', applyFilters);
        dateFromInput.addEventListener('change', applyFilters);
        dateToInput.addEventListener('change', applyFilters);
        partyFilterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const value = String(button.getAttribute('data-contracts-party-filter') || '').trim();
                if (value === '') {
                    return;
                }
                if (selectedPartyFilter === value && value !== 'all') {
                    selectedPartyFilter = 'all';
                } else {
                    selectedPartyFilter = value;
                }
                syncPartyFilterButtons();
                applyFilters();
            });
        });
        if (partyFilterClearButton) {
            partyFilterClearButton.addEventListener('click', () => {
                selectedPartyFilter = 'all';
                syncPartyFilterButtons();
                applyFilters();
            });
        }
        resetButton.addEventListener('click', () => {
            searchInput.value = '';
            statusSelect.value = '';
            companySelect.value = '';
            dateFromInput.value = '';
            dateToInput.value = '';
            selectedPartyFilter = 'all';
            syncPartyFilterButtons();
            applyFilters();
        });

        syncPartyFilterButtons();
        applyFilters();
    })();
</script>
