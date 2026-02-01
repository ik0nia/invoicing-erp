<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura furnizor</title>
    <style>
        body { font-family: "Inter", "Segoe UI", Arial, sans-serif; margin: 0; padding: 24px; background: #f8fafc; color: #0f172a; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06); }
        .title { font-size: 18px; font-weight: 700; margin-bottom: 12px; }
        pre { white-space: pre-wrap; word-break: break-word; background: #f1f5f9; border-radius: 8px; padding: 12px; font-size: 12px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">Factura furnizor<?= isset($invoice) ? ' #' . htmlspecialchars($invoice->invoice_number) : '' ?></div>
        <pre><?= htmlspecialchars($content ?? '') ?></pre>
    </div>
</body>
</html>
