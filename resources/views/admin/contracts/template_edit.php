<?php
    $title = 'Editare model contract';
    $template = $template ?? [];
    $variables = $variables ?? [];
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Editare model de contract</h1>
        <p class="mt-1 text-sm text-slate-500">Actualizeaza detaliile modelului.</p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/contract-templates') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi la lista
    </a>
</div>

<div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <div class="font-semibold">Variabile disponibile</div>
    <div class="mt-2 text-xs text-blue-700">
        Foloseste variabilele intre acolade duble, de exemplu: <strong>{{partner.name}}</strong>
    </div>
    <div class="mt-1 text-xs text-blue-700">
        Datele despre reprezentant si banca se completeaza din Companii/Inrolare.
    </div>
    <div class="mt-1 text-xs text-blue-700">
        Variabila <strong>{{contacts.table}}</strong> insereaza automat tabelul cu contactele companiei.
    </div>
    <div class="mt-1 text-xs text-blue-700">
        Pentru documente secundare, foloseste <strong>{{contract.reference_no}}</strong> si <strong>{{contract.reference_date}}</strong>.
    </div>
    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($variables as $item): ?>
            <div class="flex items-center justify-between gap-2 rounded border border-blue-100 bg-white px-2 py-1 text-xs text-blue-800">
                <span class="font-mono">{{<?= htmlspecialchars((string) $item['key']) ?>}}</span>
                <button
                    type="button"
                    class="rounded border border-blue-200 px-2 py-0.5 text-[11px] text-blue-700 hover:bg-blue-50"
                    data-copy="{{<?= htmlspecialchars((string) $item['key']) ?>}}"
                >
                    Copiaza
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<form method="POST" action="<?= App\Support\Url::to('admin/contract-templates/update') ?>" class="mt-4 rounded-xl border border-blue-100 bg-blue-50 p-6 shadow-sm ring-1 ring-blue-100">
    <?= App\Support\Csrf::input() ?>
    <input type="hidden" name="id" value="<?= (int) ($template['id'] ?? 0) ?>">
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-name">Nume</label>
            <input
                id="template-name"
                name="name"
                type="text"
                value="<?= htmlspecialchars((string) ($template['name'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-doc-type">Doc type (indexare)</label>
            <input
                id="template-doc-type"
                name="doc_type"
                type="text"
                value="<?= htmlspecialchars((string) ($template['doc_type'] ?? $template['template_type'] ?? '')) ?>"
                placeholder="ex: client_agreement"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-kind">Categorie document</label>
            <select
                id="template-kind"
                name="doc_kind"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
                <?php $docKind = (string) ($template['doc_kind'] ?? 'contract'); ?>
                <option value="contract" <?= $docKind === 'contract' ? 'selected' : '' ?>>Contract</option>
                <option value="acord" <?= $docKind === 'acord' ? 'selected' : '' ?>>Acord</option>
                <option value="anexa" <?= $docKind === 'anexa' ? 'selected' : '' ?>>Anexa</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-applies">Se aplica la</label>
            <?php $appliesTo = (string) ($template['applies_to'] ?? 'both'); ?>
            <select
                id="template-applies"
                name="applies_to"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
                <option value="both" <?= $appliesTo === 'both' ? 'selected' : '' ?>>Ambele</option>
                <option value="supplier" <?= $appliesTo === 'supplier' ? 'selected' : '' ?>>Furnizor</option>
                <option value="client" <?= $appliesTo === 'client' ? 'selected' : '' ?>>Client</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template-priority">Prioritate</label>
            <input
                id="template-priority"
                name="priority"
                type="number"
                value="<?= (int) ($template['priority'] ?? 100) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>
    <div class="mt-4">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="auto_on_enrollment" class="rounded border-slate-300" <?= !empty($template['auto_on_enrollment']) ? 'checked' : '' ?>>
            Creeaza automat la inrolare
        </label>
        <label class="ml-6 inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="required_onboarding" class="rounded border-slate-300" <?= !empty($template['required_onboarding']) ? 'checked' : '' ?>>
            Obligatoriu la onboarding
        </label>
        <label class="ml-6 inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" class="rounded border-slate-300" <?= !empty($template['is_active']) ? 'checked' : '' ?>>
            Activ
        </label>
    </div>
    <div class="mt-4">
        <label class="block text-sm font-medium text-slate-700" for="template-html">HTML</label>
        <textarea
            id="template-html"
            name="html_content"
            rows="8"
            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
        ><?= htmlspecialchars((string) ($template['html_content'] ?? '')) ?></textarea>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Salveaza modificari
        </button>
        <a
            href="<?= App\Support\Url::to('admin/contract-templates/download-draft?id=' . (int) ($template['id'] ?? 0)) ?>"
            class="rounded border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100"
        >
            Descarca PDF draft
        </a>
    </div>
