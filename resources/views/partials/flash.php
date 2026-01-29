<?php

use App\Support\Session;

$status = Session::pull('status');
$error = Session::pull('error');
?>

<?php if ($status): ?>
    <div class="mb-4 rounded border border-blue-100 bg-blue-50 px-4 py-2 text-sm text-blue-800">
        <?= htmlspecialchars($status) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-4 rounded border border-red-100 bg-red-50 px-4 py-2 text-sm text-red-700">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>
