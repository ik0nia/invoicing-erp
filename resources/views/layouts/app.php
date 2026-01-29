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
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'ERP Intern') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <div class="flex items-center gap-3">
                <?php if ($logoUrl): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo ERP" class="h-10 w-auto">
                <?php else: ?>
                    <span class="text-lg font-semibold text-blue-700">ERP Intern</span>
                <?php endif; ?>
                <span class="text-xs uppercase tracking-wide text-slate-500">Admin</span>
            </div>
            <nav class="flex items-center gap-4 text-sm">
                <?php if ($user): ?>
                    <a href="<?= App\Support\Url::to('admin/setari/branding') ?>" class="text-blue-700 hover:text-blue-800">
                        Setari branding
                    </a>
                    <form method="POST" action="<?= App\Support\Url::to('logout') ?>" class="inline">
                        <?= App\Support\Csrf::input() ?>
                        <button type="submit" class="text-slate-600 hover:text-slate-900">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="<?= App\Support\Url::to('login') ?>" class="text-blue-700 hover:text-blue-800">Autentificare</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-8">
        <?php include BASE_PATH . '/resources/views/partials/flash.php'; ?>
        <?= $content ?? '' ?>
    </main>
</body>
</html>
