<?php
    $title = 'Anexa furnizor';
    $templates = $templates ?? [];
    $preset = is_array($preset ?? null) ? $preset : [];
    $form = is_array($form ?? null) ? $form : [];
    $errorMessage = trim((string) ($errorMessage ?? ''));
    $previewHtml = $previewHtml ?? null;

    $signatureConfigured = !empty($preset['signature_configured']);
    $initialEditorHtml = (string) ($form['annex_content_html'] ?? '<p></p>');
?>

<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Generator Anexa furnizor</h1>
        <p class="mt-1 text-sm text-slate-600">
            Generator separat, fara impact pe fluxurile existente de contracte/facturi.
        </p>
    </div>
    <a
        href="<?= App\Support\Url::to('admin/setari') ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Setari anexa
    </a>
</div>

<div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <div class="font-semibold">Template + parametri simpli</div>
    <div class="mt-1 text-xs">
        In template poti folosi: <code>{{annex.title}}</code>, <code>{{annex.content}}</code>, <code>{{annex.signature}}</code>.
        Semnatura preset din Setari: <strong><?= $signatureConfigured ? 'configurata' : 'neconfigurata' ?></strong>.
    </div>
    <div class="mt-1 text-xs">
        Sunt listate doar template-urile anexa pentru furnizor care NU sunt automate la inrolare si NU sunt obligatorii la onboarding.
    </div>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="mt-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <?= htmlspecialchars($errorMessage) ?>
    </div>
<?php endif; ?>

<form id="supplier-annex-form" method="POST" class="mt-4 space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <?= App\Support\Csrf::input() ?>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-slate-700" for="template_id">Template anexa</label>
            <select
                id="template_id"
                name="template_id"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
                <?php if (empty($templates)): ?>
                    <option value="">Nu exista template-uri potrivite (anexa furnizor, ne-automat, ne-obligatoriu)</option>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <?php
                            $templateId = (int) ($template['id'] ?? 0);
                            $selected = $templateId === (int) ($form['template_id'] ?? 0);
                            $templateName = trim((string) ($template['name'] ?? 'Template anexa'));
                            $templatePriority = (int) ($template['priority'] ?? 100);
                        ?>
                        <option value="<?= $templateId ?>" <?= $selected ? 'selected' : '' ?>>
                            <?= htmlspecialchars($templateName) ?> (prioritate <?= $templatePriority ?>)
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="annex_title">Denumire anexa</label>
            <input
                id="annex_title"
                name="annex_title"
                type="text"
                value="<?= htmlspecialchars((string) ($form['annex_title'] ?? '')) ?>"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="supplier_cui">Furnizor CUI</label>
            <input
                id="supplier_cui"
                name="supplier_cui"
                type="text"
                inputmode="numeric"
                value="<?= htmlspecialchars((string) ($form['supplier_cui'] ?? '')) ?>"
                placeholder="ex: 12345678"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700" for="client_cui">Client CUI (optional)</label>
            <input
                id="client_cui"
                name="client_cui"
                type="text"
                inputmode="numeric"
                value="<?= htmlspecialchars((string) ($form['client_cui'] ?? '')) ?>"
                placeholder="ex: 87654321"
                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>

    <div class="rounded border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm font-semibold text-slate-700">Continut anexa (editor simplu)</div>
        <p class="mt-1 text-xs text-slate-600">
            Formatare permisa: paragraf, heading (H2/H3), liste, bold/italic. Fara schimbari de font.
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" data-block="P" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Paragraf</button>
            <button type="button" data-block="H2" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">H2</button>
            <button type="button" data-block="H3" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">H3</button>
            <button type="button" data-command="insertUnorderedList" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Lista puncte</button>
            <button type="button" data-command="insertOrderedList" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Lista numerotata</button>
            <button type="button" data-command="bold" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Bold</button>
            <button type="button" data-command="italic" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Italic</button>
        </div>
        <div
            id="annex-editor"
            contenteditable="true"
            class="mt-3 min-h-[220px] rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
        ><?= $initialEditorHtml ?></div>
        <input type="hidden" name="annex_content_html" id="annex_content_html" value="<?= htmlspecialchars($initialEditorHtml) ?>">
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button
            type="submit"
            formaction="<?= App\Support\Url::to('admin/anexe-furnizor/preview') ?>"
            class="rounded border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
        >
            Previzualizeaza
        </button>
        <button
            type="submit"
            formaction="<?= App\Support\Url::to('admin/anexe-furnizor/download') ?>"
            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Genereaza PDF
        </button>
    </div>
</form>

<?php if (is_string($previewHtml) && $previewHtml !== ''): ?>
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-sm font-semibold text-slate-700">Previzualizare anexa</div>
        <iframe
            title="Previzualizare anexa furnizor"
            sandbox=""
            srcdoc="<?= htmlspecialchars($previewHtml) ?>"
            class="mt-3 h-[700px] w-full rounded border border-slate-200"
        ></iframe>
    </div>
<?php endif; ?>

<script>
    (function () {
        const form = document.getElementById('supplier-annex-form');
        const editor = document.getElementById('annex-editor');
        const hidden = document.getElementById('annex_content_html');
        if (!form || !editor || !hidden) {
            return;
        }

        const syncEditor = () => {
            hidden.value = editor.innerHTML;
        };

        form.addEventListener('submit', syncEditor);

        document.querySelectorAll('[data-block]').forEach((button) => {
            button.addEventListener('click', () => {
                const block = button.getAttribute('data-block');
                if (!block || !document.execCommand) {
                    return;
                }
                editor.focus();
                document.execCommand('formatBlock', false, block);
                syncEditor();
            });
        });

        document.querySelectorAll('[data-command]').forEach((button) => {
            button.addEventListener('click', () => {
                const command = button.getAttribute('data-command');
                if (!command || !document.execCommand) {
                    return;
                }
                editor.focus();
                document.execCommand(command, false, null);
                syncEditor();
            });
        });

        editor.addEventListener('paste', (event) => {
            event.preventDefault();
            const text = (event.clipboardData || window.clipboardData).getData('text');
            if (!text) {
                return;
            }
            if (document.execCommand) {
                document.execCommand('insertText', false, text);
            }
            syncEditor();
        });
    })();
</script>
