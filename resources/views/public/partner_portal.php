<?php
    $context = $context ?? [];
    $prefill = $prefill ?? [];
    $partnerCui = $partnerCui ?? '';
    $company = $company ?? null;
    $contacts = $contacts ?? [];
    $relationContacts = $relationContacts ?? [];
    $contracts = $contracts ?? [];
    $draftContractTemplates = is_array($draftContractTemplates ?? null) ? $draftContractTemplates : [];
    $onboardingResources = $onboardingResources ?? [];
    $documentsProgress = $documentsProgress ?? [
        'required_total' => 0,
        'required_signed' => 0,
        'all_signed' => true,
        'missing' => [],
    ];
    $scope = $scope ?? [];
    $token = $token ?? '';
    $error = $error ?? '';
    $currentStep = (int) ($currentStep ?? 1);
    if ($currentStep < 1 || $currentStep > 4) {
        $currentStep = 1;
    }
    $maxStep = 4;
    $onboardingStatus = (string) ($onboardingStatus ?? 'draft');
    $pdfAvailable = !empty($pdfAvailable);
    $permissions = $context['permissions'] ?? [
        'can_view' => false,
        'can_upload_signed' => false,
        'can_upload_custom' => false,
    ];
    $isReadOnly = in_array($onboardingStatus, ['submitted', 'approved'], true);
    $requiredTotal = (int) ($documentsProgress['required_total'] ?? 0);
    $requiredSigned = (int) ($documentsProgress['required_signed'] ?? 0);
    $allRequiredSigned = !empty($documentsProgress['all_signed']);
    $missingDocs = (array) ($documentsProgress['missing'] ?? []);

    $onboardingLabels = [
        'draft' => 'Draft',
        'waiting_signature' => 'In asteptare semnaturi',
        'submitted' => 'Trimis spre activare',
        'approved' => 'Aprobat',
        'rejected' => 'Respins',
    ];
    $onboardingClasses = [
        'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-100',
        'waiting_signature' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
        'submitted' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200',
        'approved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200',
        'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-200',
    ];
    $statusLabels = [
        'draft' => 'Ciorna',
        'generated' => 'Generat',
        'sent' => 'Trimis',
        'signed_uploaded' => 'Semnat incarcat',
        'approved' => 'Aprobat',
    ];
    $statusClasses = [
        'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-100',
        'generated' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-200',
        'sent' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-200',
        'signed_uploaded' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-200',
        'approved' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200',
    ];
    $contactDepartments = $contactDepartments ?? ['Reprezentant legal', 'Financiar-contabil', 'Achizitii', 'Logistica'];
    $linkType = (string) ($context['link']['type'] ?? 'client');
    $isSupplierFlow = $linkType === 'supplier';
    $stepLabels = [
        1 => 'Pasul 1/4 - Instructiuni onboarding',
        2 => 'Pasul 2/4 - Date firma',
        3 => 'Pasul 3/4 - Date de contact',
        4 => 'Pasul 4/4 - Documente si confirmare',
    ];
    $companyDisplayName = trim((string) ($prefill['denumire'] ?? ($company?->denumire ?? '')));
    $title = $companyDisplayName !== ''
        ? ('Inrolare partener: ' . $companyDisplayName)
        : 'Inrolare partener';
    $hasMandatoryCompanyProfile = trim((string) ($prefill['legal_representative_name'] ?? '')) !== ''
        && trim((string) ($prefill['legal_representative_role'] ?? '')) !== ''
        && trim((string) ($prefill['bank_name'] ?? '')) !== ''
        && trim((string) ($prefill['iban'] ?? '')) !== '';
    $contactCount = count($contacts) + count($relationContacts);
    $stepCompletion = [
        1 => true,
        2 => $partnerCui !== '' && $hasMandatoryCompanyProfile,
        3 => $contactCount > 0,
        4 => $allRequiredSigned && $partnerCui !== '',
    ];
    if ($isReadOnly) {
        $stepCompletion[4] = true;
    }
    $completedStepsCount = 0;
    foreach ($stepCompletion as $isCompletedStep) {
        if (!empty($isCompletedStep)) {
            $completedStepsCount++;
        }
    }
    $wizardProgressPercent = (int) round(($completedStepsCount / max(1, $maxStep)) * 100);
?>

