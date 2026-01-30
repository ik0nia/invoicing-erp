<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Document') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .shadow-sm { box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
    <main class="px-4 py-6 lg:px-6 lg:py-8">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
