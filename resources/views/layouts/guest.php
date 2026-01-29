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
<body class="bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
            <div class="text-lg font-semibold text-blue-700">ERP Intern</div>
            <div class="text-xs uppercase tracking-wide text-slate-500">Setup</div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-8">
        <?php include BASE_PATH . '/resources/views/partials/flash.php'; ?>
        <?= $content ?? '' ?>
    </main>
</body>
</html>