<div class="mx-auto w-full max-w-6xl space-y-5">

    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-700 dark:bg-rose-900/40 dark:text-rose-200">
            <?= htmlspecialchars((string) $error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($permissions['can_view'])): ?>
        <div class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 via-white to-emerald-50 p-5 shadow-sm dark:border-slate-700 dark:bg-gradient-to-br dark:from-slate-900 dark:via-slate-900 dark:to-slate-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="text-sm font-semibold text-blue-700 dark:text-blue-300"><?= htmlspecialchars($title) ?></div>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Completeaza pasii in ordine pentru activare</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-semibold text-slate-700 dark:text-slate-200">Status:</span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $onboardingClasses[$onboardingStatus] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-100' ?>">
                            <?= htmlspecialchars($onboardingLabels[$onboardingStatus] ?? ucfirst($onboardingStatus)) ?>
                        </span>
                    </div>
                </div>
                <div class="grid w-full gap-3 sm:grid-cols-3 lg:w-auto lg:min-w-[420px]">
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950/70">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Pas curent</div>
                        <div class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100"><?= (int) $currentStep ?>/<?= (int) $maxStep ?></div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950/70">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Documente obligatorii</div>
                        <div class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100"><?= $requiredSigned ?>/<?= $requiredTotal ?></div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950/70">
                        <div class="text-xs text-slate-500 dark:text-slate-400">Contacte adaugate</div>
                        <div class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100"><?= (int) $contactCount ?></div>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <div class="mb-1 flex items-center justify-between text-xs font-medium text-slate-600 dark:text-slate-300">
                    <span>Progres completare</span>
                    <span><?= (int) $wizardProgressPercent ?>%</span>
                </div>
                <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-700">
                    <div class="h-2 rounded-full bg-blue-600" style="width: <?= (int) $wizardProgressPercent ?>%;"></div>
                </div>
            </div>
        </div>

        <?php if (!$pdfAvailable): ?>
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
                Generarea PDF este momentan indisponibila pe server. Puteti continua completarea, dar download-ul PDF va deveni disponibil
                dupa configurarea utilitarului wkhtmltopdf de catre echipa interna.
            </div>
        <?php endif; ?>

        <?php if ($isReadOnly): ?>
            <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-700 dark:bg-blue-900/40 dark:text-blue-200">
                <?php if ($onboardingStatus === 'submitted'): ?>
                    Cererea a fost trimisa spre activare. Un angajat intern va analiza si aproba inrolarea.
                <?php else: ?>
                    Inrolarea este aprobata. Datele raman disponibile pentru consultare din acest link.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm font-semibold text-slate-700 dark:text-slate-100">Navigare rapida pe pasi</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Pas curent: <?= (int) $currentStep ?>/<?= (int) $maxStep ?></div>
            </div>
            <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-4">
                <?php foreach ($stepLabels as $step => $label): ?>
                    <?php
                        $isActiveStep = $currentStep === $step;
                        $isCompletedStep = !empty($stepCompletion[$step]);
                        $stepButtonClasses = 'w-full rounded-xl border px-3 py-3 text-left text-sm transition';
                        if ($isActiveStep) {
                            $stepButtonClasses .= ' border-blue-600 bg-blue-50 text-blue-800 shadow-sm dark:border-blue-400 dark:bg-blue-950/70 dark:text-blue-100';
                        } elseif ($isCompletedStep) {
                            $stepButtonClasses .= ' border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-100 dark:hover:bg-emerald-900/70';
                        } else {
                            $stepButtonClasses .= ' border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
                        }
                        $stepStatus = $isCompletedStep
                            ? 'Completat'
                            : ($isActiveStep ? 'In lucru' : 'In asteptare');
                    ?>
                    <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/set-step') ?>">
                        <?= App\Support\Csrf::input() ?>
                        <input type="hidden" name="step" value="<?= (int) $step ?>">
                        <button class="<?= $stepButtonClasses ?>">
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                    <?= (int) $step ?>
                                </span>
                                <span class="text-[11px] font-semibold uppercase tracking-wide"><?= htmlspecialchars($stepStatus) ?></span>
                            </div>
                            <div class="mt-2 text-xs font-semibold"><?= htmlspecialchars($label) ?></div>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($currentStep === 1): ?>
            <div class="mt-1 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" id="pas-1">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Pasul 1 din 4</div>
                        <div class="text-lg font-semibold text-slate-900">Instructiuni onboarding</div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">Start rapid</span>
                </div>
                <?php if ($isSupplierFlow): ?>
                    <p class="mt-4 text-sm leading-6 text-slate-700">
                        Acest link este pentru onboarding <strong>furnizor</strong>. In pasii urmatori vei completa datele firmei,
                        datele de contact, apoi vei descarca si incarca documentele semnate pentru activare.
                    </p>
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
                        <li>Pasul 2: completeaza datele companiei (reprezentant legal, banca, IBAN).</li>
                        <li>Pasul 3: adauga persoanele de contact relevante (ex. financiar-contabil).</li>
                        <li>Pasul 4: descarca documentele, semneaza, incarca si trimite spre activare.</li>
                    </ul>
                <?php else: ?>
                    <p class="mt-4 text-sm leading-6 text-slate-700">
                        Acest link este pentru onboarding <strong>client</strong>. Relatia cu furnizorul este preconfigurata in link.
                        Urmareste pasii de mai jos pentru completare si trimitere spre activare.
                    </p>
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
                        <li>Pasul 2: verifica si completeaza datele companiei.</li>
                        <li>Pasul 3: adauga contacte operationale si financiar-contabile.</li>
                        <li>Pasul 4: gestioneaza documentele obligatorii si confirma trimiterea.</li>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($onboardingResources)): ?>
                    <div class="mt-5 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                        <div class="text-sm font-semibold text-slate-800">Documente pentru descarcare (Pasul 1)</div>
                        <ul class="mt-3 space-y-2 text-sm text-slate-700">
                            <?php foreach ($onboardingResources as $resource): ?>
                                <?php
                                    $resourceId = (int) ($resource['id'] ?? 0);
                                    $resourceTitle = trim((string) ($resource['title'] ?? 'Document onboarding'));
                                    if ($resourceTitle === '') {
                                        $resourceTitle = 'Document onboarding';
                                    }
                                    $resourceOriginalName = trim((string) ($resource['original_name'] ?? ''));
                                    $resourceDownloadUrl = App\Support\Url::to('p/' . $token . '/resource?id=' . $resourceId);
                                ?>
                                <li class="flex flex-wrap items-center gap-2">
                                    <a href="<?= htmlspecialchars($resourceDownloadUrl) ?>" class="font-semibold text-blue-700 hover:text-blue-800">
                                        <?= htmlspecialchars($resourceTitle) ?>
                                    </a>
                                    <?php if ($resourceOriginalName !== ''): ?>
                                        <span class="text-xs text-slate-500">(<?= htmlspecialchars($resourceOriginalName) ?>)</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                    <div class="text-sm font-semibold text-slate-800">Preview-uri contracte onboarding</div>
                    <?php if (empty($contracts)): ?>
                        <?php if (empty($draftContractTemplates)): ?>
                            <p class="mt-2 text-sm text-slate-500">
                                Nu exista template-uri onboarding obligatorii configurate pentru acest tip de inrolare.
                            </p>
                        <?php else: ?>
                            <p class="mt-2 text-sm text-slate-600">
                                Pana la generarea documentelor pe relatie, poti previzualiza si descarca drafturile din template-uri.
                            </p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-700">
                                <?php foreach ($draftContractTemplates as $draftTemplate): ?>
                                    <?php
                                        $templateId = (int) ($draftTemplate['template_id'] ?? 0);
                                        if ($templateId <= 0) {
                                            continue;
                                        }
                                        $draftTitle = trim((string) ($draftTemplate['title'] ?? 'Document draft'));
                                        if ($draftTitle === '') {
                                            $draftTitle = 'Document draft';
                                        }
                                        $draftPriority = (int) ($draftTemplate['priority'] ?? 100);
                                        $draftPreviewUrl = App\Support\Url::to('p/' . $token . '/preview-draft?template_id=' . $templateId);
                                        $draftDownloadUrl = App\Support\Url::to('p/' . $token . '/download-draft?template_id=' . $templateId);
                                    ?>
                                    <li class="flex flex-wrap items-center gap-3">
                                        <span class="font-medium text-slate-700"><?= htmlspecialchars($draftTitle) ?></span>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                                            Draft P<?= (int) $draftPriority ?>
                                        </span>
                                        <a href="<?= htmlspecialchars($draftPreviewUrl) ?>" target="_blank" rel="noopener" class="text-blue-700 hover:text-blue-800">Preview draft</a>
                                        <a href="<?= htmlspecialchars($draftDownloadUrl) ?>" class="text-blue-700 hover:text-blue-800">Descarca draft PDF</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php else: ?>
                        <ul class="mt-3 space-y-2 text-sm text-slate-700">
                            <?php foreach ($contracts as $contractPreview): ?>
                                <?php
                                    $contractId = (int) ($contractPreview['id'] ?? 0);
                                    if ($contractId <= 0) {
                                        continue;
                                    }
                                    $contractTitle = trim((string) ($contractPreview['title'] ?? 'Document'));
                                    if ($contractTitle === '') {
                                        $contractTitle = 'Document';
                                    }
                                    $previewUrl = App\Support\Url::to('p/' . $token . '/preview?id=' . $contractId);
                                    $downloadGeneratedUrl = App\Support\Url::to('p/' . $token . '/download?kind=generated&id=' . $contractId);
                                ?>
                                <li class="flex flex-wrap items-center gap-3">
                                    <span class="font-medium text-slate-700"><?= htmlspecialchars($contractTitle) ?></span>
                                    <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" rel="noopener" class="text-blue-700 hover:text-blue-800">Preview</a>
                                    <a href="<?= htmlspecialchars($downloadGeneratedUrl) ?>" class="text-blue-700 hover:text-blue-800">Descarca PDF</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($onboardingResources) || !empty($contracts) || !empty($draftContractTemplates)): ?>
                        <div class="mt-4">
                            <a
                                href="<?= htmlspecialchars(App\Support\Url::to('p/' . $token . '/download-dosar')) ?>"
                                class="inline-flex items-center rounded border border-blue-600 bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                            >
                                Descarca dosar onboarding
                            </a>
                            <p class="mt-1 text-xs text-slate-500">
                                Dosarul contine documentul incarcat la Pasul 1 + draft-urile de contract (ordonate dupa prioritate). Daca merge PDF nu este disponibil pe server, vei primi ZIP.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($currentStep === 2): ?>
        <div class="mt-1 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" id="pas-2">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Pasul 2 din 4</div>
                    <div class="text-lg font-semibold text-slate-900">Date firma</div>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Profil companie</span>
            </div>
            <p class="mt-4 text-sm text-slate-600">
                Completeaza datele de identificare ale companiei. Pentru a continua, profilul firmei trebuie sa fie complet.
            </p>

            <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/save-company') ?>" class="mt-5 space-y-4">
                <?= App\Support\Csrf::input() ?>
                <div class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="cui">CUI</label>
                        <input
                            id="cui"
                            name="cui"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['cui'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            required
                            readonly
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="denumire">Denumire</label>
                        <input
                            id="denumire"
                            name="denumire"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['denumire'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            required
                            readonly
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="nr_reg_comertului">Nr. Reg. Comertului</label>
                        <input
                            id="nr_reg_comertului"
                            name="nr_reg_comertului"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['nr_reg_comertului'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            <?= $isReadOnly ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="judet">Judet</label>
                        <input
                            id="judet"
                            name="judet"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['judet'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            <?= $isReadOnly ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="localitate">Oras</label>
                        <input
                            id="localitate"
                            name="localitate"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['localitate'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            <?= $isReadOnly ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="adresa">Adresa</label>
                        <input
                            id="adresa"
                            name="adresa"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['adresa'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            <?= $isReadOnly ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="legal_representative_name">Reprezentant legal</label>
                        <input
                            id="legal_representative_name"
                            name="legal_representative_name"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['legal_representative_name'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            required
                            <?= $isReadOnly ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="legal_representative_role">Functie reprezentant</label>
                        <input
                            id="legal_representative_role"
                            name="legal_representative_role"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['legal_representative_role'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            required
                            <?= $isReadOnly ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="<?= htmlspecialchars((string) ($prefill['email'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            <?= $isReadOnly ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="telefon">Telefon</label>
                        <input
                            id="telefon"
                            name="telefon"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['telefon'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            <?= $isReadOnly ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="bank_name">Banca</label>
                        <input
                            id="bank_name"
                            name="bank_name"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['bank_name'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            required
                            <?= $isReadOnly ? 'readonly' : '' ?>
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="iban">IBAN</label>
                        <input
                            id="iban"
                            name="iban"
                            type="text"
                            value="<?= htmlspecialchars((string) ($prefill['iban'] ?? '')) ?>"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                            required
                            <?= $isReadOnly ? 'readonly' : '' ?>
                        >
                    </div>
                </div>
                <?php if (!$isReadOnly): ?>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <button
                            name="next_step"
                            value="3"
                            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                        >
                            Salveaza si continua la contacte
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($currentStep === 3): ?>
            <div class="mt-1 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" id="pas-3">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Pasul 3 din 4</div>
                        <div class="text-lg font-semibold text-slate-900">Date de contact</div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Persoane de legatura</span>
                </div>
                <p class="mt-4 text-sm text-slate-600">
                    Adauga persoanele de contact pentru relationarea operationala si financiar-contabila.
                </p>
                <div class="mt-6 border-t border-slate-100 pt-4">
                    <div class="text-sm font-semibold text-slate-700">Contacte companie</div>
                    <div class="mt-3 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Daca doriti sa primiti facturile pe o alta adresa de e-mail decat cea generala a companiei, adaugati un contact
                        in departamentul <strong>Financiar-contabil</strong> cu datele de contact dedicate.
                    </div>
                    <?php if ($partnerCui === ''): ?>
                        <div class="mt-2 text-sm text-slate-500">Salvati datele companiei in Pasul 2 pentru a adauga contacte.</div>
                    <?php else: ?>
                        <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2">Nume</th>
                                        <th class="px-3 py-2">Departament</th>
                                        <th class="px-3 py-2">Email</th>
                                        <th class="px-3 py-2">Telefon</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($contacts) && empty($relationContacts)): ?>
                                        <tr>
                                            <td colspan="5" class="px-3 py-4 text-sm text-slate-500">Nu exista contacte inregistrate.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($contacts as $contact): ?>
                                            <tr class="border-t border-slate-100">
                                                <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contact['name'] ?? '')) ?></td>
                                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['role'] ?? '')) ?></td>
                                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['email'] ?? '')) ?></td>
                                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['phone'] ?? '')) ?></td>
                                                <td class="px-3 py-2 text-right">
                                                    <?php if (!$isReadOnly): ?>
                                                        <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/delete-contact') ?>">
                                                            <?= App\Support\Csrf::input() ?>
                                                            <input type="hidden" name="id" value="<?= (int) $contact['id'] ?>">
                                                            <button class="text-xs font-semibold text-rose-600 hover:text-rose-700">Sterge</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php foreach ($relationContacts as $contact): ?>
                                            <tr class="border-t border-slate-100">
                                                <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contact['name'] ?? '')) ?></td>
                                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['role'] ?? '')) ?></td>
                                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['email'] ?? '')) ?></td>
                                                <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['phone'] ?? '')) ?></td>
                                                <td class="px-3 py-2 text-right">
                                                    <?php if (!$isReadOnly): ?>
                                                        <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/delete-contact') ?>">
                                                            <?= App\Support\Csrf::input() ?>
                                                            <input type="hidden" name="id" value="<?= (int) $contact['id'] ?>">
                                                            <button class="text-xs font-semibold text-rose-600 hover:text-rose-700">Sterge</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!$isReadOnly): ?>
                            <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/save-contact') ?>" class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 md:grid-cols-5">
                                <?= App\Support\Csrf::input() ?>
                                <input type="hidden" name="partner_cui" value="<?= htmlspecialchars((string) $partnerCui) ?>">
                                <input type="text" name="name" placeholder="Nume" class="rounded border border-slate-300 px-3 py-2 text-sm md:col-span-2" required>
                                <select name="role" class="rounded border border-slate-300 px-3 py-2 text-sm" required>
                                    <?php foreach ($contactDepartments as $department): ?>
                                        <option value="<?= htmlspecialchars((string) $department) ?>"><?= htmlspecialchars((string) $department) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="email" name="email" placeholder="Email" class="rounded border border-slate-300 px-3 py-2 text-sm">
                                <input type="text" name="phone" placeholder="Telefon" class="rounded border border-slate-300 px-3 py-2 text-sm">
                                <div class="md:col-span-5">
                                    <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                        Adauga contact
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($currentStep === 4): ?>
        <div class="mt-1 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" id="pas-4">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Pasul 4 din 4</div>
                    <div class="text-lg font-semibold text-slate-900">Documente si confirmare</div>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Finalizare onboarding</span>
            </div>
            <p class="mt-4 text-sm text-slate-600">
                Descarca documentele generate, incarca semnaturile obligatorii si trimite inrolarea spre activare.
            </p>

            <?php if ($partnerCui === ''): ?>
                <div class="mt-4 text-sm text-slate-500">Salvati mai intai datele companiei (Pasul 2).</div>
            <?php else: ?>
                <?php
                    $registryMissing = [];
                    foreach ($contracts as $contractItem) {
                        if (!empty($contractItem['required_onboarding']) && empty($contractItem['doc_no'])) {
                            $registryMissing[] = [
                                'title' => (string) ($contractItem['title'] ?? 'Document'),
                                'doc_type' => (string) ($contractItem['doc_type'] ?? 'contract'),
                            ];
                        }
                    }
                ?>
                <div class="mt-4 rounded border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    Progres documente obligatorii: <strong><?= $requiredSigned ?>/<?= $requiredTotal ?></strong>.
                    <?php if (!$allRequiredSigned): ?>
                        Pentru a finaliza inrolarea, incarcati toate semnaturile obligatorii.
                    <?php else: ?>
                        Toate documentele obligatorii sunt semnate.
                    <?php endif; ?>
                </div>

                <?php if (!$allRequiredSigned && !empty($missingDocs)): ?>
                    <div class="mt-3 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Lipsesc semnaturi pentru:
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <?php foreach ($missingDocs as $missing): ?>
                                <li>
                                    <?= htmlspecialchars((string) ($missing['title'] ?? 'Document')) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($registryMissing)): ?>
                    <div class="mt-3 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Unele documente nu au numar de registru alocat. Contactati un angajat intern pentru configurarea registrului documente.
                    </div>
                <?php endif; ?>

                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Titlu</th>
                                <th class="px-3 py-2">Nr. registru</th>
                                <th class="px-3 py-2">Data contract</th>
                                <th class="px-3 py-2">Obligatoriu</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Actiuni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($contracts)): ?>
                                <tr>
                                    <td colspan="6" class="px-3 py-4 text-sm text-slate-500">
                                        Nu exista documente generate pentru acest onboarding inca. Poti folosi drafturile din Pasul 1.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($contracts as $contract): ?>
                                    <?php
                                        $statusKey = (string) ($contract['status'] ?? '');
                                        $statusLabel = $statusLabels[$statusKey] ?? $statusKey;
                                        $statusClass = $statusClasses[$statusKey] ?? 'bg-slate-100 text-slate-700';
                                        $isRequired = !empty($contract['required_onboarding']);
                                        $hasSigned = !empty($contract['signed_upload_path']) || !empty($contract['signed_file_path']);
                                        $hasGeneratedPdf = !empty($contract['generated_pdf_path']);
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
                                        $previewUrl = App\Support\Url::to('p/' . $token . '/preview?id=' . (int) $contract['id']);
                                        $downloadGenerated = App\Support\Url::to('p/' . $token . '/download?kind=generated&id=' . (int) $contract['id']);
                                        $downloadSigned = App\Support\Url::to('p/' . $token . '/download?kind=signed&id=' . (int) $contract['id']);
                                    ?>
                                    <tr class="border-t border-slate-100">
                                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contract['title'] ?? '')) ?></td>
                                        <td class="px-3 py-2 text-slate-600">
                                            <?php if ($docNoDisplay !== ''): ?>
                                                <span class="font-mono"><?= htmlspecialchars($docNoDisplay) ?></span>
                                            <?php else: ?>
                                                <span class="text-amber-700">Fara numar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($contractDateDisplay) ?></td>
                                        <td class="px-3 py-2 text-slate-600"><?= $isRequired ? 'Da' : 'Nu' ?></td>
                                        <td class="px-3 py-2 text-slate-600">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $statusClass ?>">
                                                <?= htmlspecialchars($statusLabel) ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            <div class="flex flex-wrap gap-3 text-xs font-semibold">
                                                <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" rel="noopener" class="text-blue-700 hover:text-blue-800">Previzualizeaza</a>
                                                <a href="<?= htmlspecialchars($downloadGenerated) ?>" class="text-blue-700 hover:text-blue-800">Descarca PDF</a>
                                                <?php if ($hasSigned): ?>
                                                    <a href="<?= htmlspecialchars($downloadSigned) ?>" class="text-blue-700 hover:text-blue-800">Descarca semnat</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$isReadOnly && !empty($permissions['can_upload_signed']) && !empty($contracts)): ?>
                    <?php
                        $uploadTargetContracts = [];
                        foreach ($contracts as $contractOption) {
                            if (!empty($contractOption['required_onboarding'])) {
                                $uploadTargetContracts[] = $contractOption;
                            }
                        }
                        if (empty($uploadTargetContracts)) {
                            $uploadTargetContracts = $contracts;
                        }
                        $uploadContractOptions = [];
                        foreach ($uploadTargetContracts as $contractOption) {
                            $optionId = (int) ($contractOption['id'] ?? 0);
                            if ($optionId <= 0) {
                                continue;
                            }
                            $optionDocNo = trim((string) ($contractOption['doc_full_no'] ?? ''));
                            if ($optionDocNo === '') {
                                $optionNo = (int) ($contractOption['doc_no'] ?? 0);
                                if ($optionNo > 0) {
                                    $optionSeries = trim((string) ($contractOption['doc_series'] ?? ''));
                                    $optionNoPadded = str_pad((string) $optionNo, 6, '0', STR_PAD_LEFT);
                                    $optionDocNo = $optionSeries !== '' ? ($optionSeries . '-' . $optionNoPadded) : $optionNoPadded;
                                }
                            }
                            $label = trim((string) ($contractOption['title'] ?? 'Document'));
                            if ($label === '') {
                                $label = 'Document';
                            }
                            if ($optionDocNo !== '') {
                                $label .= ' [' . $optionDocNo . ']';
                            }
                            $uploadContractOptions[] = [
                                'id' => $optionId,
                                'label' => $label,
                            ];
                        }
                        $uploadContractOptionsJson = json_encode(
                            $uploadContractOptions,
                            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                        );
                    ?>
                    <div class="mt-4 rounded border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        Acceptat upload semnat: PDF, JPG, JPEG, PNG. Dimensiune maxima 20MB.
                    </div>

                    <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/upload-signed') ?>" enctype="multipart/form-data" class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-5">
                        <?= App\Support\Csrf::input() ?>
                        <div class="text-sm font-semibold text-blue-900">Incarcare documente semnate</div>
                        <p class="mt-1 text-sm text-blue-800">
                            Alegeti modul de incarcare: fie fisiere separate pe document, fie un singur fisier care contine toate documentele obligatorii.
                        </p>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-2 rounded border border-blue-200 bg-white px-3 py-2 text-sm text-slate-700">
                                <input type="radio" name="upload_mode" value="batch" checked class="mt-0.5">
                                <span>
                                    <span class="font-semibold text-slate-800">Fisiere separate pe documente</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">Incarcati mai multe fisiere si alegeti documentul pentru fiecare.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-2 rounded border border-blue-200 bg-white px-3 py-2 text-sm text-slate-700">
                                <input type="radio" name="upload_mode" value="all_in_one" class="mt-0.5">
                                <span>
                                    <span class="font-semibold text-slate-800">Fisier unic pentru toate documentele</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">Pentru un singur scan/PDF cu toate documentele semnate.</span>
                                </span>
                            </label>
                        </div>

                        <div id="upload-mode-batch" class="mt-4 rounded-xl border-2 border-dashed border-blue-300 bg-white p-5">
                            <label class="block text-center text-sm font-medium text-slate-700" for="signed-files-input">
                                Alege fisierele semnate
                            </label>
                            <input
                                id="signed-files-input"
                                type="file"
                                name="signed_files[]"
                                accept=".pdf,.jpg,.jpeg,.png"
                                multiple
                                required
                                class="mt-3 block w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700"
                            >
                            <p class="mt-2 text-xs text-slate-500">
                                Dupa selectie, pentru fiecare fisier va aparea campul de alegere document.
                            </p>
                            <div id="signed-files-mapping" class="mt-4 hidden space-y-3"></div>
                        </div>

                        <div id="upload-mode-all-in-one" class="mt-4 hidden rounded-xl border-2 border-dashed border-amber-300 bg-amber-50 p-5">
                            <label class="block text-sm font-medium text-slate-700" for="all-signed-file">
                                Fisier cu toate documentele obligatorii semnate
                            </label>
                            <input
                                id="all-signed-file"
                                type="file"
                                name="all_signed_file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="mt-2 block w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700"
                            >
                            <p class="mt-2 text-xs text-slate-600">
                                Fisierul incarcat aici va fi atasat automat la toate documentele obligatorii.
                            </p>
                        </div>

                        <div class="mt-4">
                            <button id="signed-upload-submit" class="rounded bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Salveaza documentele semnate
                            </button>
                        </div>
                    </form>
                    <script id="signed-contract-options" type="application/json"><?= $uploadContractOptionsJson !== false ? $uploadContractOptionsJson : '[]' ?></script>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" id="pas-4-confirmare">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Finalizare</div>
                    <div class="text-lg font-semibold text-slate-900">Confirmare finala</div>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Verificare si trimitere</span>
            </div>
            <p class="mt-4 text-sm text-slate-600">
                Verificati datele si trimiteti cererea spre activare. Activarea este manuala si poate fi facuta doar de angajati interni.
            </p>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="rounded border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <div class="font-semibold text-slate-800">Rezumat companie</div>
                    <div class="mt-2"><span class="font-medium">CUI:</span> <?= htmlspecialchars((string) ($partnerCui !== '' ? $partnerCui : '—')) ?></div>
                    <div><span class="font-medium">Denumire:</span> <?= htmlspecialchars((string) ($prefill['denumire'] ?? $company?->denumire ?? '—')) ?></div>
                    <div><span class="font-medium">Email:</span> <?= htmlspecialchars((string) ($prefill['email'] ?? $company?->email ?? '—')) ?></div>
                    <div><span class="font-medium">Telefon:</span> <?= htmlspecialchars((string) ($prefill['telefon'] ?? $company?->telefon ?? '—')) ?></div>
                </div>
                <div class="rounded border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <div class="font-semibold text-slate-800">Rezumat documente</div>
                    <div class="mt-2">Documente obligatorii: <strong><?= $requiredSigned ?>/<?= $requiredTotal ?></strong></div>
                    <div>Status completare:
                        <?php if ($allRequiredSigned): ?>
                            <span class="font-semibold text-emerald-700">Complet</span>
                        <?php else: ?>
                            <span class="font-semibold text-amber-700">Incomplet</span>
                        <?php endif; ?>
                    </div>
                    <div>Contacte inregistrate: <?= count($contacts) + count($relationContacts) ?></div>
                </div>
            </div>

            <?php if ($onboardingStatus === 'submitted'): ?>
                <div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    Cererea este deja trimisa spre activare. Va rugam asteptati validarea unui angajat intern.
                </div>
            <?php elseif ($onboardingStatus === 'approved'): ?>
                <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    Inrolarea a fost aprobata. Nu este necesara retrimiterea.
                </div>
            <?php else: ?>
                <?php if (!$allRequiredSigned): ?>
                    <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Pentru a continua, incarcati documentele semnate obligatorii din sectiunea de mai sus.
                    </div>
                <?php endif; ?>
                <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/submit-activation') ?>" class="mt-4">
                    <?= App\Support\Csrf::input() ?>
                    <label class="inline-flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="checkbox_confirmed" value="1" class="mt-0.5 rounded border-slate-300" required>
                        <span>Confirm ca datele sunt corecte si complete. Trimit inrolarea spre activare manuala de catre un angajat intern.</span>
                    </label>
                    <div class="mt-4">
                        <button
                            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 <?= (!$allRequiredSigned || $partnerCui === '') ? 'opacity-50 cursor-not-allowed' : '' ?>"
                            <?= (!$allRequiredSigned || $partnerCui === '') ? 'disabled' : '' ?>
                        >
                            Trimite spre activare
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
            <div>
                <?php if ($currentStep > 1): ?>
                    <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/set-step') ?>">
                        <?= App\Support\Csrf::input() ?>
                        <input type="hidden" name="step" value="<?= (int) ($currentStep - 1) ?>">
                        <button class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:bg-slate-50">
                            &larr; Pasul anterior (<?= (int) ($currentStep - 1) ?>/<?= (int) $maxStep ?>)
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div>
                <?php if (!$isReadOnly && $currentStep < $maxStep): ?>
                    <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/set-step') ?>">
                        <?= App\Support\Csrf::input() ?>
                        <input type="hidden" name="step" value="<?= (int) ($currentStep + 1) ?>">
                        <button class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
                            Pasul urmator (<?= (int) ($currentStep + 1) ?>/<?= (int) $maxStep ?>) &rarr;
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        const fileInput = document.getElementById('signed-files-input');
        const allFileInput = document.getElementById('all-signed-file');
        const mappingContainer = document.getElementById('signed-files-mapping');
        const batchPanel = document.getElementById('upload-mode-batch');
        const allInOnePanel = document.getElementById('upload-mode-all-in-one');
        const submitButton = document.getElementById('signed-upload-submit');
        const modeInputs = Array.from(document.querySelectorAll('input[name="upload_mode"]'));
        const optionsNode = document.getElementById('signed-contract-options');
        if (!fileInput || !allFileInput || !mappingContainer || !batchPanel || !allInOnePanel || !submitButton || !optionsNode || modeInputs.length === 0) {
            return;
        }

        let options = [];
        try {
            const parsed = JSON.parse(optionsNode.textContent || '[]');
            if (Array.isArray(parsed)) {
                options = parsed;
            }
        } catch (error) {
            options = [];
        }

        const formatFileSize = (bytes) => {
            const value = Number(bytes || 0);
            if (!Number.isFinite(value) || value <= 0) {
                return '0 KB';
            }
            if (value < 1024 * 1024) {
                return (value / 1024).toFixed(1) + ' KB';
            }
            return (value / (1024 * 1024)).toFixed(2) + ' MB';
        };

        const createOption = (contract, isSelected) => {
            const option = document.createElement('option');
            option.value = String(contract.id || '');
            option.textContent = String(contract.label || 'Document');
            if (isSelected) {
                option.selected = true;
            }
            return option;
        };

        const selectedMode = () => {
            const checked = modeInputs.find((input) => input.checked);
            return checked ? checked.value : 'batch';
        };

        const renderMapping = () => {
            if (selectedMode() !== 'batch') {
                mappingContainer.classList.add('hidden');
                mappingContainer.innerHTML = '';
                return;
            }

            const files = Array.from(fileInput.files || []);
            mappingContainer.innerHTML = '';
            if (files.length === 0) {
                mappingContainer.classList.add('hidden');
                return;
            }

            mappingContainer.classList.remove('hidden');
            files.forEach((file, index) => {
                const row = document.createElement('div');
                row.className = 'grid gap-3 rounded border border-slate-200 bg-white p-3 md:grid-cols-[minmax(0,1fr)_320px]';

                const fileInfo = document.createElement('div');
                fileInfo.className = 'text-sm text-slate-700';
                fileInfo.innerHTML = '<div class="font-semibold">' + String(index + 1) + '. ' + String(file.name || 'fisier') + '</div>'
                    + '<div class="text-xs text-slate-500 mt-1">' + formatFileSize(file.size) + '</div>';

                const selectWrap = document.createElement('div');
                const label = document.createElement('label');
                label.className = 'mb-1 block text-xs font-medium text-slate-600';
                label.textContent = 'Document';
                const select = document.createElement('select');
                select.name = 'signed_contract_ids[]';
                select.required = true;
                select.className = 'block w-full rounded border border-slate-300 px-3 py-2 text-sm';

                options.forEach((optionItem, optionIndex) => {
                    select.appendChild(createOption(optionItem, optionIndex === Math.min(index, options.length - 1)));
                });
                if (options.length === 0) {
                    const emptyOption = document.createElement('option');
                    emptyOption.value = '';
                    emptyOption.textContent = 'Nu exista documente disponibile';
                    emptyOption.selected = true;
                    select.appendChild(emptyOption);
                    select.disabled = true;
                }

                selectWrap.appendChild(label);
                selectWrap.appendChild(select);
                row.appendChild(fileInfo);
                row.appendChild(selectWrap);
                mappingContainer.appendChild(row);
            });
        };

        const syncMode = () => {
            const allInOne = selectedMode() === 'all_in_one';
            batchPanel.classList.toggle('hidden', allInOne);
            allInOnePanel.classList.toggle('hidden', !allInOne);
            fileInput.required = !allInOne;
            allFileInput.required = allInOne;
            submitButton.textContent = allInOne
                ? 'Salveaza fisierul pentru toate documentele'
                : 'Salveaza documentele semnate';

            if (allInOne) {
                mappingContainer.classList.add('hidden');
            } else {
                renderMapping();
            }
        };

        fileInput.addEventListener('change', renderMapping);
        modeInputs.forEach((input) => {
            input.addEventListener('change', syncMode);
        });
        syncMode();
    })();
</script>
