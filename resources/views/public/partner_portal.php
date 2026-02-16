<?php
    $title = 'Wizard inrolare partener';
    $context = $context ?? [];
    $prefill = $prefill ?? [];
    $partnerCui = $partnerCui ?? '';
    $company = $company ?? null;
    $contacts = $contacts ?? [];
    $relationContacts = $relationContacts ?? [];
    $contracts = $contracts ?? [];
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
    if ($currentStep < 1 || $currentStep > 3) {
        $currentStep = 1;
    }
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
        'draft' => 'bg-slate-100 text-slate-700',
        'waiting_signature' => 'bg-amber-100 text-amber-800',
        'submitted' => 'bg-blue-100 text-blue-800',
        'approved' => 'bg-emerald-100 text-emerald-800',
        'rejected' => 'bg-rose-100 text-rose-800',
    ];
    $statusLabels = [
        'draft' => 'Ciorna',
        'generated' => 'Generat',
        'sent' => 'Trimis',
        'signed_uploaded' => 'Semnat incarcat',
        'approved' => 'Aprobat',
    ];
    $statusClasses = [
        'draft' => 'bg-slate-100 text-slate-700',
        'generated' => 'bg-blue-100 text-blue-700',
        'sent' => 'bg-amber-100 text-amber-700',
        'signed_uploaded' => 'bg-purple-100 text-purple-700',
        'approved' => 'bg-emerald-100 text-emerald-700',
    ];
    $contactDepartments = $contactDepartments ?? ['Reprezentant legal', 'Financiar-contabil', 'Achizitii', 'Logistica'];
?>

