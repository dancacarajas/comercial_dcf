<?php

declare(strict_types=1);

/**
 * Deploy Etapa 18 + 18B em produção (Hostinger).
 * Executar NO SERVIDOR após git pull:
 *
 *   cd /home/u482227589/domains/dancacarajas.com.br/public_html/comercial
 *   git pull origin main
 *   php scripts/deploy_production_etapa18b.php
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/App.php';

use App\Core\App;
use App\Core\Database;
use App\Services\ContractTemplateSeeder;

$app = new App(BASE_PATH);
$app->boot();

$pdo = Database::connection();

echo "=== Deploy produção — Etapa 18 / 18B ===\n\n";

$migrations = [
    '2026_captadores_credenciamento.sql',
    '2026_etapa18b_contratos_assinaturas.sql',
    '2026_etapa18b_signature_pdf.sql',
];

foreach ($migrations as $file) {
    $path = BASE_PATH . '/database/migrations/' . $file;
    if (!is_file($path)) {
        echo "[SKIP] Migration ausente: {$file}\n";
        continue;
    }
    echo "[RUN] {$file} ... ";
    $sql = (string) file_get_contents($path);
    try {
        $pdo->exec($sql);
        echo "OK\n";
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate column') || str_contains($msg, 'already exists')) {
            echo "OK (já aplicada)\n";
            continue;
        }
        echo "ERRO: " . $msg . "\n";
        exit(1);
    }
}

echo "\n[RUN] Seed modelo captador externo ... ";
try {
    $tplId = ContractTemplateSeeder::upsertCaptadorExternoDefault($pdo);
    echo "OK (id={$tplId})\n";
} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

$dirs = [
    BASE_PATH . '/storage/uploads/signatures',
    BASE_PATH . '/storage/uploads/collector_applications',
    BASE_PATH . '/public/assets/img/branding',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        echo "[WARN] Não foi possível criar pasta: {$dir}\n";
    } else {
        @chmod($dir, 0755);
        echo "[OK] Pasta: {$dir}\n";
    }
}

$checks = [
    'collector_applications' => 'Etapa 18 — credenciamento',
    'contract_templates'       => 'Etapa 18B — modelos',
    'signature_requests'       => 'Etapa 18B — assinaturas',
    'signature_signers'        => 'Etapa 18B — signatários',
];

echo "\n=== Verificação de tabelas ===\n";
foreach ($checks as $table => $label) {
    $exists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($table)
    )->fetchColumn();
    echo ($exists ? '[OK]' : '[FAIL]') . " {$label} ({$table})\n";
    if (!$exists) {
        exit(1);
    }
}

$tplCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM contract_templates WHERE template_key = 'captador_externo_padrao'"
)->fetchColumn();
echo ($tplCount > 0 ? '[OK]' : '[WARN]') . " Modelo captador_externo_padrao (count={$tplCount})\n";

echo "\n=== Concluído ===\n";
echo "Validar: php scripts/validate_etapa18b.php (local) ou acesse https://comercial.dancacarajas.com.br\n";
echo "Pós-deploy: limpar cache do navegador (Ctrl+F5) e testar aprovação + contrato + assinatura.\n";
