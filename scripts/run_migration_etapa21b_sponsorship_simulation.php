<?php

declare(strict_types=1);

/**
 * ETAPA 21B / 21.1 — Sponsorship Simulation + Lead fields (idempotente, FK estrita).
 *
 * Uso:
 *   php scripts/run_migration_etapa21b_sponsorship_simulation.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');

// Produção: respeita .env. Override Docker local SOMENTE com flag explícita.
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

$hasTable = static function (PDO $pdo, string $table): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $st->execute(['t' => $table]);
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

$hasForeignKey = static function (PDO $pdo, string $table, string $constraint): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
            AND CONSTRAINT_NAME = :c AND CONSTRAINT_TYPE = \'FOREIGN KEY\''
    );
    $st->execute(['t' => $table, 'c' => $constraint]);
    return (int) $st->fetchColumn() > 0;
};

$fail = static function (string $msg): void {
    fwrite(STDERR, "ERRO 21B: {$msg}\n");
    exit(1);
};

echo "== ETAPA 21B — Sponsorship Simulation (21.1 hardened) ==\n\n";

if (!$hasColumn($pdo, 'leads', 'incentive_project_id')) {
    $pdo->exec('ALTER TABLE `leads` ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `status`');
    echo "leads.incentive_project_id criado\n";
} else {
    echo "leads.incentive_project_id ja existe\n";
}
if (!$hasIndex($pdo, 'leads', 'idx_leads_incentive_project')) {
    $pdo->exec('ALTER TABLE `leads` ADD KEY `idx_leads_incentive_project` (`incentive_project_id`)');
    echo "idx_leads_incentive_project criado\n";
}

if (!$hasColumn($pdo, 'leads', 'submission_type')) {
    $pdo->exec("ALTER TABLE `leads` ADD COLUMN `submission_type` VARCHAR(40) NOT NULL DEFAULT 'GENERIC_LEAD' AFTER `incentive_project_id`");
    echo "leads.submission_type criado\n";
} else {
    echo "leads.submission_type ja existe\n";
}
if (!$hasIndex($pdo, 'leads', 'idx_leads_submission_type')) {
    $pdo->exec('ALTER TABLE `leads` ADD KEY `idx_leads_submission_type` (`submission_type`)');
    echo "idx_leads_submission_type criado\n";
}

if (!$hasColumn($pdo, 'opportunities', 'sponsorship_simulation_id')) {
    $pdo->exec('ALTER TABLE `opportunities` ADD COLUMN `sponsorship_simulation_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `quota_id`');
    echo "opportunities.sponsorship_simulation_id criado\n";
} else {
    echo "opportunities.sponsorship_simulation_id ja existe\n";
}
if (!$hasIndex($pdo, 'opportunities', 'idx_opportunities_sponsorship_simulation')) {
    $pdo->exec('ALTER TABLE `opportunities` ADD KEY `idx_opportunities_sponsorship_simulation` (`sponsorship_simulation_id`)');
    echo "idx_opportunities_sponsorship_simulation criado\n";
}

if (!$hasTable($pdo, 'sponsorship_simulations')) {
    $pdo->exec(<<<SQL
CREATE TABLE `sponsorship_simulations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` BIGINT UNSIGNED NOT NULL,
    `incentive_project_id` BIGINT UNSIGNED NOT NULL,
    `submission_uuid` VARCHAR(64) NOT NULL,
    `submission_version` VARCHAR(20) NOT NULL,
    `snapshot_version` VARCHAR(20) NOT NULL,
    `catalog_version` VARCHAR(20) NOT NULL,
    `briefing_schema_version` VARCHAR(20) NOT NULL,
    `policy_version` VARCHAR(20) NOT NULL,
    `engine_version` VARCHAR(20) NOT NULL,
    `presenter_version` VARCHAR(20) NULL DEFAULT NULL,
    `confirmed` TINYINT(1) NOT NULL DEFAULT 1,
    `confirmed_at` DATETIME NOT NULL,
    `investment_status` VARCHAR(40) NOT NULL,
    `investment_min` DECIMAL(14,2) NULL DEFAULT NULL,
    `investment_max` DECIMAL(14,2) NULL DEFAULT NULL,
    `currency` CHAR(3) NOT NULL DEFAULT 'BRL',
    `primary_tier_ref` VARCHAR(40) NOT NULL,
    `primary_axis_ref` VARCHAR(80) NULL DEFAULT NULL,
    `primary_activation_ref` VARCHAR(80) NULL DEFAULT NULL,
    `primary_property_ref` VARCHAR(80) NULL DEFAULT NULL,
    `fit_level` VARCHAR(40) NOT NULL,
    `availability_status` VARCHAR(40) NOT NULL,
    `briefing_snapshot` LONGTEXT NOT NULL,
    `interests_snapshot` LONGTEXT NOT NULL,
    `recommendation_snapshot` LONGTEXT NOT NULL,
    `display_snapshot` LONGTEXT NULL DEFAULT NULL,
    `snapshot_hash` CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sponsorship_sim_uuid` (`submission_uuid`),
    UNIQUE KEY `uniq_sponsorship_sim_lead` (`lead_id`),
    KEY `idx_sponsorship_sim_project` (`incentive_project_id`),
    KEY `idx_sponsorship_sim_tier` (`primary_tier_ref`),
    KEY `idx_sponsorship_sim_created` (`created_at`),
    CONSTRAINT `fk_sponsorship_sim_lead`
        FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sponsorship_sim_project`
        FOREIGN KEY (`incentive_project_id`) REFERENCES `incentive_projects` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    echo "tabela sponsorship_simulations criada (ON DELETE RESTRICT)\n";
} else {
    echo "tabela sponsorship_simulations ja existe — reconciliando\n";

    $rowCount = (int) $pdo->query('SELECT COUNT(*) FROM `sponsorship_simulations`')->fetchColumn();
    echo "registros existentes: {$rowCount}\n";

    // Colunas invariantes esperadas (existência + nullability).
    // Com registros: NÃO inventar fit/tier/version/hash/confirmation — abortar se faltar NOT NULL.
    // Tabela vazia: ADD estrutural; versões só DEFAULT '1.0.0'; sem defaults semânticos de fit/tier.
    $expectedCols = [
        'lead_id' => ['type' => 'bigint', 'nullable' => false],
        'incentive_project_id' => ['type' => 'bigint', 'nullable' => false],
        'submission_uuid' => ['type' => 'varchar', 'nullable' => false],
        'submission_version' => ['type' => 'varchar', 'nullable' => false],
        'snapshot_version' => ['type' => 'varchar', 'nullable' => false],
        'catalog_version' => ['type' => 'varchar', 'nullable' => false],
        'briefing_schema_version' => ['type' => 'varchar', 'nullable' => false],
        'policy_version' => ['type' => 'varchar', 'nullable' => false],
        'engine_version' => ['type' => 'varchar', 'nullable' => false],
        'presenter_version' => ['type' => 'varchar', 'nullable' => true],
        'confirmed' => ['type' => 'tinyint', 'nullable' => false],
        'confirmed_at' => ['type' => 'datetime', 'nullable' => false],
        'investment_status' => ['type' => 'varchar', 'nullable' => false],
        'investment_min' => ['type' => 'decimal', 'nullable' => true],
        'investment_max' => ['type' => 'decimal', 'nullable' => true],
        'currency' => ['type' => 'char', 'nullable' => false],
        'primary_tier_ref' => ['type' => 'varchar', 'nullable' => false],
        'primary_axis_ref' => ['type' => 'varchar', 'nullable' => true],
        'primary_activation_ref' => ['type' => 'varchar', 'nullable' => true],
        'primary_property_ref' => ['type' => 'varchar', 'nullable' => true],
        'fit_level' => ['type' => 'varchar', 'nullable' => false],
        'availability_status' => ['type' => 'varchar', 'nullable' => false],
        'briefing_snapshot' => ['type' => 'longtext', 'nullable' => false],
        'interests_snapshot' => ['type' => 'longtext', 'nullable' => false],
        'recommendation_snapshot' => ['type' => 'longtext', 'nullable' => false],
        'display_snapshot' => ['type' => 'longtext', 'nullable' => true],
        'snapshot_hash' => ['type' => 'char', 'nullable' => false],
        'created_at' => ['type' => 'datetime', 'nullable' => false],
        'updated_at' => ['type' => 'datetime', 'nullable' => true],
    ];

    // Tabela vazia: ADD NOT NULL preferencialmente sem defaults semânticos inventados.
    // Versões: apenas '1.0.0' (nunca '1.0'). Sem default inventado de fit_level / primary_tier_ref.
    $addDdlEmpty = [
        'lead_id' => 'ADD COLUMN `lead_id` BIGINT UNSIGNED NOT NULL',
        'incentive_project_id' => 'ADD COLUMN `incentive_project_id` BIGINT UNSIGNED NOT NULL',
        'submission_uuid' => 'ADD COLUMN `submission_uuid` VARCHAR(64) NOT NULL',
        'submission_version' => "ADD COLUMN `submission_version` VARCHAR(20) NOT NULL DEFAULT '1.0.0'",
        'snapshot_version' => "ADD COLUMN `snapshot_version` VARCHAR(20) NOT NULL DEFAULT '1.0.0'",
        'catalog_version' => "ADD COLUMN `catalog_version` VARCHAR(20) NOT NULL DEFAULT '2026-V2.1'",
        'briefing_schema_version' => "ADD COLUMN `briefing_schema_version` VARCHAR(20) NOT NULL DEFAULT '1.0.0'",
        'policy_version' => "ADD COLUMN `policy_version` VARCHAR(20) NOT NULL DEFAULT '1.0.0'",
        'engine_version' => "ADD COLUMN `engine_version` VARCHAR(20) NOT NULL DEFAULT '1.0.0'",
        'presenter_version' => 'ADD COLUMN `presenter_version` VARCHAR(20) NULL DEFAULT NULL',
        'confirmed' => 'ADD COLUMN `confirmed` TINYINT(1) NOT NULL',
        'confirmed_at' => 'ADD COLUMN `confirmed_at` DATETIME NOT NULL',
        'investment_status' => 'ADD COLUMN `investment_status` VARCHAR(40) NOT NULL',
        'investment_min' => 'ADD COLUMN `investment_min` DECIMAL(14,2) NULL DEFAULT NULL',
        'investment_max' => 'ADD COLUMN `investment_max` DECIMAL(14,2) NULL DEFAULT NULL',
        'currency' => "ADD COLUMN `currency` CHAR(3) NOT NULL DEFAULT 'BRL'",
        'primary_tier_ref' => 'ADD COLUMN `primary_tier_ref` VARCHAR(40) NOT NULL',
        'primary_axis_ref' => 'ADD COLUMN `primary_axis_ref` VARCHAR(80) NULL DEFAULT NULL',
        'primary_activation_ref' => 'ADD COLUMN `primary_activation_ref` VARCHAR(80) NULL DEFAULT NULL',
        'primary_property_ref' => 'ADD COLUMN `primary_property_ref` VARCHAR(80) NULL DEFAULT NULL',
        'fit_level' => 'ADD COLUMN `fit_level` VARCHAR(40) NOT NULL',
        'availability_status' => 'ADD COLUMN `availability_status` VARCHAR(40) NOT NULL',
        'briefing_snapshot' => 'ADD COLUMN `briefing_snapshot` LONGTEXT NOT NULL',
        'interests_snapshot' => 'ADD COLUMN `interests_snapshot` LONGTEXT NOT NULL',
        'recommendation_snapshot' => 'ADD COLUMN `recommendation_snapshot` LONGTEXT NOT NULL',
        'display_snapshot' => 'ADD COLUMN `display_snapshot` LONGTEXT NULL DEFAULT NULL',
        'snapshot_hash' => 'ADD COLUMN `snapshot_hash` CHAR(64) NOT NULL',
        'created_at' => 'ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL',
    ];

    $colMeta = $pdo->query(
        "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_TYPE
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sponsorship_simulations'"
    )->fetchAll(PDO::FETCH_ASSOC);
    $byName = [];
    foreach ($colMeta as $row) {
        $byName[strtolower((string) $row['COLUMN_NAME'])] = $row;
    }

    foreach ($expectedCols as $col => $spec) {
        if (!isset($byName[$col])) {
            // Com dados: coluna NOT NULL invariante ausente → FAIL seguro (não inventar valores).
            if ($rowCount > 0 && $spec['nullable'] === false) {
                $fail(
                    "coluna invariante NOT NULL ausente: {$col}"
                    . " (tabela tem {$rowCount} registro(s))"
                    . ' — reconciliação insegura; não inventar fit/tier/version/hash/confirmation'
                );
            }
            if (!isset($addDdlEmpty[$col])) {
                $fail("coluna obrigatoria ausente sem DDL de adicao: {$col}");
            }
            if ($rowCount > 0) {
                // Nullable ausente com dados: ADD NULL é seguro (sem inventar domínio).
                try {
                    $pdo->exec('ALTER TABLE `sponsorship_simulations` ' . $addDdlEmpty[$col]);
                    echo "coluna {$col} adicionada (nullable, com {$rowCount} registro(s))\n";
                } catch (Throwable $e) {
                    $fail("falha ao adicionar coluna {$col}: " . $e->getMessage());
                }
                continue;
            }
            try {
                $pdo->exec('ALTER TABLE `sponsorship_simulations` ' . $addDdlEmpty[$col]);
                echo "coluna {$col} adicionada (tabela vazia)\n";
            } catch (Throwable $e) {
                $fail("falha ao adicionar coluna {$col}: " . $e->getMessage());
            }
            continue;
        }
        $isNull = strtoupper((string) $byName[$col]['IS_NULLABLE']) === 'YES';
        $type = strtolower((string) $byName[$col]['DATA_TYPE']);
        if ($spec['type'] !== '' && !str_starts_with($type, $spec['type']) && $type !== $spec['type']) {
            // char/varchar/longtext/datetime/decimal/bigint/tinyint — aceitar família
            $okFamily = match ($spec['type']) {
                'varchar' => in_array($type, ['varchar', 'char', 'text', 'mediumtext', 'longtext'], true),
                'char' => in_array($type, ['char', 'varchar'], true),
                'longtext' => in_array($type, ['longtext', 'mediumtext', 'text'], true),
                'datetime' => in_array($type, ['datetime', 'timestamp'], true),
                'decimal' => in_array($type, ['decimal', 'numeric', 'float', 'double'], true),
                'bigint' => in_array($type, ['bigint', 'int'], true),
                'tinyint' => in_array($type, ['tinyint', 'boolean', 'bool', 'smallint'], true),
                default => false,
            };
            if (!$okFamily) {
                $fail("coluna {$col} tipo inesperado: {$type} (esperado {$spec['type']})");
            }
        }
        if ($spec['nullable'] === false && $isNull) {
            // Tentar endurecer; se houver NULLs, abortar com detalhe — sem backfill inventado
            $nullCount = (int) $pdo->query(
                "SELECT COUNT(*) FROM `sponsorship_simulations` WHERE `{$col}` IS NULL"
            )->fetchColumn();
            if ($nullCount > 0) {
                $fail(
                    "coluna {$col} deveria ser NOT NULL mas tem {$nullCount} NULL(s)"
                    . " (tabela tem {$rowCount} registro(s))"
                    . ' — reconciliação insegura; não inventar fit/tier/version/hash/confirmation'
                );
            }
            $colType = (string) $byName[$col]['COLUMN_TYPE'];
            try {
                $pdo->exec("ALTER TABLE `sponsorship_simulations` MODIFY COLUMN `{$col}` {$colType} NOT NULL");
                echo "coluna {$col} reconciliada para NOT NULL\n";
            } catch (Throwable $e) {
                $fail("nao foi possivel tornar {$col} NOT NULL: " . $e->getMessage());
            }
        }
    }

    foreach ([
        'uniq_sponsorship_sim_uuid' => 'ADD UNIQUE KEY `uniq_sponsorship_sim_uuid` (`submission_uuid`)',
        'uniq_sponsorship_sim_lead' => 'ADD UNIQUE KEY `uniq_sponsorship_sim_lead` (`lead_id`)',
        'idx_sponsorship_sim_project' => 'ADD KEY `idx_sponsorship_sim_project` (`incentive_project_id`)',
        'idx_sponsorship_sim_tier' => 'ADD KEY `idx_sponsorship_sim_tier` (`primary_tier_ref`)',
        'idx_sponsorship_sim_created' => 'ADD KEY `idx_sponsorship_sim_created` (`created_at`)',
    ] as $idx => $ddl) {
        if (!$hasIndex($pdo, 'sponsorship_simulations', $idx)) {
            $pdo->exec('ALTER TABLE `sponsorship_simulations` ' . $ddl);
            echo "indice {$idx} criado\n";
        }
    }

    // Reconciliar FK lead → RESTRICT
    if ($hasForeignKey($pdo, 'sponsorship_simulations', 'fk_sponsorship_sim_lead')) {
        $rule = $pdo->query(
            "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND CONSTRAINT_NAME = 'fk_sponsorship_sim_lead'"
        )->fetchColumn();
        if (strtoupper((string) $rule) !== 'RESTRICT' && strtoupper((string) $rule) !== 'NO ACTION') {
            $pdo->exec('ALTER TABLE `sponsorship_simulations` DROP FOREIGN KEY `fk_sponsorship_sim_lead`');
            $pdo->exec(
                'ALTER TABLE `sponsorship_simulations`
                   ADD CONSTRAINT `fk_sponsorship_sim_lead`
                   FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`)
                   ON DELETE RESTRICT ON UPDATE CASCADE'
            );
            echo "FK fk_sponsorship_sim_lead reconciliada para ON DELETE RESTRICT\n";
        } else {
            echo "FK fk_sponsorship_sim_lead ja RESTRICT/NO ACTION\n";
        }
    } else {
        try {
            $pdo->exec(
                'ALTER TABLE `sponsorship_simulations`
                   ADD CONSTRAINT `fk_sponsorship_sim_lead`
                   FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`)
                   ON DELETE RESTRICT ON UPDATE CASCADE'
            );
            echo "FK fk_sponsorship_sim_lead criada\n";
        } catch (Throwable $e) {
            $fail('FK lead obrigatoria ausente: ' . $e->getMessage());
        }
    }

    if (!$hasForeignKey($pdo, 'sponsorship_simulations', 'fk_sponsorship_sim_project')) {
        try {
            $pdo->exec(
                'ALTER TABLE `sponsorship_simulations`
                   ADD CONSTRAINT `fk_sponsorship_sim_project`
                   FOREIGN KEY (`incentive_project_id`) REFERENCES `incentive_projects` (`id`)
                   ON DELETE RESTRICT ON UPDATE CASCADE'
            );
            echo "FK fk_sponsorship_sim_project criada\n";
        } catch (Throwable $e) {
            $fail('FK project obrigatoria ausente: ' . $e->getMessage());
        }
    }
}

if (!$hasForeignKey($pdo, 'opportunities', 'fk_opportunities_sponsorship_simulation')) {
    try {
        $pdo->exec(
            'ALTER TABLE `opportunities`
               ADD CONSTRAINT `fk_opportunities_sponsorship_simulation`
               FOREIGN KEY (`sponsorship_simulation_id`) REFERENCES `sponsorship_simulations` (`id`)
               ON DELETE SET NULL ON UPDATE CASCADE'
        );
        echo "FK opportunities.sponsorship_simulation_id criada\n";
    } catch (Throwable $e) {
        $fail('FK opportunities obrigatoria ausente: ' . $e->getMessage());
    }
} else {
    echo "FK opportunities.sponsorship_simulation_id ja existe\n";
}

// Validacao final
foreach ([
    ['sponsorship_simulations', 'uniq_sponsorship_sim_uuid'],
    ['sponsorship_simulations', 'uniq_sponsorship_sim_lead'],
    ['leads', 'idx_leads_submission_type'],
] as [$t, $i]) {
    if (!$hasIndex($pdo, $t, $i)) {
        $fail("indice obrigatorio ausente: {$t}.{$i}");
    }
}
if (!$hasForeignKey($pdo, 'sponsorship_simulations', 'fk_sponsorship_sim_lead')) {
    $fail('FK fk_sponsorship_sim_lead ausente apos reconciliacao');
}
$delRule = (string) $pdo->query(
    "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND CONSTRAINT_NAME = 'fk_sponsorship_sim_lead'"
)->fetchColumn();
if (!in_array(strtoupper($delRule), ['RESTRICT', 'NO ACTION'], true)) {
    $fail('fk_sponsorship_sim_lead DELETE_RULE esperada RESTRICT, obtida ' . $delRule);
}

echo "\nETAPA 21B CONCLUIDA (validada).\n";
exit(0);
