<?php

declare(strict_types=1);

/**
 * Migration idempotente — ETAPA 19 (Projetos Incentivados / PRONACs / Plano de Captação).
 *
 * - Cria as tabelas incentive_projects e incentive_project_budget_items.
 * - Adiciona incentive_project_id (NULL) nas 7 tabelas operacionais prioritárias.
 * - Cria as permissões incentive_projects.* e concede aos perfis internos.
 * - Cria o projeto "Dança Carajás Festival 2026" e a rubrica de captação (item 41).
 * - Faz o backfill dos registros existentes para o projeto 2026.
 *
 * Seguro para rodar várias vezes. NÃO torna a FK obrigatória e NÃO remove campos
 * legados de sponsors. Uso: php scripts/run_migration_etapa19_projects.php
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

/** Verifica se uma coluna existe na tabela atual. */
$hasCol = static function (PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $st->execute(['t' => $table, 'c' => $col]);
    return (int) $st->fetchColumn() > 0;
};

echo "== ETAPA 19 — Projetos Incentivados ==\n\n";

// ---------------------------------------------------------------------
// 1) Tabelas novas
// ---------------------------------------------------------------------
$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `incentive_projects` (
    `id`                         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_name`               VARCHAR(200)    NOT NULL,
    `edition_year`               SMALLINT UNSIGNED NULL DEFAULT NULL,
    `pronac_number`              VARCHAR(60)     NULL DEFAULT NULL,
    `salic_proposal_number`      VARCHAR(60)     NULL DEFAULT NULL,
    `law_framework`              VARCHAR(120)    NULL DEFAULT 'Lei Rouanet',
    `proponent_name`             VARCHAR(200)    NULL DEFAULT NULL,
    `proponent_document`         VARCHAR(40)     NULL DEFAULT NULL,
    `project_status`             VARCHAR(40)     NOT NULL DEFAULT 'em_elaboracao',
    `approved_total_amount`      DECIMAL(14,2)   NULL DEFAULT NULL,
    `authorized_capture_amount`  DECIMAL(14,2)   NULL DEFAULT NULL,
    `capture_commission_budget`  DECIMAL(14,2)   NULL DEFAULT NULL,
    `commission_factor`          DECIMAL(12,10)  NULL DEFAULT NULL,
    `capture_start_date`         DATE            NULL DEFAULT NULL,
    `capture_end_date`           DATE            NULL DEFAULT NULL,
    `bank_name`                  VARCHAR(120)    NULL DEFAULT NULL,
    `bank_agency`                VARCHAR(30)     NULL DEFAULT NULL,
    `bank_account`               VARCHAR(40)     NULL DEFAULT NULL,
    `bank_account_digit`         VARCHAR(10)     NULL DEFAULT NULL,
    `bank_account_type`          VARCHAR(30)     NULL DEFAULT NULL,
    `notes`                      TEXT            NULL DEFAULT NULL,
    `created_by`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`                 BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`                 DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                 DATETIME        NULL DEFAULT NULL,
    `archived_at`                DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_incentive_projects_status` (`project_status`),
    KEY `idx_incentive_projects_year` (`edition_year`),
    KEY `idx_incentive_projects_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
echo "tabela incentive_projects ok\n";

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `incentive_project_budget_items` (
    `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `incentive_project_id`        BIGINT UNSIGNED NOT NULL,
    `item_number`                 INT             NULL DEFAULT NULL,
    `source`                      VARCHAR(120)    NULL DEFAULT NULL,
    `product`                     VARCHAR(200)    NULL DEFAULT NULL,
    `stage`                       VARCHAR(120)    NULL DEFAULT NULL,
    `uf`                          VARCHAR(2)      NULL DEFAULT NULL,
    `city`                        VARCHAR(120)    NULL DEFAULT NULL,
    `budget_item_name`            VARCHAR(255)    NOT NULL,
    `unit`                        VARCHAR(40)     NULL DEFAULT NULL,
    `quantity`                    DECIMAL(12,2)   NULL DEFAULT NULL,
    `occurrence`                  DECIMAL(12,2)   NULL DEFAULT NULL,
    `unit_amount`                 DECIMAL(14,2)   NULL DEFAULT NULL,
    `requested_amount`            DECIMAL(14,2)   NULL DEFAULT NULL,
    `is_capture_commission_item`  TINYINT(1)      NOT NULL DEFAULT 0,
    `notes`                       TEXT            NULL DEFAULT NULL,
    `created_at`                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                  DATETIME        NULL DEFAULT NULL,
    `archived_at`                 DATETIME        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ipbi_project` (`incentive_project_id`),
    KEY `idx_ipbi_commission` (`is_capture_commission_item`),
    KEY `idx_ipbi_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
echo "tabela incentive_project_budget_items ok\n";

// ---------------------------------------------------------------------
// 2) Coluna incentive_project_id (NULL) nas tabelas operacionais
// ---------------------------------------------------------------------
$opTables = [
    'quotas', 'opportunities', 'proposals', 'sponsors',
    'financial_entries', 'collector_assignments', 'collector_deals',
];
foreach ($opTables as $t) {
    if (!$hasCol($pdo, $t, 'incentive_project_id')) {
        $pdo->exec(
            "ALTER TABLE `{$t}`
                ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL,
                ADD KEY `idx_{$t}_incentive_project` (`incentive_project_id`)"
        );
        echo "coluna incentive_project_id adicionada em {$t}\n";
    } else {
        echo "coluna incentive_project_id já existe em {$t}\n";
    }
}

// ---------------------------------------------------------------------
// 3) Permissões + concessão aos perfis
// ---------------------------------------------------------------------
$perms = [
    ['incentive_projects.view',            'Projetos incentivados: visualizar'],
    ['incentive_projects.create',          'Projetos incentivados: criar'],
    ['incentive_projects.edit',            'Projetos incentivados: editar'],
    ['incentive_projects.archive',         'Projetos incentivados: arquivar/restaurar'],
    ['incentive_projects.budget',          'Projetos incentivados: gerir rubricas/orçamento'],
    ['incentive_projects.activate_capture','Projetos incentivados: liberar captação'],
];
$insP = $pdo->prepare(
    'INSERT INTO permissions (name, slug, description, created_at, updated_at)
     VALUES (:n, :s, :d, NOW(), NOW())
     ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)'
);
foreach ($perms as [$slug, $name]) {
    $insP->execute(['n' => $name, 's' => $slug, 'd' => $name]);
}
echo "permissões incentive_projects.* ok\n";

