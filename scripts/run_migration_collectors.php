<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/App.php';

use App\Core\App;
use App\Core\Database;

$app = new App(BASE_PATH);
$app->boot();

$pdo = Database::connection();
$file = BASE_PATH . '/database/migrations/2026_etapa18c_collectors.sql';
$sql = (string) file_get_contents($file);

// Remove linhas de comentário para não suprimir statements que vêm logo após comentários.
$lines = array_filter(
    explode("\n", $sql),
    static fn (string $line): bool => !str_starts_with(trim($line), '--')
);
$clean = implode("\n", $lines);

foreach (array_filter(array_map('trim', explode(';', $clean))) as $statement) {
    if ($statement === '') {
        continue;
    }
    $pdo->exec($statement);
}

echo "Migration applied: 2026_etapa18c_collectors.sql\n";
