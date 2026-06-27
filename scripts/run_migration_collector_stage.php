<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/App.php';

use App\Core\App;
use App\Core\Database;

$app = new App(BASE_PATH);
$app->boot();

$pdo = Database::connection();
$file = BASE_PATH . '/database/migrations/2026_collector_signature_stage_templates.sql';
$sql = (string) file_get_contents($file);
$pdo->exec($sql);
echo "Migration applied: 2026_collector_signature_stage_templates.sql\n";