/** Concede um conjunto de slugs a um role. */
$grant = static function (PDO $pdo, string $roleSlug, array $slugs): void {
    $role = $pdo->prepare('SELECT id FROM roles WHERE slug = :s LIMIT 1');
    $role->execute(['s' => $roleSlug]);
    $roleId = $role->fetchColumn();
    if ($roleId === false) { return; }
    $in = "'" . implode("','", $slugs) . "'";
    $ids = $pdo->query("SELECT id FROM permissions WHERE slug IN ({$in})")->fetchAll(PDO::FETCH_COLUMN);
    $insRP = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (:r, :p, NOW())');
    foreach ($ids as $pid) {
        $insRP->execute(['r' => (int) $roleId, 'p' => (int) $pid]);
    }
};
$allSlugs = array_map(static fn ($p) => $p[0], $perms);
$grant($pdo, 'administrador-geral', $allSlugs);
$grant($pdo, 'captacao-comercial', ['incentive_projects.view', 'incentive_projects.create', 'incentive_projects.edit', 'incentive_projects.budget']);
$grant($pdo, 'producao-coordenacao', ['incentive_projects.view']);
$grant($pdo, 'comunicacao', ['incentive_projects.view']);
$grant($pdo, 'leitura-consulta', ['incentive_projects.view']);
echo "concessões de permissões ok\n";

// ---------------------------------------------------------------------
// 4) Projeto inicial "Dança Carajás Festival 2026"
// ---------------------------------------------------------------------
$projectName = 'Dança Carajás Festival 2026';
$editionYear = 2026;
$approvedTotal = 470448.00;
$authorizedCapture = 470448.00;
$commissionBudget = 42768.00;
$commissionFactor = round($commissionBudget / $approvedTotal, 10); // 0.0909090909