</form>

<?php
    $stampPath = trim((string) ($template['stamp_image_path'] ?? ''));
    $stampMetaRaw = (string) ($template['stamp_image_meta'] ?? '');
    $stampMeta = [];
    if ($stampMetaRaw !== '') {
        $decodedMeta = json_decode($stampMetaRaw, true);
        if (is_array($decodedMeta)) {
            $stampMeta = $decodedMeta;
        }
    }
    $stampOriginalName = (string) ($stampMeta['original_name'] ?? '');
    $stampUploadedAt = (string) ($stampMeta['uploaded_at'] ?? '');
    $stampPreviewUrl = $stampPath !== ''
        ? App\Support\Url::to('admin/contract-templates/stamp?id=' . (int) ($template['id'] ?? 0) . '&t=' . urlencode((string) ($template['updated_at'] ?? $stampUploadedAt ?? '')))
        : '';
?>

<div class="mt-4 rounded-xl border border-blue-100 bg-blue-50 p-6 shadow-sm ring-1 ring-blue-100">
    <div class="text-sm font-semibold text-slate-700">Stampila / Semnatura (optional)</div>
    <p class="mt-1 text-xs text-slate-500">
        Stampila este specifica acestui model. O poti insera in document cu variabila {{stamp.image}}.
    </p>

    <?php if ($stampPreviewUrl !== ''): ?>
        <div class="mt-4">
            <div class="text-xs font-medium text-slate-600">Imagine curenta</div>
            <img
                src="<?= htmlspecialchars($stampPreviewUrl) ?>"
                alt="Stampila model"
                class="mt-2 max-h-28 rounded border border-slate-200 bg-slate-50 p-1"
            >
            <?php if ($stampOriginalName !== '' || $stampUploadedAt !== ''): ?>
                <div class="mt-2 text-xs text-slate-500">
                    <?php if ($stampOriginalName !== ''): ?>
                        Fisier: <?= htmlspecialchars($stampOriginalName) ?>
                    <?php endif; ?>
                    <?php if ($stampUploadedAt !== ''): ?>
                        <span class="ml-2">Incarcat la: <?= htmlspecialchars($stampUploadedAt) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= App\Support\Url::to('admin/contract-templates/upload-stamp') ?>" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
        <?= App\Support\Csrf::input() ?>
        <input type="hidden" name="id" value="<?= (int) ($template['id'] ?? 0) ?>">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="stamp_image">Incarca imagine stampila</label>
            <input
                id="stamp_image"
                name="stamp_image"
                type="file"
                accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                class="mt-1 block rounded border border-slate-300 px-3 py-2 text-sm"
            >
            <p class="mt-1 text-xs text-slate-500">Formate acceptate: PNG, JPG, JPEG, WEBP. Maxim 5MB.</p>
        </div>
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
            Salveaza stampila
        </button>
    </form>

    <?php if ($stampPreviewUrl !== ''): ?>
        <form method="POST" action="<?= App\Support\Url::to('admin/contract-templates/remove-stamp') ?>" class="mt-3">
            <?= App\Support\Csrf::input() ?>
            <input type="hidden" name="id" value="<?= (int) ($template['id'] ?? 0) ?>">
            <button
                class="rounded border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                onclick="return confirm('Stergi stampila din acest model?')"
            >
                Sterge stampila
            </button>
        </form>
    <?php endif; ?>
</div>

<form method="POST" action="<?= App\Support\Url::to('admin/contract-templates/preview') ?>" class="mt-4 rounded-xl border border-blue-100 bg-blue-50 p-6 shadow-sm ring-1 ring-blue-100">
    <?= App\Support\Csrf::input() ?>
    <input type="hidden" name="id" value="<?= (int) ($template['id'] ?? 0) ?>">
    <div class="text-sm font-semibold text-slate-700">Previzualizare</div>
    <p class="mt-1 text-xs text-slate-500">Optional: completeaza un partener pentru test.</p>
    <div class="mt-3 grid gap-3 md:grid-cols-3">
        <input
            type="text"
            name="partner_cui"
            placeholder="CUI partener"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
        <input
            type="text"
            name="supplier_cui"
            placeholder="CUI furnizor (relatie)"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
        <input
            type="text"
            name="client_cui"
            placeholder="CUI client (relatie)"
            class="rounded border border-slate-300 px-3 py-2 text-sm"
        >
    </div>
    <div class="mt-4">
        <button class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Previzualizeaza
        </button>
    </div>
</form>

<script>
    (function () {
        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', () => {
                const value = button.getAttribute('data-copy') || '';
                if (!value) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value);
                } else {
                    const temp = document.createElement('textarea');
                    temp.value = value;
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand('copy');
                    document.body.removeChild(temp);
                }
            });
        });
    })();
</script>
