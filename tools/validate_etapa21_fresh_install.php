<?php

declare(strict_types=1);

/**
 * Etapa 21.2 — Fresh install gate (string-scan + temp DB real).
 *
 * Uso: php tools/validate_etapa21_fresh_install.php
 *
 * Preferência: MySQL/MariaDB em 127.0.0.1:3307 (Docker local via .env).
 * Cria dcf_e21_fresh_test, importa database/install_schema.sql, valida seeds
 * canônicos V2.1 e dropa o DB ao final.
 */

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');

$host = (string) (getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1'));
$port = (string) (getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3307'));
$user = (string) (getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'danca'));
$pass = (string) (getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? 'danca'));
$rootUser = (string) (getenv('DB_ROOT_USERNAME') ?: ($_ENV['DB_ROOT_USERNAME'] ?? 'root'));
$rootPass = (string) (getenv('DB_ROOT_PASSWORD') ?: ($_ENV['DB_ROOT_PASSWORD'] ?? 'root'));

if ($host === 'db' || $host === 'localhost') {
    $host = '127.0.0.1';
}
if ($port === '' || $port === '3306') {
    // Em CLI local, porta publicada do compose é tipicamente 3307
    $port = (string) (getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3307'));
    if ($port === '' || $port === '3306') {
        $port = '3307';
    }
}

$results = [];
$dbName = 'dcf_e21_fresh_test';
$schemaPath = $root . '/database/install_schema.sql';

function pass(string $label, bool $ok, string $detail = ''): void
{
    global $results;
    $results[] = ['ok' => $ok, 'label' => $label];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

echo "== validate_etapa21_fresh_install (21.2) ==\n";
echo "DB target={$host}:{$port} temp={$dbName}\n\n";

if (!is_file($schemaPath)) {
    pass('install_schema.sql existe', false, $schemaPath);
    echo "\nResultado: 0 PASS / 1 FAIL\n";
    exit(1);
}

$schema = (string) file_get_contents($schemaPath);

// --- String-scan gates ---
echo "-- string-scan --\n";

$legacyHits = [];
foreach (['Cota Formação', 'Cota Incentivador', 'Círculo Dança Carajás', '470448', '42768'] as $needle) {
    if (str_contains($schema, $needle)) {
        $legacyHits[] = $needle;
    }
}
if (preg_match("/'Cota Apresenta'[\s\S]{0,120}200000/", $schema)) {
    $legacyHits[] = 'Cota Apresenta+200000';
}
if (preg_match("/'Cota Movimento'[\s\S]{0,120}50000/", $schema)) {
    $legacyHits[] = 'Cota Movimento+50000';
}
pass('scan: sem seeds legados ativos', $legacyHits === [], $legacyHits === [] ? '' : implode(' | ', $legacyHits));
pass('scan: contém 265397', str_contains($schema, '265397'));
pass('scan: contém 515592.48', str_contains($schema, '515592.48'));
foreach (['INCENTIVA', 'MOVIMENTO', 'EXPERIENCE', 'CARAJAS', 'APRESENTA'] as $ref) {
    pass("scan: catalog_ref_id {$ref}", str_contains($schema, $ref));
}
pass(
    'scan: quotas.amount DECIMAL(14,2)',
    (bool) preg_match('/CREATE TABLE IF NOT EXISTS `quotas`[\s\S]{0,800}`amount`\s+DECIMAL\(14,2\)/i', $schema)
);
pass(
    'scan: available_quantity nullable no CREATE quotas',
    (bool) preg_match(
        '/CREATE TABLE IF NOT EXISTS `quotas`[\s\S]{0,900}`available_quantity`\s+INT(?:\s+UNSIGNED)?\s+NULL/i',
        $schema
    )
);

// --- Temp DB import (prefer real Docker MySQL) ---
echo "\n-- temp DB import --\n";

$adminPdo = null;
$importOk = false;
$importDetail = '';

$multiAttr = defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
    ? constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
    : (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS') ? PDO::MYSQL_ATTR_MULTI_STATEMENTS : 1001);

try {
    $adminPdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $rootUser,
        $rootPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            $multiAttr => true,
        ]
    );
    pass('temp DB: conexão root/admin OK', true, "{$host}:{$port}");
} catch (Throwable $e) {
    // fallback: usuário app com CREATE privilege
    try {
        $adminPdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                $multiAttr => true,
            ]
        );
        pass('temp DB: conexão app OK (sem root)', true, "{$user}@{$host}:{$port}");
    } catch (Throwable $e2) {
        pass('temp DB: conexão disponível', false, $e->getMessage());
        $adminPdo = null;
    }
}