$find = $pdo->prepare('SELECT id FROM incentive_projects WHERE project_name = :n AND edition_year = :y LIMIT 1');
$find->execute(['n' => $projectName, 'y' => $editionYear]);
$projectId = $find->fetchColumn();

if ($projectId === false) {
    $ins = $pdo->prepare(
        'INSERT INTO incentive_projects
            (project_name, edition_year, law_framework, proponent_name, project_status,
             approved_total_amount, authorized_capture_amount, capture_commission_budget,
             commission_factor, notes, created_at)
         VALUES
            (:name, :year, :law, :proponent, :status,
             :total, :auth, :budget, :factor, :notes, NOW())'
    );
    $ins->execute([
        'name'      => $projectName,
        'year'      => $editionYear,
        'law'       => 'Lei Rouanet / Incentivo Fiscal Federal',
        'proponent' => 'Dança Carajás',
        'status'    => 'em_captacao',
        'total'     => $approvedTotal,
        'auth'      => $authorizedCapture,
        'budget'    => $commissionBudget,
        'factor'    => $commissionFactor,
        'notes'     => 'Projeto criado automaticamente na ETAPA 19 (backfill dos dados de 2026).',
    ]);
    $projectId = (int) $pdo->lastInsertId();
    echo "projeto criado: {$projectName} (id={$projectId}), fator={$commissionFactor}\n";
} else {
    $projectId = (int) $projectId;
    echo "projeto já existe: {$projectName} (id={$projectId})\n";
}

// ---------------------------------------------------------------------
// 5) Rubrica de captação (item 41)
// ---------------------------------------------------------------------
$findItem = $pdo->prepare(
    'SELECT id FROM incentive_project_budget_items
      WHERE incentive_project_id = :p AND item_number = 41 LIMIT 1'
);
$findItem->execute(['p' => $projectId]);
if ($findItem->fetchColumn() === false) {
    $insItem = $pdo->prepare(
        'INSERT INTO incentive_project_budget_items
            (incentive_project_id, item_number, product, stage, budget_item_name,
             requested_amount, is_capture_commission_item, created_at)
         VALUES
            (:p, 41, :product, :stage, :name, :amount, 1, NOW())'
    );
    $insItem->execute([
        'p'       => $projectId,
        'product' => 'Administração do Projeto',
        'stage'   => 'Captação de Recursos',
        'name'    => 'Remuneração para captação de recursos',
        'amount'  => $commissionBudget,
    ]);
    echo "rubrica de captação (item 41) criada: R$ {$commissionBudget}\n";
} else {
    echo "rubrica de captação (item 41) já existe\n";
}

// capture_commission_budget = soma das rubricas marcadas como comissão
$sumItem = $pdo->prepare(
    'SELECT COALESCE(SUM(requested_amount), 0) FROM incentive_project_budget_items
      WHERE incentive_project_id = :p AND is_capture_commission_item = 1 AND archived_at IS NULL'
);
$sumItem->execute(['p' => $projectId]);
$budgetSum = (float) $sumItem->fetchColumn();
if ($budgetSum > 0) {
    $factor = round($budgetSum / $approvedTotal, 10);
    $pdo->prepare(
        'UPDATE incentive_projects
            SET capture_commission_budget = :b, commission_factor = :f, updated_at = NOW()
          WHERE id = :id'
    )->execute(['b' => $budgetSum, 'f' => $factor, 'id' => $projectId]);
    echo "capture_commission_budget recalculado: R$ {$budgetSum} (fator {$factor})\n";
}

// ---------------------------------------------------------------------
// 6) Backfill dos dados existentes -> projeto 2026
// ---------------------------------------------------------------------
echo "\n-- Backfill --\n";
foreach ($opTables as $t) {
    $st = $pdo->prepare("UPDATE `{$t}` SET `incentive_project_id` = :p WHERE `incentive_project_id` IS NULL");
    $st->execute(['p' => $projectId]);
    echo "{$t}: {$st->rowCount()} registro(s) vinculado(s) ao projeto {$projectId}\n";
}

echo "\nMigration ETAPA 19 concluída.\n";
exit(0);
