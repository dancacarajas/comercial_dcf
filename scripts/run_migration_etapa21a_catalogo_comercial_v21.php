<?php

declare(strict_types=1);

/**
 * ETAPA 21A — Projeto 2026 canônico + Catálogo comercial V2.1 (IN-PLACE).
 *
 * NÃO faz DELETE físico do projeto/cotas com histórico comercial.
 * - Atualiza o projeto 2026 existente
 * - Arquiva cotas legadas
 * - Cria/atualiza as 5 cotas canônicas V2.1
 *
 * Uso:
 *   php scripts/run_migration_etapa21a_catalogo_comercial_v21.php
 *   php scripts/run_migration_etapa21a_catalogo_comercial_v21.php --dry-run
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

$dryRun = in_array('--dry-run', $argv ?? [], true);
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

$hasIndex = static function (PDO $pdo, string $table, string $index): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i'
    );
    $st->execute(['t' => $table, 'i' => $index]);
    return (int) $st->fetchColumn() > 0;
};

echo "== ETAPA 21A — Catálogo comercial V2.1 (IN-PLACE)" . ($dryRun ? ' [DRY-RUN]' : '') . " ==\n\n";

// ------------------------------------------------------------------
// FASE ESTRUTURAL (DDL — pode implicit commit)
// ------------------------------------------------------------------
echo "-- FASE A: DDL --\n";

$alters = [
    'catalog_ref_id' => "ALTER TABLE `quotas` ADD COLUMN `catalog_ref_id` VARCHAR(40) NULL DEFAULT NULL AFTER `commercial_name`",
    'catalog_version' => "ALTER TABLE `quotas` ADD COLUMN `catalog_version` VARCHAR(20) NULL DEFAULT NULL AFTER `catalog_ref_id`",
    'pricing_mode' => "ALTER TABLE `quotas` ADD COLUMN `pricing_mode` VARCHAR(20) NULL DEFAULT NULL AFTER `catalog_version`",
    'min_amount' => "ALTER TABLE `quotas` ADD COLUMN `min_amount` DECIMAL(14,2) NULL DEFAULT NULL AFTER `amount`",
    'max_amount' => "ALTER TABLE `quotas` ADD COLUMN `max_amount` DECIMAL(14,2) NULL DEFAULT NULL AFTER `min_amount`",
    'inventory_mode' => "ALTER TABLE `quotas` ADD COLUMN `inventory_mode` VARCHAR(20) NULL DEFAULT 'TRACKED' AFTER `closed_quantity`",
];

foreach ($alters as $col => $sql) {
    if ($hasCol($pdo, 'quotas', $col)) {
        echo "coluna quotas.{$col} já existe\n";
        continue;
    }
    if ($dryRun) {
        echo "[dry-run] {$sql}\n";
        continue;
    }
    $pdo->exec($sql);
    echo "coluna quotas.{$col} criada\n";
}

if (!$dryRun) {
    $pdo->exec('ALTER TABLE `quotas` MODIFY COLUMN `available_quantity` INT UNSIGNED NULL DEFAULT NULL');
    $pdo->exec('ALTER TABLE `quotas` MODIFY COLUMN `amount` DECIMAL(14,2) NULL DEFAULT NULL');
    echo "available_quantity nullable + amount DECIMAL(14,2)\n";
}

if (!$hasIndex($pdo, 'quotas', 'uniq_quotas_project_catalog_ref')) {
    if ($dryRun) {
        echo "[dry-run] ADD UNIQUE uniq_quotas_project_catalog_ref\n";
    } else {
        $pdo->exec('ALTER TABLE `quotas` ADD UNIQUE KEY `uniq_quotas_project_catalog_ref` (`incentive_project_id`, `catalog_ref_id`)');
        echo "índice uniq_quotas_project_catalog_ref criado\n";
    }
} else {
    echo "índice uniq_quotas_project_catalog_ref já existe\n";
}

foreach (['idx_quotas_catalog_version' => '`catalog_version`', 'idx_quotas_pricing_mode' => '`pricing_mode`'] as $idx => $cols) {
    if ($hasIndex($pdo, 'quotas', $idx)) {
        echo "índice {$idx} já existe\n";
        continue;
    }
    if ($dryRun) {
        echo "[dry-run] ADD KEY {$idx}\n";
        continue;
    }
    $pdo->exec("ALTER TABLE `quotas` ADD KEY `{$idx}` ({$cols})");
    echo "índice {$idx} criado\n";
}

// ------------------------------------------------------------------
// FASE DADOS (transacional)
// ------------------------------------------------------------------
echo "\n-- FASE B: dados canônicos --\n";

$project = $pdo->query(
    "SELECT * FROM incentive_projects
      WHERE REPLACE(REPLACE(COALESCE(pronac_number,''), '-', ''), ' ', '') = '265397'
        AND archived_at IS NULL
      ORDER BY id ASC"
)->fetchAll();

if (count($project) === 0) {
    $project = $pdo->query(
        "SELECT * FROM incentive_projects
          WHERE project_name = 'Dança Carajás Festival 2026'
            AND edition_year = 2026
            AND archived_at IS NULL
          ORDER BY id ASC"
    )->fetchAll();
}

if (count($project) === 0) {
    fwrite(STDERR, "ERRO: nenhum projeto candidato (PRONAC 265397 ou nome+ano exatos). Abortando.\n");
    exit(1);
}
if (count($project) > 1) {
    fwrite(STDERR, "ERRO: mais de um projeto candidato — abortando para evitar UPDATE ambiguo.\n");
    foreach ($project as $p) {
        fwrite(STDERR, sprintf(
            "  id=%s name=%s pronac=%s year=%s authorized=%s\n",
            $p['id'],
            $p['project_name'],
            $p['pronac_number'] ?? 'NULL',
            $p['edition_year'] ?? 'NULL',
            $p['authorized_capture_amount'] ?? 'NULL'
        ));
    }
    exit(1);
}

$project = $project[0];
$projectId = (int) $project['id'];
echo "PREFLIGHT projeto unico:\n";
echo sprintf(
    "  id=%d name=%s pronac=%s year=%s authorized=%s approved=%s commission=%s\n",
    $projectId,
    (string) $project['project_name'],
    (string) ($project['pronac_number'] ?? 'NULL'),
    (string) ($project['edition_year'] ?? 'NULL'),
    (string) ($project['authorized_capture_amount'] ?? 'NULL'),
    (string) ($project['approved_total_amount'] ?? 'NULL'),
    (string) ($project['capture_commission_budget'] ?? 'NULL')
);

$legacyNames = [
    'Cota Apresenta', 'Cota Carajás', 'Cota Movimento',
    'Cota Formação', 'Cota Incentivador', 'Círculo Dança Carajás',
];

$catalog = [
    [
        'ref' => 'INCENTIVA', 'name' => 'Incentiva', 'order' => 1,
        'mode' => 'RANGE', 'amount' => null, 'min' => 10000.00, 'max' => 24999.99,
    ],
    [
        'ref' => 'MOVIMENTO', 'name' => 'Movimento', 'order' => 2,
        'mode' => 'FIXED', 'amount' => 25000.00, 'min' => 25000.00, 'max' => 25000.00,
    ],
    [
        'ref' => 'EXPERIENCE', 'name' => 'Experience', 'order' => 3,
        'mode' => 'FIXED', 'amount' => 50000.00, 'min' => 50000.00, 'max' => 50000.00,
    ],
    [
        'ref' => 'CARAJAS', 'name' => 'Carajás', 'order' => 4,
        'mode' => 'FIXED', 'amount' => 100000.00, 'min' => 100000.00, 'max' => 100000.00,
    ],
    [
        'ref' => 'APRESENTA', 'name' => 'Apresenta', 'order' => 5,
        'mode' => 'FULL_PROJECT', 'amount' => 515592.48, 'min' => 515592.48, 'max' => 515592.48,
    ],
];

if ($dryRun) {
    echo "[dry-run] UPDATE projeto #{$projectId} → PRONAC 265397 / authorized 515592.48 / approved NULL / commission NULL\n";
    echo "[dry-run] ARCHIVE cotas legadas: " . implode(', ', $legacyNames) . "\n";
    foreach ($catalog as $c) {
        echo "[dry-run] UPSERT cota {$c['ref']} {$c['name']} mode={$c['mode']}\n";
    }
    echo "\nDRY-RUN concluído. Nenhuma alteração persistida.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $migrationNote = 'Projeto canônico 2026-V2.1 (ETAPA 21A). approved_total_amount sem fonte documental — NULL. Rubrica de comissão antiga não transportada.';
    $existingNotes = trim((string) ($project['notes'] ?? ''));
    $notesOut = $existingNotes === ''
        ? $migrationNote
        : (str_contains($existingNotes, 'ETAPA 21A')
            ? $existingNotes
            : $existingNotes . "\n\n" . $migrationNote);

    // Projeto canônico — approved_total_amount = NULL (sem fonte canônica)
    // notes: preserva histórico e anexa nota de migração (não sobrescreve silenciosamente)
    $upd = $pdo->prepare(
        'UPDATE incentive_projects SET
            project_name = :name,
            edition_year = 2026,
            pronac_number = :pronac,
            law_framework = :law,
            proponent_name = :proponent,
            project_status = :status,
            approved_total_amount = NULL,
            authorized_capture_amount = :auth,
            capture_commission_budget = NULL,
            commission_factor = NULL,
            notes = :notes,
            updated_at = NOW()
          WHERE id = :id'
    );
    $upd->execute([
        'name' => 'Dança Carajás Festival 2026',
        'pronac' => '265397',
        'law' => 'Lei Rouanet · Art. 18',
        'proponent' => 'JA Produções Artísticas Ltda',
        'status' => 'em_captacao',
        'auth' => 515592.48,
        'notes' => $notesOut,
        'id' => $projectId,
    ]);
    echo "projeto #{$projectId} atualizado (canônico)\n";

    // Arquivar cotas legadas ativas do projeto (por nome legado ou sem catalog_ref)
    $ph = implode(',', array_fill(0, count($legacyNames), '?'));
    $arch = $pdo->prepare(
        "UPDATE quotas SET archived_at = COALESCE(archived_at, NOW()), status = 'arquivada', updated_at = NOW()
          WHERE incentive_project_id = ?
            AND archived_at IS NULL
            AND (
                name IN ({$ph})
                OR catalog_ref_id IS NULL
                OR catalog_ref_id NOT IN ('INCENTIVA','MOVIMENTO','EXPERIENCE','CARAJAS','APRESENTA')
            )"
    );
    $arch->execute(array_merge([$projectId], $legacyNames));
    echo "cotas legadas arquivadas: {$arch->rowCount()}\n";

    // Arquivar rubrica item 41 antiga (não apagar histórico orçamentário)
    if ($hasCol($pdo, 'incentive_project_budget_items', 'archived_at')) {
        $bi = $pdo->prepare(
            'UPDATE incentive_project_budget_items
                SET archived_at = COALESCE(archived_at, NOW()), updated_at = NOW()
              WHERE incentive_project_id = :p
                AND item_number = 41
                AND is_capture_commission_item = 1
                AND archived_at IS NULL'
        );
        $bi->execute(['p' => $projectId]);
        echo "rubrica item 41 arquivada: {$bi->rowCount()}\n";
    }

    $find = $pdo->prepare(
        'SELECT id FROM quotas
          WHERE incentive_project_id = :p AND catalog_ref_id = :ref
          LIMIT 1'
    );
    $ins = $pdo->prepare(
        'INSERT INTO quotas
            (incentive_project_id, name, commercial_name, catalog_ref_id, catalog_version,
             pricing_mode, amount, min_amount, max_amount,
             available_quantity, reserved_quantity, closed_quantity, inventory_mode,
             status, display_order, ideal_profile, notes, created_at)
         VALUES
            (:p, :name, :cname, :ref, :ver, :mode, :amount, :min, :max,
             NULL, 0, 0, :inv, :status, :ord, NULL, :notes, NOW())'
    );
    $upQ = $pdo->prepare(
        'UPDATE quotas SET
            name = :name,
            commercial_name = :cname,
            catalog_version = :ver,
            pricing_mode = :mode,
            amount = :amount,
            min_amount = :min,
            max_amount = :max,
            available_quantity = NULL,
            inventory_mode = :inv,
            status = :status,
            display_order = :ord,
            ideal_profile = NULL,
            notes = :notes,
            archived_at = NULL,
            updated_at = NOW()
          WHERE id = :id'
    );

    foreach ($catalog as $c) {
        $find->execute(['p' => $projectId, 'ref' => $c['ref']]);
        $existingId = $find->fetchColumn();
        $payload = [
            'name' => $c['name'],
            'cname' => $c['name'],
            'ver' => '2026-V2.1',
            'mode' => $c['mode'],
            'amount' => $c['amount'],
            'min' => $c['min'],
            'max' => $c['max'],
            'inv' => 'UNSPECIFIED',
            'status' => 'disponivel',
            'ord' => $c['order'],
            'notes' => 'Catálogo comercial canônico 2026-V2.1',
        ];
        if ($existingId) {
            $upQ->execute($payload + ['id' => (int) $existingId]);
            echo "cota {$c['ref']} atualizada id={$existingId}\n";
        } else {
            $ins->execute($payload + ['p' => $projectId, 'ref' => $c['ref']]);
            echo "cota {$c['ref']} criada id=" . $pdo->lastInsertId() . "\n";
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FALHA: ' . $e->getMessage() . "\n");
    exit(1);
}

// Invariantes
$active = $pdo->prepare(
    "SELECT catalog_ref_id, name, pricing_mode, amount, min_amount, max_amount
       FROM quotas
      WHERE incentive_project_id = :p AND archived_at IS NULL AND catalog_version = '2026-V2.1'
      ORDER BY display_order"
);
$active->execute(['p' => $projectId]);
$rows = $active->fetchAll();
echo "\nCotas ativas V2.1: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo " - {$r['catalog_ref_id']} {$r['name']} {$r['pricing_mode']} {$r['min_amount']}..{$r['max_amount']}\n";
}

$legacyActive = (int) $pdo->query(
    "SELECT COUNT(*) FROM quotas
      WHERE incentive_project_id = {$projectId} AND archived_at IS NULL
        AND (catalog_ref_id IS NULL OR catalog_version IS NULL OR catalog_version <> '2026-V2.1')"
)->fetchColumn();

$proj = $pdo->query("SELECT pronac_number, authorized_capture_amount, approved_total_amount, capture_commission_budget FROM incentive_projects WHERE id={$projectId}")->fetch();

echo "\nProjeto pós-migração:\n";
echo ' PRONAC=' . ($proj['pronac_number'] ?? 'null') . "\n";
echo ' authorized=' . ($proj['authorized_capture_amount'] ?? 'null') . "\n";
echo ' approved=' . ($proj['approved_total_amount'] ?? 'NULL') . "\n";
echo ' commission_budget=' . ($proj['capture_commission_budget'] ?? 'NULL') . "\n";
echo ' legacy_active_quotas=' . $legacyActive . "\n";

if (count($rows) !== 5 || $legacyActive > 0 || ($proj['pronac_number'] ?? '') !== '265397') {
    fwrite(STDERR, "INVARIANTES FALHARAM\n");
    exit(1);
}

echo "\nETAPA 21A CONCLUÍDA (IN-PLACE).\n";
exit(0);
