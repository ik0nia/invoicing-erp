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
$isSuperAdmin = $user?->isSuperAdmin() ?? false;
$isPlatformUser = $user?->isPlatformUser() ?? false;
$isSupplierUser = $user?->isSupplierUser() ?? false;
$userFirstName = '';
if ($user && !empty($user->name)) {
    $parts = preg_split('/\s+/', trim((string) $user->name));
    $userFirstName = $parts[0] ?? '';
}
?>
<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$base = App\Support\Url::base();
if ($base !== '' && str_starts_with($currentPath, $base)) {
    $currentPath = substr($currentPath, strlen($base));
    $currentPath = $currentPath === '' ? '/' : $currentPath;
}

$menuSections = [];

if ($isPlatformUser || $isSupplierUser) {
    $menuSections['General'] = [
        [
            'label' => 'Dashboard',
            'path' => '/admin/dashboard',
            'active' => str_starts_with($currentPath, '/admin/dashboard'),
        ],
    ];
}

$menuSections['Facturare'] = [
    [
            'label' => 'Facturare',
        'path' => '/admin/facturi',
        'active' => str_starts_with($currentPath, '/admin/facturi'),
    ],
];

if ($isPlatformUser) {
    $menuSections['Facturare'][] = [
        'label' => 'Pachete confirmate',
        'path' => '/admin/pachete-confirmate',
        'active' => str_starts_with($currentPath, '/admin/pachete-confirmate'),
    ];
}

if ($isPlatformUser) {
    $menuSections['Facturare'][] = [
        'label' => 'Incasari clienti',
        'path' => '/admin/incasari',
        'active' => str_starts_with($currentPath, '/admin/incasari'),
    ];
    $menuSections['Facturare'][] = [
        'label' => 'Plati furnizori',
        'path' => '/admin/plati',
        'active' => str_starts_with($currentPath, '/admin/plati'),
    ];

    $menuSections['Companii'] = [
        [
            'label' => 'Companii',
            'path' => '/admin/companii',
            'active' => str_starts_with($currentPath, '/admin/companii'),
        ],
        [
            'label' => 'Asocieri clienti',
            'path' => '/admin/asocieri',
            'active' => str_starts_with($currentPath, '/admin/asocieri'),
        ],
    ];

    $menuSections['Rapoarte'] = [
        [
            'label' => 'Cashflow lunar',
            'path' => '/admin/rapoarte/cashflow',
            'active' => str_starts_with($currentPath, '/admin/rapoarte/cashflow'),
        ],
    ];

    $menuSections['Setari'] = [
        [
            'label' => 'Setari',
            'path' => '/admin/setari',
            'active' => str_starts_with($currentPath, '/admin/setari'),
        ],
        [
            'label' => 'Manual',
            'path' => '/admin/manual',
            'active' => str_starts_with($currentPath, '/admin/manual'),
        ],
    ];
}

