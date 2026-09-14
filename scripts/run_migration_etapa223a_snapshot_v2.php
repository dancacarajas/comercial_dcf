<?php

declare(strict_types=1);

/**
 * ETAPA 22.3-A — colunas aditivas Snapshot V2 (idempotente).
 *
 * Uso:
 *   php scripts/run_migration_etapa223a_snapshot_v2.php
 *   php scripts/run_migration_etapa223a_snapshot_v2.php --local-docker-db
 */

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');

if (PHP_SAPI === 'cli') {
    $override = (string) (getenv('DCX_LOCAL_DOCKER_DB_OVERRIDE') ?: ($_ENV['DCX_LOCAL_DOCKER_DB_OVERRIDE'] ?? ''));
    if ($override === '1' || in_array('--local-docker-db', $argv ?? [], true)) {
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=3307');
        $_ENV['DB_HOST'] = '127.0.0.1';
        $_ENV['DB_PORT'] = '3307';
        echo "[local-docker-db] override ativo: 127.0.0.1:3307\n";
    }
}

require_once $root . '/app/Core/Database.php';

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$hasColumn = static function (PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $st->execute(['t' => $table, 'c' => $col]);

    return (int) $st->fetchColumn() > 0;
};

$hasIndex = static function (PDO $pdo, string $table, string $index): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i'
    );
    $st->execute(['t' => $table, 'i' => $index]);

    return (int) $st->fetchColumn() > 0;
};

echo "== ETAPA 22.3-A — Snapshot V2 columns ==\n\n";

if (!$hasColumn($pdo, 'sponsorship_simulations', 'scenario_result_snapshot')) {
    $pdo->exec(
        'ALTER TABLE `sponsorship_simulations`
           ADD COLUMN `scenario_result_snapshot` LONGTEXT NULL DEFAULT NULL AFTER `display_snapshot`'
    );
    echo "scenario_result_snapshot criado\n";
} else {
    echo "scenario_result_snapshot ja existe\n";
}

if (!$hasColumn($pdo, 'sponsorship_simulations', 'result_type')) {
    $pdo->exec(
        'ALTER TABLE `sponsorship_simulations`
           ADD COLUMN `result_type` VARCHAR(64) NULL DEFAULT NULL AFTER `scenario_result_snapshot`'
    );
    echo "result_type criado\n";
} else {
    echo "result_type ja existe\n";
}

if (!$hasIndex($pdo, 'sponsorship_simulations', 'idx_sponsorship_sim_result_type')) {
    $pdo->exec(
        'ALTER TABLE `sponsorship_simulations`
           ADD KEY `idx_sponsorship_sim_result_type` (`result_type`)'
    );
    echo "idx_sponsorship_sim_result_type criado\n";
} else {
    echo "idx_sponsorship_sim_result_type ja existe\n";
}

echo "\nETAPA 22.3-A CONCLUIDA.\n";
exit(0);
