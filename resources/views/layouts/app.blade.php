<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ERP Intern')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
    @php
        $brandingLogo = app(\App\Domain\Settings\Services\SettingsService::class)
            ->get('branding.logo_path');
    @endphp

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <div class="flex items-center gap-3">
                @if ($brandingLogo)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::url($brandingLogo) }}"
                        alt="Logo ERP"
                        class="h-10 w-auto"
                    >
                @else
                    <span class="text-lg font-semibold text-blue-700">ERP Intern</span>
                @endif
                <span class="text-xs uppercase tracking-wide text-slate-500">Admin</span>
            </div>
            <nav class="flex items-center gap-4 text-sm">
                <a
                    href="{{ route('admin.settings.branding') }}"
                    class="text-blue-700 hover:text-blue-800"
                >
                    Setari branding
                </a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-8">
        @yield('content')
    </main>
</body>
</html>
