<?php
    $title = 'Anexa furnizor';
    $templates = $templates ?? [];
    $preset = is_array($preset ?? null) ? $preset : [];
    $form = is_array($form ?? null) ? $form : [];
    $errorMessage = trim((string) ($errorMessage ?? ''));
    $previewHtml = $previewHtml ?? null;

    $signatureConfigured = !empty($preset['signature_configured']);
    $initialEditorHtml = (string) ($form['annex_content_html'] ?? '<p></p>');
    $supplierValue = (string) ($form['supplier_cui'] ?? '');
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
    <div class="mt-1 text-xs">
        Butonul <strong>Genereaza document</strong> aloca numar din registrul de furnizori si salveaza documentul in contracte.
        <strong>Genereaza PDF rapid</strong> descarca direct PDF fara inregistrare.
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
            <div
                class="relative"
                data-supplier-picker
                data-search-url="<?= App\Support\Url::to('admin/contracts/company-search') ?>"
                data-search-role="supplier"
            >
                <label class="block text-sm font-medium text-slate-700" for="supplier-cui-display">Furnizor</label>
                <input
                    id="supplier-cui-display"
                    type="text"
                    autocomplete="off"
                    class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Cauta dupa denumire sau CUI"
                    value="<?= htmlspecialchars($supplierValue) ?>"
                    data-supplier-display
                    required
                >
                <input
                    type="hidden"
                    id="supplier_cui"
                    name="supplier_cui"
                    value="<?= htmlspecialchars($supplierValue) ?>"
                    data-supplier-value
                >
                <div class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-slate-300 bg-white p-1 shadow-xl ring-1 ring-slate-200 divide-y divide-slate-100" data-supplier-list></div>
            </div>
        </div>
    </div>

    <div class="rounded border border-slate-200 bg-slate-50 p-4">
        <div class="text-sm font-semibold text-slate-700">Continut anexa (editor simplu)</div>
        <p class="mt-1 text-xs text-slate-600">
            Formatare permisa: paragraf, heading (H2/H3), liste, bold/italic. Fara schimbari de font.
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" data-block="p" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Paragraf</button>
            <button type="button" data-block="h2" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">H2</button>
            <button type="button" data-block="h3" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">H3</button>
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
            formaction="<?= App\Support\Url::to('admin/anexe-furnizor/generate-document') ?>"
            class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
        >
            Genereaza document
        </button>
        <button
            type="submit"
            formaction="<?= App\Support\Url::to('admin/anexe-furnizor/download') ?>"
            class="rounded border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
        >
            Genereaza PDF rapid
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

<style>
    #annex-editor h2 {
        font-size: 1.35rem;
        line-height: 1.25;
        font-weight: 700;
        margin: 0 0 0.55rem;
        color: #0f172a;
    }
    #annex-editor h3 {
        font-size: 1.1rem;
        line-height: 1.3;
        font-weight: 700;
        margin: 0 0 0.5rem;
        color: #1e293b;
    }
    #annex-editor p {
        margin: 0 0 0.5rem;
        line-height: 1.5;
    }
    #annex-editor ul,
    #annex-editor ol {
        margin: 0 0 0.5rem 1.15rem;
        padding: 0;
    }
    #annex-editor li { margin: 0 0 0.25rem; }
    #annex-editor strong { font-weight: 700; }
    #annex-editor em { font-style: italic; }