if ($adminPdo instanceof PDO) {
    try {
        $adminPdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
        $adminPdo->exec(
            "CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        pass('temp DB: CREATE dcf_e21_fresh_test', true);

        // Import via docker exec quando disponível (mais confiável que PDO multi-statement gigante)
        $imported = false;
        $docker = trim((string) shell_exec('docker ps --format "{{.Names}}" 2>NUL'));
        if (str_contains($docker, 'dcc_db')) {
            $cmd = 'docker exec -i dcc_db mariadb -u' . escapeshellarg($rootUser)
                . ' -p' . escapeshellarg($rootPass) . ' ' . escapeshellarg($dbName);
            $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open($cmd, $desc, $pipes);
            if (is_resource($proc)) {
                fwrite($pipes[0], $schema);
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($proc);
                $imported = ($code === 0);
                $importDetail = $imported ? 'docker exec mariadb' : ('exit=' . $code . ' ' . substr((string) $stderr, 0, 200));
            }
        }

        if (!$imported) {
            // Fallback PDO (pode falhar em dumps com DELIMITER)
            $adminPdo->exec("USE `{$dbName}`");
            $adminPdo->exec($schema);
            $imported = true;
            $importDetail = 'PDO multi-statement';
        }

        $importOk = $imported;
        pass('temp DB: import install_schema.sql', $importOk, $importDetail);
    } catch (Throwable $e) {
        pass('temp DB: import install_schema.sql', false, substr($e->getMessage(), 0, 240));
        $importOk = false;
    }
}

if ($importOk && $adminPdo instanceof PDO) {
    try {
        $db = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
            $rootUser,
            $rootPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        $db = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    $proj = $db->query(
        "SELECT pronac_number, project_status, approved_total_amount, authorized_capture_amount,
                capture_commission_budget, edition_year, archived_at
           FROM incentive_projects
          WHERE REPLACE(REPLACE(COALESCE(pronac_number,''), '-', ''), ' ', '') = '265397'
             OR (edition_year = 2026 AND archived_at IS NULL)
          ORDER BY id ASC LIMIT 1"
    )->fetch() ?: [];

    $pronac = preg_replace('/\D+/', '', (string) ($proj['pronac_number'] ?? '')) ?? '';
    $auth = isset($proj['authorized_capture_amount']) ? (float) $proj['authorized_capture_amount'] : null;
    $approved = $proj['approved_total_amount'] ?? null;
    $commission = $proj['capture_commission_budget'] ?? null;

    pass(
        'temp DB: 1 projeto PRONAC 265397',
        $pronac === '265397',
        'pronac=' . (string) ($proj['pronac_number'] ?? 'null')
    );
    pass(
        'temp DB: authorized_capture_amount = 515592.48',
        $auth !== null && abs($auth - 515592.48) < 0.001,
        (string) ($proj['authorized_capture_amount'] ?? 'null')
    );
    pass(
        'temp DB: approved_total_amount NULL',
        $approved === null || $approved === '',
        (string) ($approved ?? 'null')
    );
    pass(
        'temp DB: capture_commission_budget NULL',
        $commission === null || $commission === '',
        (string) ($commission ?? 'null')
    );

    $v21 = $db->query(
        "SELECT catalog_ref_id FROM quotas
          WHERE archived_at IS NULL
            AND catalog_ref_id IN ('INCENTIVA','MOVIMENTO','EXPERIENCE','CARAJAS','APRESENTA')"
    )->fetchAll(PDO::FETCH_COLUMN);
    $v21 = array_values(array_unique(array_map('strval', $v21)));
    sort($v21);
    $expected = ['APRESENTA', 'CARAJAS', 'EXPERIENCE', 'INCENTIVA', 'MOVIMENTO'];
    pass(
        'temp DB: exatamente 5 cotas V2.1 ativas',
        count($v21) === 5 && $v21 === $expected,
        'found=' . implode(',', $v21)
    );

    $legacyNames = [
        'Cota Formação',
        'Cota Incentivador',
        'Círculo Dança Carajás',
        'Cota Apresenta',
        'Cota Carajás',
        'Cota Movimento',
    ];
    $inList = "'" . implode("','", array_map(static fn ($n) => str_replace("'", "''", $n), $legacyNames)) . "'";
    $legacyActive = (int) $db->query(
        "SELECT COUNT(*) FROM quotas WHERE archived_at IS NULL AND name IN ({$inList})"
    )->fetchColumn();
    pass('temp DB: 0 nomes legados ativos', $legacyActive === 0, 'count=' . $legacyActive);
}

// Drop temp DB
if ($adminPdo instanceof PDO) {
    try {
        $adminPdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
        pass('temp DB: DROP dcf_e21_fresh_test', true);
    } catch (Throwable $e) {
        pass('temp DB: DROP dcf_e21_fresh_test', false, $e->getMessage());
    }
}

$fails = count(array_filter($results, static fn ($r) => !$r['ok']));
$passes = count($results) - $fails;
echo "\nResultado: {$passes} PASS / {$fails} FAIL\n";
exit($fails > 0 ? 1 : 0);
