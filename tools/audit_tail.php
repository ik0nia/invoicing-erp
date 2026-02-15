<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/Autoloader.php';

App\Support\Autoloader::register();
App\Support\Env::load(BASE_PATH . '/.env');
date_default_timezone_set(App\Support\Env::get('APP_TIMEZONE', 'Europe/Bucharest'));

$limit = isset($argv[1]) ? (int) $argv[1] : 50;
if ($limit <= 0) {
    $limit = 50;
}

if (!App\Support\Database::tableExists('audit_log')) {
    echo "audit_log table missing.\n";
    exit(1);
}

$limit = min($limit, 500);
$rows = App\Support\Database::fetchAll(
    'SELECT created_at, actor_user_id, action, entity_type, entity_id
     FROM audit_log
     ORDER BY created_at DESC, id DESC
     LIMIT ' . (int) $limit
);

foreach ($rows as $row) {
    $createdAt = (string) ($row['created_at'] ?? '');
    $actor = $row['actor_user_id'] !== null ? (string) $row['actor_user_id'] : '-';
    $action = (string) ($row['action'] ?? '');
    $entityType = (string) ($row['entity_type'] ?? '');
    $entityId = $row['entity_id'] !== null ? (string) $row['entity_id'] : '-';
    echo $createdAt . ' | user:' . $actor . ' | ' . $action . ' | ' . $entityType . '/' . $entityId . PHP_EOL;
}