</style>

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
            if (raw === '') return '';
            if (/^\d+$/.test(raw)) return raw;
            const compact = raw.replace(/\s+/g, '').toUpperCase();
            if (/^RO\d+$/.test(compact)) return compact.replace(/^RO/, '');
            const suffix = raw.match(/(?:^|[-\s])(RO)?(\d{2,16})$/i);
            if (suffix && suffix[2]) return normalizeDigits(suffix[2]);
            return '';
        };

        const form = document.getElementById('supplier-annex-form');
        const editor = document.getElementById('annex-editor');
        const hidden = document.getElementById('annex_content_html');
        const picker = document.querySelector('[data-supplier-picker]');
        const supplierDisplay = picker ? picker.querySelector('[data-supplier-display]') : null;
        const supplierValue = picker ? picker.querySelector('[data-supplier-value]') : null;
        const supplierList = picker ? picker.querySelector('[data-supplier-list]') : null;
        const supplierSearchUrl = picker ? (picker.getAttribute('data-search-url') || '') : '';
        const supplierSearchRole = picker ? (picker.getAttribute('data-search-role') || 'supplier') : 'supplier';
        if (!form || !editor || !hidden || !picker || !supplierDisplay || !supplierValue || !supplierList || !supplierSearchUrl) {
            return;
        }

        const syncEditor = () => {
            hidden.value = editor.innerHTML;
        };

        let supplierRequestId = 0;
        let supplierTimer = null;
        const clearSupplierList = () => {
            supplierList.innerHTML = '';
            supplierList.classList.add('hidden');
        };
        const syncSupplierValidity = () => {
            const hasValue = supplierValue.value.trim() !== '' || extractCompanyCuiCandidate(supplierDisplay.value) !== '';
            supplierDisplay.setCustomValidity(hasValue ? '' : 'Selecteaza furnizorul.');
        };
        const applySupplierSelection = (item) => {
            const cui = normalizeDigits(item.cui || '');
            const name = String(item.name || '').trim();
            const label = name !== '' && name !== cui ? `${name} - ${cui}` : cui;
            supplierValue.value = cui;
            supplierDisplay.value = label;
            clearSupplierList();
            syncSupplierValidity();
        };
        const renderSupplierItems = (items) => {
            if (!Array.isArray(items) || items.length === 0) {
                supplierList.innerHTML = '<div class="px-3 py-2 text-xs text-slate-500">Nu exista rezultate.</div>';
                supplierList.classList.remove('hidden');
                return;
            }
            supplierList.innerHTML = items.map((item) => {
                const cui = escapeHtml(item.cui || '');
                const name = escapeHtml(item.name || item.cui || '');
                const label = escapeHtml(item.label || `${item.name || item.cui || ''} - ${item.cui || ''}`);
                return `
                    <button
                        type="button"
                        class="block w-full rounded px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700"
                        data-supplier-item
                        data-cui="${cui}"
                        data-name="${name}"
                        data-label="${label}"
                    >
                        <div class="font-medium text-slate-900">${label}</div>
                    </button>
                `;
            }).join('');
            supplierList.classList.remove('hidden');
        };
        const fetchSupplierItems = (term) => {
            const currentRequestId = ++supplierRequestId;
            const url = new URL(supplierSearchUrl, window.location.origin);
            url.searchParams.set('term', term);
            url.searchParams.set('limit', '20');
            url.searchParams.set('role', supplierSearchRole);
            fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.json())
                .then((data) => {
                    if (currentRequestId !== supplierRequestId) return;
                    if (!data || data.success !== true) {
                        renderSupplierItems([]);
                        return;
                    }
                    renderSupplierItems(data.items || []);
                })
                .catch(() => {
                    if (currentRequestId !== supplierRequestId) return;
                    renderSupplierItems([]);
                });
        };

        form.addEventListener('submit', (event) => {
            syncEditor();
            if (supplierValue.value.trim() === '') {
                const maybeCui = extractCompanyCuiCandidate(supplierDisplay.value);
                if (maybeCui !== '') {
                    supplierValue.value = maybeCui;
                    supplierDisplay.value = maybeCui;
                }
            }
            syncSupplierValidity();
            if (supplierValue.value.trim() === '') {
                event.preventDefault();
                supplierDisplay.reportValidity();
            }
        });

        supplierDisplay.addEventListener('focus', () => {
            fetchSupplierItems(supplierDisplay.value.trim());
        });
        supplierDisplay.addEventListener('input', () => {
            supplierValue.value = '';
            syncSupplierValidity();
            const query = supplierDisplay.value.trim();
            if (supplierTimer) clearTimeout(supplierTimer);
            supplierTimer = setTimeout(() => {
                fetchSupplierItems(query);
            }, 200);
        });
        supplierDisplay.addEventListener('change', () => {
            if (supplierValue.value.trim() !== '') {
                syncSupplierValidity();
                return;
            }
            const maybeCui = extractCompanyCuiCandidate(supplierDisplay.value);
            if (maybeCui !== '') {
                supplierValue.value = maybeCui;
                supplierDisplay.value = maybeCui;
            }
            syncSupplierValidity();
        });
        supplierDisplay.addEventListener('blur', () => {
            window.setTimeout(() => {
                clearSupplierList();
                if (supplierValue.value.trim() !== '') {
                    syncSupplierValidity();
                    return;
                }
                const maybeCui = extractCompanyCuiCandidate(supplierDisplay.value);
                if (maybeCui !== '') {
                    supplierValue.value = maybeCui;
                    supplierDisplay.value = maybeCui;
                }
                syncSupplierValidity();
            }, 140);
        });

        const handleSupplierSelect = (event) => {
            if (event.type === 'mousedown' && typeof window.PointerEvent !== 'undefined') {
                return;
            }
            const target = event.target.closest('[data-supplier-item]');
            if (!target) return;
            event.preventDefault();
            applySupplierSelection({
                cui: target.getAttribute('data-cui') || '',
                name: target.getAttribute('data-name') || '',
                label: target.getAttribute('data-label') || '',
            });
        };
        supplierList.addEventListener('pointerdown', handleSupplierSelect);
        supplierList.addEventListener('mousedown', handleSupplierSelect);

        document.querySelectorAll('[data-block]').forEach((button) => {
            button.addEventListener('click', () => {
                const block = button.getAttribute('data-block');
                if (!block || !document.execCommand) {
                    return;
                }
                editor.focus();
                const fallback = String(block).toLowerCase().replace(/[^a-z0-9]/g, '');
                const primary = `<${fallback}>`;
                document.execCommand('formatBlock', false, primary);
                document.execCommand('formatBlock', false, fallback);
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
        editor.addEventListener('input', syncEditor);

        syncSupplierValidity();
    })();
</script>
