<?php
    $title = 'Manual';
    $version = $version ?? '';
    $releases = $releases ?? [];
?>

<div class="mx-auto w-full max-w-5xl">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Manual</h1>
            <p class="mt-1 text-sm text-slate-600">
                Istoric versiuni si schimbari pe aplicatie.
            </p>
        </div>
        <?php if ($version !== ''): ?>
            <div class="rounded-full bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700">
                Versiune curenta: <?= htmlspecialchars($version) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-6 grid gap-4">
        <?php if (empty($releases)): ?>
            <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
                Nu exista inca istoric de versiuni.
            </div>
        <?php else: ?>
            <?php foreach ($releases as $release): ?>
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-900">
                            Versiunea <?= htmlspecialchars($release['version'] ?? '') ?>
                        </h2>
                        <?php if (!empty($release['items'])): ?>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                <?= count($release['items']) ?> schimbari
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($release['items'])): ?>
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                            <?php foreach ($release['items'] as $item): ?>
                                <li><?= htmlspecialchars((string) $item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="mt-3 text-sm text-slate-500">Detalii indisponibile pentru aceasta versiune.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
