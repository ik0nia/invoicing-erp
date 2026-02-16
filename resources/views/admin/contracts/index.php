<?php
    use App\Support\Auth;

    $title = 'Contracte';
    $templates = $templates ?? [];
    $contracts = $contracts ?? [];
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

<div class="mt-4 rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
    <div class="font-semibold">Flux contracte</div>
    <ol class="mt-2 list-decimal space-y-1 pl-5">
        <li>Contract in <strong>Ciorna</strong></li>
        <li>Genereaza PDF contract</li>
        <li>Trimite catre semnare</li>
        <li>Incarca semnat</li>
        <li>Aprobare (staff intern: super_admin/admin/contabil/operator)</li>
    </ol>
    <div class="mt-2 text-xs text-blue-700">
        Statusuri: Ciorna, Generat, Trimis, Semnat (incarcat), Aprobat.
    </div>
    <div class="mt-2 text-xs text-blue-700">
        [1] Ciorna &rarr; [2] Generat &rarr; [3] Semnat &rarr; [4] Aprobat
    </div>
</div>

<?php if (!$pdfAvailable): ?>
    <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Generarea PDF nu este disponibila (wkhtmltopdf lipsa). Contractele pot fi salvate, dar download-ul PDF va fi indisponibil
        pana la configurarea utilitarului pe server.
    </div>
<?php endif; ?>

<form method="POST" action="<?= App\Support\Url::to('admin/contracts/generate') ?>" class="mt-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
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

<form method="POST" action="<?= App\Support\Url::to('admin/contracts/upload-signed') ?>" enctype="multipart/form-data" class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <?= App\Support\Csrf::input() ?>
    <div class="flex flex-wrap items-center gap-3">
        <select name="contract_id" class="rounded border border-slate-300 px-3 py-2 text-sm" required>
            <option value="">Selecteaza contract</option>
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
                    <?= htmlspecialchars((string) ($contract['title'] ?? '')) ?>
                    <?= $optionDocNo !== '' ? ' [' . htmlspecialchars($optionDocNo) . ']' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="file" name="file" required class="text-sm text-slate-600">
        <button class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Incarca contract semnat
        </button>
    </div>
</form>

<div class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="px-3 py-2">Titlu</th>
                <th class="px-3 py-2">Doc type</th>
                <th class="px-3 py-2">Nr. registru</th>
                <th class="px-3 py-2">Data contract</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Relatie</th>
                <th class="px-3 py-2">Descarcare</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contracts)): ?>
                <tr>
                    <td colspan="8" class="px-3 py-4 text-sm text-slate-500">
                        Nu exista contracte inca. Dupa confirmarea inrolarii, contractele vor aparea automat aici.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($contracts as $contract): ?>
                    <?php
                        $downloadUrl = App\Support\Url::to('admin/contracts/download?id=' . (int) $contract['id']);
                        $relation = '';
                        if (!empty($contract['supplier_cui']) || !empty($contract['client_cui'])) {
                            $relation = trim((string) ($contract['supplier_cui'] ?? '')) . ' / ' . trim((string) ($contract['client_cui'] ?? ''));
                        }
                        $statusKey = (string) ($contract['status'] ?? '');
                        $statusLabel = $statusLabels[$statusKey] ?? $statusKey;
                        $statusClass = $statusClasses[$statusKey] ?? 'bg-slate-100 text-slate-700';
                        $downloadReady = !empty($contract['signed_upload_path'])
                            || !empty($contract['signed_file_path'])
                            || !empty($contract['generated_pdf_path'])
                            || $pdfAvailable;
                        $docNoDisplay = trim((string) ($contract['doc_full_no'] ?? ''));
                        if ($docNoDisplay === '') {
                            $docNo = (int) ($contract['doc_no'] ?? 0);
                            if ($docNo > 0) {
                                $series = trim((string) ($contract['doc_series'] ?? ''));
                                $docNoPadded = str_pad((string) $docNo, 6, '0', STR_PAD_LEFT);
                                $docNoDisplay = $series !== '' ? ($series . '-' . $docNoPadded) : $docNoPadded;
                            }
                        }
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2 text-slate-700"><?= htmlspecialchars((string) ($contract['title'] ?? '')) ?></td>
                        <td class="px-3 py-2 text-slate-600 font-mono"><?= htmlspecialchars((string) ($contract['doc_type'] ?? 'contract')) ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <?php if ($docNoDisplay !== ''): ?>
                                <span class="font-mono"><?= htmlspecialchars($docNoDisplay) ?></span>
                            <?php else: ?>
                                <span class="text-amber-700">Fara numar</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars((string) ($contract['contract_date'] ?? '—')) ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $statusClass ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($relation !== '' ? $relation : '—') ?></td>
                        <td class="px-3 py-2 text-slate-600">
                            <?php if ($downloadReady): ?>
                                <a href="<?= htmlspecialchars($downloadUrl) ?>" class="text-xs font-semibold text-blue-700 hover:text-blue-800">Descarca</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
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
