<?php
    $settings = new App\Domain\Settings\Services\SettingsService();
    $brandingLogo = (string) $settings->get('branding.logo_path', '');
    $brandingLogoDark = (string) $settings->get('branding.logo_dark_path', '');
    $resolveLogoUrl = static function (string $path): ?string {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        $absolutePath = BASE_PATH . '/' . ltrim($path, '/');
        if (!file_exists($absolutePath)) {
            return null;
        }

        return App\Support\Url::asset($path);
    };
    $logoUrl = $resolveLogoUrl($brandingLogo);
    $logoDarkUrl = $resolveLogoUrl($brandingLogoDark);
    $hasDualLogos = $logoUrl !== null && $logoDarkUrl !== null && $logoUrl !== $logoDarkUrl;
$guestContainerClass = trim((string) ($guestContainerClass ?? 'max-w-5xl'));
if ($guestContainerClass === '') {
    $guestContainerClass = 'max-w-5xl';
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'ERP Intern') ?></title>
    <script>
        window.tailwind = window.tailwind || {};
        window.tailwind.config = {
            darkMode: 'class',
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        (function () {
            try {
                if (localStorage.getItem('dark-mode') === '1') {
                    document.documentElement.classList.add('dark-mode', 'dark');
                }
            } catch (e) {}
        })();
    </script>
    <style>
        .text-slate-400 { color: #475569 !important; }
        .text-slate-500 { color: #475569 !important; }
        .text-slate-600 { color: #334155 !important; }
        .dark-mode body { background-color: #0f172a !important; color: #e2e8f0 !important; }
        .dark-mode .bg-white { background-color: #0b1220 !important; }
        .dark-mode .bg-slate-50 { background-color: #0f172a !important; }
        .dark-mode .bg-slate-100 { background-color: #111827 !important; }
        .dark-mode .bg-slate-200 { background-color: #1f2937 !important; }
        .dark-mode .bg-blue-50 { background-color: #0b1f33 !important; }
        .dark-mode .bg-slate-50\/50 { background-color: #0f172a !important; }
        .dark-mode .bg-slate-50\/70 { background-color: #0f172a !important; }
        .dark-mode .bg-blue-50\/50 { background-color: #0b1f33 !important; }
        .dark-mode .bg-amber-50 { background-color: #2a1f0b !important; }
        .dark-mode .bg-amber-100 { background-color: #3a2a0f !important; }
        .dark-mode .bg-yellow-50 { background-color: #2a240b !important; }
        .dark-mode .bg-yellow-100 { background-color: #3a320f !important; }
        .dark-mode .border-slate-200 { border-color: #1f2937 !important; }
        .dark-mode .border-slate-300 { border-color: #374151 !important; }
        .dark-mode .border-slate-100 { border-color: #1f2937 !important; }
        .dark-mode .border-blue-200 { border-color: #1e3a8a !important; }
        .dark-mode .border-amber-200 { border-color: #78350f !important; }
        .dark-mode .border-amber-300 { border-color: #92400e !important; }
        .dark-mode .text-slate-900 { color: #f8fafc !important; }
        .dark-mode .text-slate-800 { color: #e2e8f0 !important; }
        .dark-mode .text-slate-700 { color: #cbd5f5 !important; }
        .dark-mode .text-slate-600 { color: #94a3b8 !important; }
        .dark-mode .text-slate-500 { color: #64748b !important; }
        .dark-mode .text-blue-900 { color: #bfdbfe !important; }
        .dark-mode .text-blue-800 { color: #93c5fd !important; }
        .dark-mode .text-blue-700 { color: #93c5fd !important; }
        .dark-mode .text-amber-900 { color: #fde68a !important; }
        .dark-mode .text-amber-800 { color: #fcd34d !important; }
        .dark-mode .text-amber-700 { color: #fbbf24 !important; }
        .dark-mode .hover\:bg-slate-50:hover { background-color: #1f2937 !important; }
        .dark-mode input[type="text"],
        .dark-mode input[type="number"],
        .dark-mode input[type="date"],
        .dark-mode input[type="email"],
        .dark-mode input[type="password"],
        .dark-mode input[type="search"],
        .dark-mode input[type="file"],
        .dark-mode select,
        .dark-mode textarea {
            background-color: #0f172a !important;
            color: #e2e8f0 !important;
            border-color: #334155 !important;
        }
        .dark-mode input::placeholder,
        .dark-mode textarea::placeholder {
            color: #94a3b8 !important;
        }
        .dark-mode option {
            background-color: #0f172a !important;
            color: #e2e8f0 !important;
        }
        .dark-mode .dark-toggle-track { background-color: #1f2937 !important; }
        .dark-mode .dark-toggle-knob { background-color: #e2e8f0 !important; }
        .brand-logo-dark { display: none; }
        .dark-mode .brand-logo-light { display: none !important; }
        .dark-mode .brand-logo-dark { display: block !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex <?= htmlspecialchars($guestContainerClass) ?> items-center justify-between px-6 py-4">
            <a href="<?= App\Support\Url::to('/') ?>" class="inline-flex items-center gap-3">
                <?php if ($hasDualLogos): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo ERP" class="brand-logo-light h-9 w-auto">
                    <img src="<?= htmlspecialchars($logoDarkUrl ?? '') ?>" alt="Logo ERP dark mode" class="brand-logo-dark h-9 w-auto">
                <?php elseif ($logoUrl): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo ERP" class="h-9 w-auto">
                <?php elseif ($logoDarkUrl): ?>
                    <img src="<?= htmlspecialchars($logoDarkUrl) ?>" alt="Logo ERP" class="h-9 w-auto">
                <?php else: ?>
                    <div class="text-lg font-semibold text-blue-700">ERP Intern</div>
                <?php endif; ?>
            </a>
            <label class="relative inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" id="dark-mode-toggle" class="peer sr-only">
                <span class="dark-toggle-track relative inline-flex h-6 w-11 items-center rounded-full bg-slate-200 transition-colors peer-checked:bg-blue-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300">
                    <span class="dark-toggle-knob inline-block h-4 w-4 translate-x-1 rounded-full bg-white transition-transform peer-checked:translate-x-6"></span>
                </span>
                <span>Dark mode</span>
            </label>
        </div>
    </header>

    <main class="mx-auto <?= htmlspecialchars($guestContainerClass) ?> px-6 py-8">
        <?php include BASE_PATH . '/resources/views/partials/flash.php'; ?>
        <?= $content ?? '' ?>
    </main>
    <script>
        (function () {
            const darkToggle = document.getElementById('dark-mode-toggle');
            if (!darkToggle) {
                return;
            }
            const setDark = (enabled) => {
                document.documentElement.classList.toggle('dark-mode', enabled);
                document.documentElement.classList.toggle('dark', enabled);
                try {
                    localStorage.setItem('dark-mode', enabled ? '1' : '0');
                } catch (e) {}
            };
            darkToggle.checked = document.documentElement.classList.contains('dark-mode');
            darkToggle.addEventListener('change', () => setDark(darkToggle.checked));
        })();
    </script>
</body>
</html>
