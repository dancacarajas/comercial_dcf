<?php

declare(strict_types=1);

/**
 * Validador ETAPA 21A — Catálogo comercial V2.1 canônico.
 *
 * Uso: php tools/validate_etapa21_catalog.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');

if (PHP_SAPI === 'cli') {
    $host = (string) (getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ''));
    if ($host === 'db' || $host === 'localhost' || $host === '127.0.0.1') {
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=3307');
        $_ENV['DB_HOST'] = '127.0.0.1';
        $_ENV['DB_PORT'] = '3307';
    }
}

require_once $root . '/app/Core/Database.php';

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pass = 0;
$fail = 0;

$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        echo "[PASS] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        $pass++;
    } else {
        echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        $fail++;
    }
};

echo "== validate_etapa21_catalog ==\n\n";

$projects = $pdo->query(
    "SELECT * FROM incentive_projects
      WHERE edition_year = 2026 AND archived_at IS NULL
      ORDER BY id ASC"
)->fetchAll();

$check('projeto 2026 único ativo', count($projects) === 1, 'count=' . count($projects));
$project = $projects[0] ?? null;
$pid = (int) ($project['id'] ?? 0);

$check('PRONAC 265397', $project && preg_replace('/\D+/', '', (string) $project['pronac_number']) === '265397');
$check(
    'authorized_capture_amount 515592.48',
    $project && abs((float) $project['authorized_capture_amount'] - 515592.48) < 0.001,
    (string) ($project['authorized_capture_amount'] ?? '')
);
$check(
    'approved_total_amount NULL (não inventado)',
    $project && ($project['approved_total_amount'] === null || $project['approved_total_amount'] === ''),
    (string) ($project['approved_total_amount'] ?? 'NULL')
);
$check(
    'commission budget/factor NULL',
    $project
    && ($project['capture_commission_budget'] === null || $project['capture_commission_budget'] === '')
    && ($project['commission_factor'] === null || $project['commission_factor'] === '')
);

$cols = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotas'
        AND COLUMN_NAME IN ('catalog_ref_id','catalog_version','pricing_mode','min_amount','max_amount','inventory_mode')"
)->fetchAll(PDO::FETCH_COLUMN);
$check('colunas V2.1 em quotas', count($cols) === 6, implode(',', $cols));

$quotas = $pdo->prepare(
    "SELECT * FROM quotas
      WHERE incentive_project_id = :pid AND archived_at IS NULL
        AND catalog_version = '2026-V2.1'
      ORDER BY FIELD(catalog_ref_id,'INCENTIVA','MOVIMENTO','EXPERIENCE','CARAJAS','APRESENTA')"
);
$quotas->execute(['pid' => $pid]);
$rows = $quotas->fetchAll();
$refs = array_map(static fn ($r) => (string) $r['catalog_ref_id'], $rows);

$check('exatamente 5 cotas canônicas ativas', count($rows) === 5, implode(',', $refs));
$check('catalog_ref_ids únicos', count($refs) === count(array_unique($refs)));

$byRef = [];
foreach ($rows as $r) {
    $byRef[(string) $r['catalog_ref_id']] = $r;
}

$check(
    'INCENTIVA range',
    isset($byRef['INCENTIVA'])
    && $byRef['INCENTIVA']['pricing_mode'] === 'RANGE'
    && abs((float) $byRef['INCENTIVA']['min_amount'] - 10000) < 0.001
    && abs((float) $byRef['INCENTIVA']['max_amount'] - 24999.99) < 0.001
    && ($byRef['INCENTIVA']['amount'] === null || $byRef['INCENTIVA']['amount'] === '')
);
$check(
    'MOVIMENTO 25000',
    isset($byRef['MOVIMENTO']) && abs((float) $byRef['MOVIMENTO']['amount'] - 25000) < 0.001
);
$check(
    'EXPERIENCE 50000',
    isset($byRef['EXPERIENCE']) && abs((float) $byRef['EXPERIENCE']['amount'] - 50000) < 0.001
);
$check(
    'CARAJAS 100000',
    isset($byRef['CARAJAS']) && abs((float) $byRef['CARAJAS']['amount'] - 100000) < 0.001
);
$check(
    'APRESENTA 515592.48',
    isset($byRef['APRESENTA'])
    && $byRef['APRESENTA']['pricing_mode'] === 'FULL_PROJECT'
    && abs((float) $byRef['APRESENTA']['amount'] - 515592.48) < 0.001
);

$legacy = $pdo->prepare(
    "SELECT COUNT(*) FROM quotas
      WHERE incentive_project_id = :pid AND archived_at IS NULL
        AND (
          name IN ('Cota Formação','Cota Incentivador','Círculo Dança Carajás','Cota Apresenta','Cota Movimento')
          OR (name = 'Cota Apresenta' AND amount = 200000)
          OR (name = 'Cota Movimento' AND amount = 50000)
          OR catalog_ref_id IS NULL
        )"
);
$legacy->execute(['pid' => $pid]);
$legacyCount = (int) $legacy->fetchColumn();
$check('nenhuma cota legado ativa no projeto', $legacyCount === 0, 'legacy_active=' . $legacyCount);

$hardcodeFile = $root . '/app/Models/Opportunity.php';
$src = (string) file_get_contents($hardcodeFile);
$check(
    'getQuotaInterests não é fonte do catálogo vigente',
    str_contains($src, 'getLegacyQuotaInterests') || str_contains($src, 'legado'),
    'Opportunity.php'
);

foreach ($rows as $r) {
    $check(
        'inventory UNSPECIFIED em ' . $r['catalog_ref_id'],
        ($r['inventory_mode'] ?? '') === 'UNSPECIFIED'
    );
}

echo "\nResultado: {$pass} PASS / {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