<div class="max-w-6xl">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Wizard inrolare partener</h1>
        <p class="mt-1 text-sm text-slate-600">Link unic pentru completare date, incarcare documente si trimitere spre activare manuala.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <?= htmlspecialchars((string) $error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($permissions['can_view'])): ?>
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded border border-slate-200 bg-white px-4 py-3 text-sm">
            <span class="font-semibold text-slate-700">Status onboarding:</span>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $onboardingClasses[$onboardingStatus] ?? 'bg-slate-100 text-slate-700' ?>">
                <?= htmlspecialchars($onboardingLabels[$onboardingStatus] ?? ucfirst($onboardingStatus)) ?>
            </span>
            <span class="text-slate-500">
                Documente obligatorii semnate: <?= $requiredSigned ?>/<?= $requiredTotal ?>
            </span>
        </div>

        <?php if (!$pdfAvailable): ?>
            <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Generarea PDF este momentan indisponibila pe server. Puteti continua completarea, dar download-ul PDF va deveni disponibil
                dupa configurarea utilitarului wkhtmltopdf de catre echipa interna.
            </div>
        <?php endif; ?>

        <?php if ($isReadOnly): ?>
            <div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                <?php if ($onboardingStatus === 'submitted'): ?>
                    Cererea a fost trimisa spre activare. Un angajat intern va analiza si aproba inrolarea.
                <?php else: ?>
                    Inrolarea este aprobata. Datele raman disponibile pentru consultare din acest link.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mt-4 rounded border border-slate-200 bg-white px-4 py-3 text-sm">
            <div class="text-sm font-semibold text-slate-700">Navigare pasi: <?= (int) $currentStep ?>/3</div>
            <div class="mt-3 flex flex-wrap gap-2">
                <?php foreach ([1 => 'Pasul 1/3 - Date companie si contacte', 2 => 'Pasul 2/3 - Documente', 3 => 'Pasul 3/3 - Confirmare finala'] as $step => $label): ?>
                    <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/set-step') ?>">
                        <?= App\Support\Csrf::input() ?>
                        <input type="hidden" name="step" value="<?= (int) $step ?>">
                        <button
                            class="rounded border px-3 py-1.5 text-xs font-semibold <?= $currentStep === $step ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"
                        >
                            <?= htmlspecialchars($label) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" id="pas-1">
            <div class="text-base font-semibold text-slate-800">Pasul 1/3: Date companie + contacte</div>
            <p class="mt-1 text-sm text-slate-500">
                Salvati datele firmei, apoi adaugati persoanele de contact. Pentru onboarding client, relatia este fixa pe furnizorul din link.
            </p>

            <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/save-company') ?>" class="mt-4">
                <?= App\Support\Csrf::input() ?>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="cui">CUI</label>
                        <div class="mt-1 flex gap-2">
                            <input
                                id="cui"
                                name="cui"
                                type="text"
                                value="<?= htmlspecialchars((string) ($prefill['cui'] ?? '')) ?>"
                                class="block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                                required
                                <?= $isReadOnly ? 'readonly' : '' ?>
                            >
                            <?php if (!$isReadOnly): ?>
                                <button
                                    type="button"
                                    id="openapi-fetch"
                                    class="rounded border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    OpenAPI
                                </button>
                            <?php endif; ?>
                        </div>
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
                            <?= $isReadOnly ? 'readonly' : '' ?>
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
                        <label class="block text-sm font-medium text-slate-700" for="localitate">Localitate</label>
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
                </div>
                <?php if (!$isReadOnly): ?>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Salveaza date companie
                        </button>
                        <button
                            name="next_step"
                            value="2"
                            class="rounded border border-blue-600 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50"
                        >
                            Salveaza si continua la documente
                        </button>
                    </div>
                <?php endif; ?>
            </form>

            <div class="mt-6 border-t border-slate-100 pt-4">
                <div class="text-sm font-semibold text-slate-700">Contacte companie</div>
                <div class="mt-3 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Daca doriti sa primiti facturile pe o alta adresa de e-mail decat cea generala a companiei, adaugati un contact
                    in departamentul <strong>Financiar-contabil</strong> cu datele de contact dedicate.
                </div>
                <?php if ($partnerCui === ''): ?>
                    <div class="mt-2 text-sm text-slate-500">Salvati datele companiei pentru a adauga contacte.</div>
                <?php else: ?>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-3 py-2">Nume</th>
                                    <th class="px-3 py-2">Departament</th>
                                    <th class="px-3 py-2">Email</th>
                                    <th class="px-3 py-2">Telefon</th>
                                    <th class="px-3 py-2">Tip</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contacts) && empty($relationContacts)): ?>
                                    <tr>
                                        <td colspan="6" class="px-3 py-4 text-sm text-slate-500">Nu exista contacte inregistrate.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contacts as $contact): ?>
                                        <tr class="border-t border-slate-100">
                                            <td class="px-3 py-2 text-slate-700">
                                                <?= htmlspecialchars((string) ($contact['name'] ?? '')) ?>
                                                <?php if (!empty($contact['is_primary'])): ?>
                                                    <span class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Principal</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['role'] ?? '')) ?></td>
                                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['email'] ?? '')) ?></td>
                                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['phone'] ?? '')) ?></td>
                                            <td class="px-3 py-2 text-slate-600">Partener</td>
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
                                            <td class="px-3 py-2 text-slate-700">
                                                <?= htmlspecialchars((string) ($contact['name'] ?? '')) ?>
                                                <?php if (!empty($contact['is_primary'])): ?>
                                                    <span class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Principal</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['role'] ?? '')) ?></td>
                                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['email'] ?? '')) ?></td>
                                            <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contact['phone'] ?? '')) ?></td>
                                            <td class="px-3 py-2 text-slate-600">Relatie</td>
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
                        <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/save-contact') ?>" class="mt-4 grid gap-3 md:grid-cols-6">
                            <?= App\Support\Csrf::input() ?>
                            <input type="hidden" name="partner_cui" value="<?= htmlspecialchars((string) $partnerCui) ?>">
                            <input type="text" name="name" placeholder="Nume" class="rounded border border-slate-300 px-3 py-2 text-sm md:col-span-2" required>
                            <select name="role" class="rounded border border-slate-300 px-3 py-2 text-sm" required>
                                <option value="">Departament</option>
                                <?php foreach ($contactDepartments as $department): ?>
                                    <option value="<?= htmlspecialchars((string) $department) ?>"><?= htmlspecialchars((string) $department) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="email" name="email" placeholder="Email" class="rounded border border-slate-300 px-3 py-2 text-sm">
                            <input type="text" name="phone" placeholder="Telefon" class="rounded border border-slate-300 px-3 py-2 text-sm">
                            <div class="flex items-center gap-2 text-sm text-slate-600 md:col-span-2">
                                <input type="checkbox" name="is_primary" id="is_primary">
                                <label for="is_primary">Contact principal</label>
                            </div>
                            <?php if (($scope['type'] ?? '') === 'relation'): ?>
                                <div class="md:col-span-2">
                                    <label class="block text-xs text-slate-500">Tip contact</label>
                                    <select name="contact_scope" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                                        <option value="partner">Contact general</option>
                                        <option value="relation">Contact relatie</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="md:col-span-2">
                                <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                    Adauga contact
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" id="pas-2">
            <div class="text-base font-semibold text-slate-800">Pasul 2/3: Documente obligatorii</div>
            <p class="mt-1 text-sm text-slate-500">
                Pentru a continua, incarcati documentele semnate obligatorii.
            </p>

            <?php if ($partnerCui === ''): ?>
                <div class="mt-4 text-sm text-slate-500">Salvati mai intai datele companiei (Pasul 1).</div>
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
                        Pentru a continua la Pasul 3, incarcati toate semnaturile obligatorii.
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

                <div class="mt-4 overflow-x-auto">
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
                                    <td colspan="6" class="px-3 py-4 text-sm text-slate-500">Nu exista documente generate pentru acest onboarding.</td>
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
                                                <?php if ($hasGeneratedPdf || $pdfAvailable): ?>
                                                    <a href="<?= htmlspecialchars($downloadGenerated) ?>" class="text-blue-700 hover:text-blue-800">Descarca PDF</a>
                                                <?php endif; ?>
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
                    <div class="mt-4 rounded border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        Acceptat upload semnat: PDF, JPG, JPEG, PNG. Dimensiune maxima 20MB.
                    </div>
                    <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/upload-signed') ?>" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-3">
                        <?= App\Support\Csrf::input() ?>
                        <select name="contract_id" class="rounded border border-slate-300 px-3 py-2 text-sm" required>
                            <option value="">Selecteaza document</option>
                            <?php foreach ($contracts as $contract): ?>
                                <?php
                                    $optionDocNo = trim((string) ($contract['doc_full_no'] ?? ''));
                                    if ($optionDocNo === '') {
                                        $optionNo = (int) ($contract['doc_no'] ?? 0);
                                        if ($optionNo > 0) {
                                            $optionSeries = trim((string) ($contract['doc_series'] ?? ''));
                                            $optionNoPadded = str_pad((string) $optionNo, 6, '0', STR_PAD_LEFT);
                                            $optionDocNo = $optionSeries !== '' ? ($optionSeries . '-' . $optionNoPadded) : $optionNoPadded;
                                        }
                                    }
                                ?>
                                <option value="<?= (int) $contract['id'] ?>">
                                    <?= htmlspecialchars((string) ($contract['title'] ?? 'Document')) ?>
                                    <?= $optionDocNo !== '' ? ' [' . htmlspecialchars($optionDocNo) . ']' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required class="text-sm text-slate-600">
                        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Incarca semnat
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" id="pas-3">
            <div class="text-base font-semibold text-slate-800">Pasul 3/3: Confirmare finala</div>
            <p class="mt-1 text-sm text-slate-500">
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
                        Pentru a continua, incarcati documentele semnate obligatorii din Pasul 2.
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
</div>

<script>
    (function () {
        const button = document.getElementById('openapi-fetch');
        const cuiInput = document.getElementById('cui');
        if (!button || !cuiInput) {
            return;
        }
        button.addEventListener('click', () => {
            const cui = (cuiInput.value || '').trim();
            if (!cui) {
                alert('Completeaza CUI-ul pentru precompletare.');
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.set('lookup', '1');
            url.searchParams.set('lookup_cui', cui);
            window.location.href = url.toString();
        });
    })();
</script>
