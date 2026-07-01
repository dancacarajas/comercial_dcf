<?php

declare(strict_types=1);

/**
 * Migration idempotente - ETAPA 19C (escopo operacional por projeto).
 *
 * Adiciona incentive_project_id nos modulos legados que ainda dependiam
 * apenas de vinculos indiretos para descobrir o projeto.
 */

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');
spl_autoload_register(function (string $c) use ($root): void {
    if (strncmp($c, 'App\\', 4) !== 0) { return; }
    $f = $root . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (is_file($f)) { require $f; }
});

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$hasCol = static function (PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $st->execute(['t' => $table, 'c' => $col]);
    return (int) $st->fetchColumn() > 0;
};

echo "== ETAPA 19C - Escopo por Projeto ==\n\n";

$tables = ['contracts', 'documents', 'counterparts', 'sponsor_dossiers'];
foreach ($tables as $table) {
    if (!$hasCol($pdo, $table, 'incentive_project_id')) {
        $pdo->exec(
            "ALTER TABLE `{$table}`
                ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL,
                ADD KEY `idx_{$table}_incentive_project` (`incentive_project_id`)"
        );
        echo "coluna incentive_project_id adicionada em {$table}\n";
    } else {
        echo "coluna incentive_project_id ja existe em {$table}\n";
    }
}

$projectId = (int) $pdo->query(
    "SELECT id FROM incentive_projects
      WHERE project_name = 'Dança Carajás Festival 2026'
      ORDER BY id ASC LIMIT 1"
)->fetchColumn();

if ($projectId > 0) {
    $updates = [
        'contracts' => "UPDATE contracts ct
            LEFT JOIN sponsors s ON s.id = ct.sponsor_id
            LEFT JOIN proposals p ON p.id = ct.proposal_id
            LEFT JOIN opportunities o ON o.id = ct.opportunity_id
            LEFT JOIN quotas q ON q.id = ct.quota_id
            SET ct.incentive_project_id = COALESCE(s.incentive_project_id, p.incentive_project_id, o.incentive_project_id, q.incentive_project_id, :pid)
            WHERE ct.incentive_project_id IS NULL",
        'documents' => "UPDATE documents d
            LEFT JOIN opportunities o ON o.id = d.opportunity_id
            LEFT JOIN proposals p ON p.id = d.proposal_id
            LEFT JOIN sponsors s ON s.id = d.sponsor_id
            LEFT JOIN contracts ct ON ct.id = d.contract_id
            LEFT JOIN financial_entries fe ON fe.id = d.financial_entry_id
            LEFT JOIN sponsor_dossiers sd ON sd.id = d.sponsor_dossier_id
            LEFT JOIN quotas q ON q.id = d.quota_id
            SET d.incentive_project_id = COALESCE(o.incentive_project_id, p.incentive_project_id, s.incentive_project_id, ct.incentive_project_id, fe.incentive_project_id, sd.incentive_project_id, q.incentive_project_id, :pid)
            WHERE d.incentive_project_id IS NULL",
        'counterparts' => "UPDATE counterparts cp
            LEFT JOIN sponsors s ON s.id = cp.sponsor_id
            LEFT JOIN proposals p ON p.id = cp.proposal_id
            LEFT JOIN opportunities o ON o.id = cp.opportunity_id
            LEFT JOIN quotas q ON q.id = cp.quota_id
            SET cp.incentive_project_id = COALESCE(s.incentive_project_id, p.incentive_project_id, o.incentive_project_id, q.incentive_project_id, :pid)
            WHERE cp.incentive_project_id IS NULL",
        'sponsor_dossiers' => "UPDATE sponsor_dossiers sd
            LEFT JOIN sponsors s ON s.id = sd.sponsor_id
            LEFT JOIN contracts ct ON ct.id = sd.main_contract_id
            LEFT JOIN proposals p ON p.id = sd.proposal_id
            LEFT JOIN opportunities o ON o.id = sd.opportunity_id
            LEFT JOIN quotas q ON q.id = sd.quota_id
            SET sd.incentive_project_id = COALESCE(s.incentive_project_id, ct.incentive_project_id, p.incentive_project_id, o.incentive_project_id, q.incentive_project_id, :pid)
            WHERE sd.incentive_project_id IS NULL",
    ];

    foreach ($updates as $table => $sql) {
        $st = $pdo->prepare($sql);
        $st->execute(['pid' => $projectId]);
        echo "{$table}: {$st->rowCount()} registro(s) preenchido(s)\n";
    }
}

echo "\nMigration ETAPA 19C concluida.\n";