if ($isSuperAdmin) {
    $menuSections['Administrare'] = [
        [
            'label' => 'Utilizatori',
            'path' => '/admin/utilizatori',
            'active' => str_starts_with($currentPath, '/admin/utilizatori'),
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'ERP Intern') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        (function () {
            try {
                if (localStorage.getItem('dark-mode') === '1') {
                    document.documentElement.classList.add('dark-mode');
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
        .dark-mode .bg-blue-50 { background-color: #0f172a !important; }
        .dark-mode .bg-blue-100 { background-color: #1e293b !important; }
        .dark-mode .border-slate-200 { border-color: #1f2937 !important; }
        .dark-mode .border-slate-300 { border-color: #374151 !important; }
        .dark-mode .border-slate-100 { border-color: #1f2937 !important; }
        .dark-mode .text-slate-900 { color: #f8fafc !important; }
        .dark-mode .text-slate-800 { color: #e2e8f0 !important; }
        .dark-mode .text-slate-700 { color: #cbd5f5 !important; }
        .dark-mode .text-slate-600 { color: #94a3b8 !important; }
        .dark-mode .text-slate-500 { color: #64748b !important; }
        .dark-mode .text-slate-400 { color: #94a3b8 !important; }
        .dark-mode .hover\:bg-slate-100:hover { background-color: #1f2937 !important; }
        .dark-mode .hover\:bg-slate-50:hover { background-color: #1f2937 !important; }
        .dark-mode .text-blue-800 { color: #93c5fd !important; }
        .dark-mode .text-blue-700 { color: #93c5fd !important; }
        .dark-mode .text-blue-600 { color: #7dd3fc !important; }
        .dark-mode .hover\:text-slate-900:hover { color: #f8fafc !important; }
        .dark-mode .hover\:text-blue-800:hover { color: #bfdbfe !important; }
        .dark-mode .hover\:text-blue-700:hover { color: #bfdbfe !important; }
        .dark-mode .hover\:text-blue-600:hover { color: #bae6fd !important; }
        .dark-mode input[type="text"],
        .dark-mode input[type="number"],
        .dark-mode input[type="date"],
        .dark-mode input[type="email"],
        .dark-mode input[type="password"],
        .dark-mode input[type="search"],
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
        .dark-mode table { color: #e2e8f0 !important; }
        .dark-mode thead { background-color: #0b1220 !important; color: #94a3b8 !important; }
        .dark-mode tbody tr { border-color: #1f2937 !important; }
        .dark-mode tbody tr:hover { background-color: #1f2937 !important; }
        .dark-mode .bg-emerald-50 { background-color: #0b2b26 !important; }
        .dark-mode .bg-green-50 { background-color: #0c2a1e !important; }
        .dark-mode .bg-teal-50 { background-color: #0a2a2a !important; }
        .dark-mode .bg-blue-50 { background-color: #0b1f33 !important; }
        .dark-mode .bg-indigo-50 { background-color: #111827 !important; }
        .dark-mode .bg-purple-50 { background-color: #201132 !important; }
        .dark-mode .bg-violet-50 { background-color: #1f1433 !important; }
        .dark-mode .bg-amber-50 { background-color: #2a1f0b !important; }
        .dark-mode .bg-yellow-50 { background-color: #2a240b !important; }
        .dark-mode .bg-orange-50 { background-color: #2a160b !important; }
        .dark-mode .bg-red-50 { background-color: #2a0b0b !important; }
        .dark-mode .bg-rose-50 { background-color: #2a0b1b !important; }
        .dark-mode .dark-toggle-track { background-color: #1f2937 !important; }
        .dark-mode .dark-toggle-knob { background-color: #e2e8f0 !important; }

        body.sidebar-open #sidebar {
            transform: translateX(0);
        }
        body.sidebar-open #mobile-overlay {
            display: block;
        }
        #sidebar {
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="min-h-screen lg:flex">
        <div
            id="mobile-overlay"
            class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"
            aria-hidden="true"
        ></div>
        <aside
            id="sidebar"
            class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full overflow-y-auto border-r border-slate-200 bg-white transition-transform duration-200 ease-in-out lg:relative lg:translate-x-0"
            aria-label="Meniu principal"
        >
            <div class="flex items-center gap-3 border-b border-slate-200 px-6 py-5">
                <a href="<?= App\Support\Url::to('admin/dashboard') ?>" class="inline-flex items-center gap-3">
                    <?php if ($logoUrl): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo ERP" class="h-12 w-auto">
                    <?php else: ?>
                        <span class="text-lg font-semibold text-blue-700">ERP Intern</span>
                    <?php endif; ?>
                </a>
            </div>
            <nav class="px-4 py-6 text-sm space-y-6">
                <?php foreach ($menuSections as $sectionLabel => $items): ?>
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <?= htmlspecialchars($sectionLabel) ?>
                        </div>
                        <ul class="space-y-2">
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <a
                                        href="<?= App\Support\Url::to($item['path']) ?>"
                                        class="flex items-center gap-2 rounded px-3 py-2 font-medium <?= $item['active'] ? 'bg-blue-100 text-blue-800' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100' ?>"
                                    >
                                        <?= htmlspecialchars($item['label']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>
        </aside>

        <div class="flex flex-1 flex-col lg:pl-0">
            <header class="border-b border-slate-200 bg-white">
                <div class="flex items-center justify-between gap-4 px-4 py-4 lg:px-6">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded border border-slate-200 px-3 py-2 text-sm text-slate-700 lg:hidden"
                            id="sidebar-toggle"
                            aria-label="Deschide meniul"
                        >
                            ☰
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="relative inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" id="dark-mode-toggle" class="peer sr-only">
                            <span class="dark-toggle-track relative inline-flex h-6 w-11 items-center rounded-full bg-slate-200 transition-colors peer-checked:bg-blue-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300">
                                <span class="dark-toggle-knob inline-block h-4 w-4 translate-x-1 rounded-full bg-white transition-transform peer-checked:translate-x-6"></span>
                            </span>
                            <span>Dark mode</span>
                        </label>
                        <?php if ($user): ?>
                            <a href="<?= App\Support\Url::to('admin/profil') ?>" class="text-right hover:text-slate-900">
                                <div class="text-sm text-slate-600">Admin</div>
                                <div class="text-base font-semibold text-slate-900">
                                    <?= htmlspecialchars($userFirstName !== '' ? $userFirstName : 'Administrator') ?>
                                </div>
                            </a>
                        <?php endif; ?>
                        <?php if ($user): ?>
                            <form method="POST" action="<?= App\Support\Url::to('logout') ?>">
                                <?= App\Support\Csrf::input() ?>
                                <button type="submit" class="rounded border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:text-slate-900">
                                    Logout
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 lg:px-6 lg:py-8">
                <?php include BASE_PATH . '/resources/views/partials/flash.php'; ?>
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>
    <script>
        (function () {
            const toggle = document.getElementById('sidebar-toggle');
            const overlay = document.getElementById('mobile-overlay');

            if (!toggle || !overlay) {
                return;
            }

            const closeSidebar = () => {
                document.body.classList.remove('sidebar-open');
            };

            toggle.addEventListener('click', () => {
                document.body.classList.toggle('sidebar-open');
            });

            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) {
                    closeSidebar();
                }
            });
        })();
        (function () {
            const darkToggle = document.getElementById('dark-mode-toggle');
            if (!darkToggle) {
                return;
            }
            const setDark = (enabled) => {
                document.documentElement.classList.toggle('dark-mode', enabled);
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
