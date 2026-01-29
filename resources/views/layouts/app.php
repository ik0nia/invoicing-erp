<?php

use App\Domain\Settings\Services\SettingsService;
use App\Support\Auth;

$settings = new SettingsService();
$brandingLogo = $settings->get('branding.logo_path');
$logoUrl = null;

if ($brandingLogo) {
    $absolutePath = BASE_PATH . '/' . ltrim($brandingLogo, '/');

    if (file_exists($absolutePath)) {
        $logoUrl = App\Support\Url::asset($brandingLogo);
    }
}
$user = Auth::user();
?>
<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$base = App\Support\Url::base();
if ($base !== '' && str_starts_with($currentPath, $base)) {
    $currentPath = substr($currentPath, strlen($base));
    $currentPath = $currentPath === '' ? '/' : $currentPath;
}

$menu = [
    [
        'label' => 'Dashboard',
        'path' => '/admin/dashboard',
        'active' => str_starts_with($currentPath, '/admin/dashboard'),
    ],
    [
        'label' => 'Facturi intrare',
        'path' => '/admin/facturi',
        'active' => str_starts_with($currentPath, '/admin/facturi'),
    ],
    [
        'label' => 'Setari branding',
        'path' => '/admin/setari/branding',
        'active' => str_starts_with($currentPath, '/admin/setari/branding'),
    ],
    [
        'label' => 'Import date vechi',
        'path' => '/admin/setari/import-date',
        'active' => str_starts_with($currentPath, '/admin/setari/import-date'),
    ],
];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'ERP Intern') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .text-slate-400 { color: #475569 !important; }
        .text-slate-500 { color: #475569 !important; }
        .text-slate-600 { color: #334155 !important; }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="flex min-h-screen">
        <aside class="w-64 border-r border-slate-200 bg-white">
            <div class="flex items-center gap-3 border-b border-slate-200 px-6 py-5">
                <?php if ($logoUrl): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo ERP" class="h-9 w-auto">
                <?php else: ?>
                    <span class="text-lg font-semibold text-blue-700">ERP Intern</span>
                <?php endif; ?>
            </div>
            <nav class="px-4 py-6 text-sm">
                <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Meniu</div>
                <ul class="space-y-2">
                    <?php foreach ($menu as $item): ?>
                        <li>
                            <a
                                href="<?= App\Support\Url::to($item['path']) ?>"
                                class="flex items-center gap-2 rounded px-3 py-2 font-medium <?= $item['active'] ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>"
                            >
                                <?= htmlspecialchars($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li class="rounded px-3 py-2 text-slate-400">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">In curand</span>
                        <div class="mt-2 space-y-1">
                            <div class="text-sm">Companii</div>
                            <div class="text-sm">Utilizatori</div>
                            <div class="text-sm">Facturi</div>
                        </div>
                    </li>
                </ul>
            </nav>
        </aside>

        <div class="flex flex-1 flex-col">
            <header class="border-b border-slate-200 bg-white">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <div class="text-sm text-slate-500">Admin</div>
                        <div class="text-base font-semibold text-slate-900">
                            <?= htmlspecialchars($user?->name ?? 'Administrator') ?>
                        </div>
                    </div>
                    <?php if ($user): ?>
                        <form method="POST" action="<?= App\Support\Url::to('logout') ?>">
                            <?= App\Support\Csrf::input() ?>
                            <button type="submit" class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900">
                                Logout
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </header>

            <main class="flex-1 px-6 py-8">
                <?php include BASE_PATH . '/resources/views/partials/flash.php'; ?>
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>
</body>
</html>
