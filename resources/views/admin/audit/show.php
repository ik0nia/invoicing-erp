<?php
    $title = 'Audit Log';
    $row = $row ?? [];
    $contextPretty = $context_pretty ?? '';
    $backUrl = $back_url ?? App\Support\Url::to('admin/audit');
    $actorId = $row['actor_user_id'] ?? null;
    $actorRole = trim((string) ($row['actor_role'] ?? ''));
    $actorLabel = $actorId !== null ? ('#' . (int) $actorId) : '—';
    if ($actorRole !== '') {
        $actorLabel .= ' (' . $actorRole . ')';
    }
?>

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Audit Log</h1>
        <p class="mt-1 text-sm text-slate-500">Detalii inregistrare audit.</p>
    </div>
    <a
        href="<?= htmlspecialchars($backUrl) ?>"
        class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900"
    >
        Inapoi la lista
    </a>
</div>

<?php if (!empty($schema_error ?? '')): ?>
    <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
        <?= htmlspecialchars((string) $schema_error) ?>
    </div>
<?php endif; ?>

<div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <div class="text-xs uppercase text-slate-400">Data</div>
            <div class="text-sm font-semibold text-slate-700">
                <?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?>
            </div>
        </div>
        <div>
            <div class="text-xs uppercase text-slate-400">Actor</div>
            <div class="text-sm font-semibold text-slate-700">
                <?= htmlspecialchars($actorLabel) ?>
            </div>
        </div>
        <div>
            <div class="text-xs uppercase text-slate-400">Actiune</div>
            <div class="text-sm font-semibold text-slate-700">
                <?= htmlspecialchars((string) ($row['action'] ?? '')) ?>
            </div>
        </div>
        <div>
            <div class="text-xs uppercase text-slate-400">Entity</div>
            <div class="text-sm font-semibold text-slate-700">
                <?= htmlspecialchars((string) ($row['entity_type'] ?? '')) ?>
                <?= $row['entity_id'] !== null ? ('#' . htmlspecialchars((string) $row['entity_id'])) : '' ?>
            </div>
        </div>
        <div>
            <div class="text-xs uppercase text-slate-400">IP</div>
            <div class="text-sm font-semibold text-slate-700">
                <?= htmlspecialchars((string) ($row['ip'] ?? '—')) ?>
            </div>
        </div>
        <div>
            <div class="text-xs uppercase text-slate-400">User agent</div>
            <div class="text-sm font-semibold text-slate-700 break-words">
                <?= htmlspecialchars((string) ($row['user_agent'] ?? '—')) ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <div class="text-sm font-semibold text-slate-700">Context JSON</div>
    <pre class="mt-3 max-h-[480px] overflow-auto rounded border border-slate-200 bg-slate-50 p-4 text-xs text-slate-700"><?= htmlspecialchars($contextPretty !== '' ? $contextPretty : '—') ?></pre>
</div>
