<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Document') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Fallback utility stylesheet for PDF generators that do not execute Tailwind CDN JS. */
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            line-height: 1.35;
        }
        table { border-collapse: collapse; }
        a { color: #1d4ed8; text-decoration: none; }
        h1, h2, h3, p { margin: 0; }
        strong { font-weight: 700; }

        .mx-auto { margin-left: auto; margin-right: auto; }
        .w-full { width: 100%; }
        .max-w-5xl { max-width: 64rem; }
        .table-fixed { table-layout: fixed; }
        .break-words { overflow-wrap: anywhere; word-break: break-word; }

        .grid { display: grid; }
        .md\:grid-cols-2 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        @media (min-width: 768px) {
            .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        .flex { display: flex; }
        .inline-flex { display: inline-flex; }
        .flex-wrap { flex-wrap: wrap; }
        .items-start { align-items: flex-start; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }

        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 0.75rem; }
        .gap-4 { gap: 1rem; }
        .space-y-1 > * + * { margin-top: 0.25rem; }
        .space-y-4 > * + * { margin-top: 1rem; }
        .space-y-6 > * + * { margin-top: 1.5rem; }

        .overflow-x-auto { overflow-x: auto; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .uppercase { text-transform: uppercase; }
        .tracking-wide { letter-spacing: 0.025em; }

        .text-xs { font-size: 0.75rem; line-height: 1rem; }
        .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
        .text-base { font-size: 1rem; line-height: 1.5rem; }
        .text-xl { font-size: 1.25rem; line-height: 1.75rem; }
        .font-semibold { font-weight: 600; }

        .text-slate-900 { color: #0f172a; }
        .text-slate-700 { color: #334155; }
        .text-slate-600 { color: #475569; }
        .text-slate-500 { color: #64748b; }
        .text-blue-700 { color: #1d4ed8; }

        .bg-white { background-color: #ffffff; }
        .bg-slate-50 { background-color: #f8fafc; }
        .bg-slate-100 { background-color: #f1f5f9; }
        .bg-blue-50 { background-color: #eff6ff; }

        .rounded { border-radius: 0.25rem; }
        .rounded-lg { border-radius: 0.5rem; }

        .border { border-width: 1px; border-style: solid; }
        .border-t { border-top-width: 1px; border-top-style: solid; }
        .border-b { border-bottom-width: 1px; border-bottom-style: solid; }
        .border-slate-100 { border-color: #f1f5f9; }
        .border-slate-200 { border-color: #e2e8f0; }
        .border-slate-300 { border-color: #cbd5e1; }
        .border-slate-400 { border-color: #94a3b8; }

        .shadow-sm { box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08); }

        .p-3 { padding: 0.75rem; }
        .p-4 { padding: 1rem; }
        .p-6 { padding: 1.5rem; }
        .px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
        .px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .py-6 { padding-top: 1.5rem; padding-bottom: 1.5rem; }

        .mt-1 { margin-top: 0.25rem; }
        .mt-2 { margin-top: 0.5rem; }
        .mt-4 { margin-top: 1rem; }
        .mt-6 { margin-top: 1.5rem; }

        @media (min-width: 1024px) {
            .lg\:px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
            .lg\:py-8 { padding-top: 2rem; padding-bottom: 2rem; }
        }

        <?php if (!empty($pdfMode)): ?>
        .no-print { display: none !important; }
        body { background: #ffffff !important; }
        .shadow-sm { box-shadow: none !important; }
        <?php endif; ?>

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
