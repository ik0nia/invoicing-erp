<?php
    $title = 'Wizard partener';
    $context = $context ?? [];
    $prefill = $prefill ?? [];
    $partnerCui = $partnerCui ?? '';
    $contacts = $contacts ?? [];
    $relationContacts = $relationContacts ?? [];
    $contracts = $contracts ?? [];
    $scope = $scope ?? [];
    $token = $token ?? '';
    $error = $error ?? '';
    $currentStep = (int) ($currentStep ?? 1);
    if ($currentStep < 1 || $currentStep > 3) {
        $currentStep = 1;
    }
    $permissions = $context['permissions'] ?? [
        'can_view' => false,
        'can_upload_signed' => false,
        'can_upload_custom' => false,
    ];
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

    $confirmedAt = $context['link']['confirmed_at'] ?? null;
    $statusText = $partnerCui !== '' ? 'Datele companiei sunt salvate. Puteti continua cu pasii urmatori.' : 'In asteptarea completarii datelor.';
    if ($confirmedAt) {
        $statusText = 'Inrolare finalizata. Puteti reveni oricand pentru actualizari.';
    }
?>

<div class="max-w-5xl">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Wizard partener</h1>
        <p class="mt-1 text-sm text-slate-600">Un singur link pentru date companie, contacte si contracte.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <?= htmlspecialchars((string) $error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($permissions['can_view'])): ?>
        <div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <?= htmlspecialchars($statusText) ?>
        </div>

        <div class="mt-4 rounded border border-slate-200 bg-white px-4 py-3 text-sm">
            <div class="text-sm font-semibold text-slate-700">Pas curent: <?= (int) $currentStep ?> / 3</div>
            <div class="mt-3 flex flex-wrap gap-2">
                <?php foreach ([1 => 'Date companie', 2 => 'Contacte', 3 => 'Contracte'] as $step => $label): ?>
                    <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/set-step') ?>">
                        <?= App\Support\Csrf::input() ?>
                        <input type="hidden" name="step" value="<?= (int) $step ?>">
                        <button
                            class="rounded border px-3 py-1.5 text-xs font-semibold <?= $currentStep === $step ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>"
                        >
                            Pasul <?= (int) $step ?>: <?= htmlspecialchars($label) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" id="pas-1">
            <div class="text-base font-semibold text-slate-800">Pasul 1: Date companie</div>
            <p class="mt-1 text-sm text-slate-500">Completati datele companiei si salvati.</p>

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
                            >
                            <button
                                type="button"
                                id="openapi-fetch"
                                class="rounded border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                OpenAPI
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Apasa OpenAPI pentru precompletare automata.</p>
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
                        >
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Salveaza
                    </button>
                    <button
                        name="next_step"
                        value="2"
                        class="rounded border border-blue-600 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50"
                    >
                        Salveaza si continua
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" id="pas-2">
            <div class="text-base font-semibold text-slate-800">Pasul 2: Persoane de contact</div>
            <p class="mt-1 text-sm text-slate-500">Adaugati persoanele de contact pentru aceasta companie.</p>

            <?php if ($partnerCui === ''): ?>
                <div class="mt-4 text-sm text-slate-500">Salvati mai intai datele companiei pentru a adauga contacte.</div>
            <?php else: ?>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Nume</th>
                                <th class="px-3 py-2">Rol</th>
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
                                            <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/delete-contact') ?>">
                                                <?= App\Support\Csrf::input() ?>
                                                <input type="hidden" name="id" value="<?= (int) $contact['id'] ?>">
                                                <button class="text-xs font-semibold text-rose-600 hover:text-rose-700">Sterge</button>
                                            </form>
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
                                            <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/delete-contact') ?>">
                                                <?= App\Support\Csrf::input() ?>
                                                <input type="hidden" name="id" value="<?= (int) $contact['id'] ?>">
                                                <button class="text-xs font-semibold text-rose-600 hover:text-rose-700">Sterge</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/save-contact') ?>" class="mt-4 grid gap-3 md:grid-cols-6">
                    <?= App\Support\Csrf::input() ?>
                    <input type="hidden" name="partner_cui" value="<?= htmlspecialchars((string) $partnerCui) ?>">
                    <input
                        type="text"
                        name="name"
                        placeholder="Nume"
                        class="rounded border border-slate-300 px-3 py-2 text-sm md:col-span-2"
                        required
                    >
                    <select name="role" class="rounded border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Rol/Functie</option>
                        <option value="Administrator">Administrator</option>
                        <option value="Contabil">Contabil</option>
                        <option value="Achizitii">Achizitii</option>
                        <option value="Vanzari">Vanzari</option>
                        <option value="Manager">Manager</option>
                    </select>
                    <input
                        type="email"
                        name="email"
                        placeholder="Email"
                        class="rounded border border-slate-300 px-3 py-2 text-sm"
                    >
                    <input
                        type="text"
                        name="phone"
                        placeholder="Telefon"
                        class="rounded border border-slate-300 px-3 py-2 text-sm"
                    >
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
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/set-step') ?>">
                        <?= App\Support\Csrf::input() ?>
                        <input type="hidden" name="step" value="1">
                        <button class="rounded border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Inapoi la date companie
                        </button>
                    </form>
                    <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/set-step') ?>">
                        <?= App\Support\Csrf::input() ?>
                        <input type="hidden" name="step" value="3">
                        <button class="rounded border border-blue-600 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                            Continua la contracte
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm" id="pas-3">
            <div class="text-base font-semibold text-slate-800">Pasul 3: Contracte si documente</div>
            <p class="mt-1 text-sm text-slate-500">Previzualizati, descarcati sau incarcati contracte semnate.</p>

            <?php if ($partnerCui === ''): ?>
                <div class="mt-4 text-sm text-slate-500">Salvati mai intai datele companiei pentru a vedea contractele.</div>
            <?php elseif (empty($contracts)): ?>
                <div class="mt-4 text-sm text-slate-500">Nu exista contracte disponibile.</div>
            <?php else: ?>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Titlu</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Actiuni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $contract): ?>
                                <?php
                                    $statusKey = (string) ($contract['status'] ?? '');
                                    $statusLabel = $statusLabels[$statusKey] ?? $statusKey;
                                    $statusClass = $statusClasses[$statusKey] ?? 'bg-slate-100 text-slate-700';
                                    $hasGenerated = !empty($contract['generated_file_path']) || in_array($statusKey, ['generated', 'sent', 'signed_uploaded', 'approved'], true);
                                    $hasSigned = !empty($contract['signed_file_path']);
                                    $previewUrl = App\Support\Url::to('p/' . $token . '/preview?id=' . (int) $contract['id']);
                                    $downloadGenerated = App\Support\Url::to('p/' . $token . '/download?kind=generated&id=' . (int) $contract['id']);
                                    $downloadSigned = App\Support\Url::to('p/' . $token . '/download?kind=signed&id=' . (int) $contract['id']);
                                ?>
                                <tr class="border-t border-slate-100">
                                    <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contract['title'] ?? '')) ?></td>
                                    <td class="px-3 py-2 text-slate-600">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $statusClass ?>">
                                            <?= htmlspecialchars($statusLabel) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        <div class="flex flex-wrap gap-3 text-xs font-semibold">
                                            <a href="<?= htmlspecialchars($previewUrl) ?>" class="text-blue-700 hover:text-blue-800" target="_blank" rel="noopener">Previzualizeaza</a>
                                            <?php if ($hasGenerated): ?>
                                                <a href="<?= htmlspecialchars($downloadGenerated) ?>" class="text-blue-700 hover:text-blue-800">Descarca generat</a>
                                            <?php endif; ?>
                                            <?php if ($hasSigned): ?>
                                                <a href="<?= htmlspecialchars($downloadSigned) ?>" class="text-blue-700 hover:text-blue-800">Descarca semnat</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($partnerCui !== '' && !empty($contracts) && !empty($permissions['can_upload_signed'])): ?>
                <div class="mt-4 rounded border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Acceptat: PDF, JPG, JPEG, PNG. Dimensiune maxima 20MB.
                </div>
                <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/upload-signed') ?>" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-3">
                    <?= App\Support\Csrf::input() ?>
                    <select name="contract_id" class="rounded border border-slate-300 px-3 py-2 text-sm" required>
                        <option value="">Selecteaza contract</option>
                        <?php foreach ($contracts as $contract): ?>
                            <option value="<?= (int) $contract['id'] ?>">
                                <?= htmlspecialchars((string) ($contract['title'] ?? 'Contract')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required class="text-sm text-slate-600">
                    <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Incarca contract semnat
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-4 flex flex-wrap gap-2">
                <form method="POST" action="<?= App\Support\Url::to('p/' . $token . '/set-step') ?>">
                    <?= App\Support\Csrf::input() ?>
                    <input type="hidden" name="step" value="2">
                    <button class="rounded border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Inapoi la contacte
                    </button>
                </form>
            </div>
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
