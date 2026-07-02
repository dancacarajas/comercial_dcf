<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 21B: Gatilhos de e-mail da trilha de captadores.
 */

$root = dirname(__DIR__);
$passes = 0;
$failures = [];

function ok21b(string $m): void { global $passes; $passes++; echo "[PASS] {$m}\n"; }
function fail21b(string $m): void { global $failures; $failures[] = $m; echo "[FAIL] {$m}\n"; }
function assert21b(bool $c, string $p, string $f): void { $c ? ok21b($p) : fail21b($f); }
function lint21b(string $path): int
{
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    if (!function_exists('exec')) {
        return 0;
    }
    $out = [];
    exec($cmd, $out, $rc);
    return (int) $rc;
}

require_once $root . '/app/Helpers/env.php';
require_once $root . '/app/Helpers/security.php';
load_env($root . '/.env');
spl_autoload_register(function (string $c) use ($root): void {
    if (strncmp($c, 'App\\', 4) !== 0) { return; }
    $f = $root . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (is_file($f)) { require $f; }
});

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "== ETAPA 21B - Gatilhos de E-mail da Trilha de Captadores ==\n\n";

$files = [
    'app/Services/EmailEventService.php',
    'app/Services/MailerService.php',
    'app/Models/EmailLog.php',
    'app/Controllers/Api/CollectorApplicationApiController.php',
    'app/Controllers/CollectorApplicationController.php',
    'app/Controllers/CollectorPublicController.php',
    'app/Controllers/SignatureRequestController.php',
    'app/Controllers/SignaturePublicController.php',
    'scripts/run_migration_etapa21b_collector_email_triggers.php',
    'scripts/validate_etapa21b_collector_email_triggers.php',
    'database/schema.sql',
];
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    assert21b(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($path) && str_ends_with($rel, '.php')) {
        assert21b(lint21b($path) === 0, "php -l {$rel}", "Syntax error: {$rel}");
    }
}

$columnStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c');
foreach (['email_outbox', 'email_logs'] as $table) {
    foreach (['entity_type', 'entity_id', 'recipient_type'] as $column) {
        $columnStmt->execute(['t' => $table, 'c' => $column]);
        assert21b((int) $columnStmt->fetchColumn() === 1, "Coluna existe: {$table}.{$column}", "Coluna ausente: {$table}.{$column}");
    }
}
$idxStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND INDEX_NAME=:i');
$idxStmt->execute(['t' => 'email_outbox', 'i' => 'idx_email_outbox_event_entity_recipient']);
assert21b((int) $idxStmt->fetchColumn() > 0, 'Outbox tem indice operacional por evento/entidade/destinatario', 'Outbox sem indice operacional');

$templates = [
    'collector_application_received',
    'collector_application_received_internal',
    'collector_application_triage_started',
    'collector_documents_requested',
    'collector_document_uploaded',
    'collector_documents_completed',
    'collector_documents_completed_internal',
    'collector_document_reviewed_pending_correction',
    'collector_application_in_document_review',
    'collector_application_adjustments_requested',
    'collector_application_rejected',
    'collector_application_approved',
    'signature_request_sent',
    'collector_contract_signed',
    'collector_contract_signed_internal',
    'collector_contract_fully_signed',
    'collector_access_released',
    'collector_access_self_registered',
    'collector_access_self_registered_internal',
];
$tplStmt = $pdo->prepare('SELECT COUNT(*) FROM email_templates WHERE event_key=:k AND enabled=1');
$ruleStmt = $pdo->prepare('SELECT COUNT(*) FROM email_event_rules WHERE event_key=:k AND enabled=1');
foreach ($templates as $eventKey) {
    $tplStmt->execute(['k' => $eventKey]);
    assert21b((int) $tplStmt->fetchColumn() === 1, "Template ativo: {$eventKey}", "Template ausente/inativo: {$eventKey}");
    $ruleStmt->execute(['k' => $eventKey]);
    assert21b((int) $ruleStmt->fetchColumn() === 1, "Regra ativa: {$eventKey}", "Regra ausente/inativa: {$eventKey}");
}

$sources = [
    'app/Controllers/Api/CollectorApplicationApiController.php' => ['collector_application_received', 'collector_application_received_internal', 'EmailEventService'],
    'app/Controllers/CollectorApplicationController.php' => ['collector_documents_requested', 'collector_application_approved', 'collector_application_rejected', 'collector_access_released', 'collector_application_adjustments_requested', 'sendSignatureStageEmail', 'signature_request_sent'],
    'app/Controllers/CollectorPublicController.php' => ['collector_document_uploaded', 'collector_documents_completed', 'collector_access_self_registered'],
    'app/Controllers/SignatureRequestController.php' => ['signature_request_sent'],
    'app/Controllers/SignaturePublicController.php' => ['collector_contract_signed', 'collector_contract_fully_signed'],
];
foreach ($sources as $rel => $needles) {
    $content = (string) file_get_contents($root . '/' . $rel);
    foreach ($needles as $needle) {
        assert21b(str_contains($content, $needle), "{$rel} dispara {$needle}", "{$rel} nao dispara {$needle}");
    }
}

$settingsModel = new \App\Models\MailSetting();
$originalSettings = $settingsModel->current();
$appId = 0;
try {
    $settingsModel->saveSettings([
        'provider' => 'gmail',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => 'teste21b@example.com',
        'smtp_password' => 'SenhaTeste21B!NaoEnviar',
        'from_name' => 'Danca Carajas Captacao',
        'from_email' => 'teste21b@example.com',
        'reply_to_name' => 'Equipe Danca Carajas',
        'reply_to_email' => 'equipe21b@example.com',
        'enabled' => 1,
        'dry_run' => 1,
        'hourly_limit' => 20,
        'daily_limit' => 100,
    ]);

    $applicationModel = new \App\Models\CollectorApplication();
    $appId = (int) $applicationModel->create([
        'source' => 'site',
        'name' => 'Teste Etapa 21B',
        'company_or_activity' => 'Captacao',
        'document_number' => '00000000191',
        'email' => 'captador21b@example.com',
        'phone_whatsapp' => '5599999999999',
        'city_state' => 'Maraba/PA',
        'rouanet_experience' => 'basica',
        'status' => 'manifestacao_recebida',
        'document_status' => 'nao_solicitado',
        'review_status' => 'pendente',
        'access_status' => 'nao_liberado',
        'consent_contact' => 1,
    ]);
    $application = $applicationModel->findById($appId);
    assert21b($application !== null, 'Candidatura de teste criada', 'Falha ao criar candidatura de teste');

    $service = new \App\Services\EmailEventService();
    $first = $service->sendToCollector('collector_application_received', $application ?? []);
    $second = $service->sendToCollector('collector_application_received', $application ?? []);
    assert21b(($first['status'] ?? '') === 'simulated', 'Gatilho registra envio simulado quando dry-run ativo', 'Gatilho nao simulou envio');
    assert21b(($second['status'] ?? '') === 'skipped', 'Gatilho nao duplica mesmo evento da mesma candidatura', 'Gatilho duplicou evento');

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM email_outbox
          WHERE event_key='collector_application_received'
            AND entity_type='collector_application'
            AND entity_id=:id
            AND recipient_type='captador'"
    );
    $countStmt->execute(['id' => $appId]);
    assert21b((int) $countStmt->fetchColumn() === 1, 'Outbox contem um unico registro do evento da candidatura', 'Outbox duplicou ou nao registrou evento');
} catch (Throwable $e) {
    fail21b('Teste funcional dos gatilhos falhou: ' . $e->getMessage());
} finally {
    if ($appId > 0) {
        $pdo->prepare('DELETE FROM email_logs WHERE entity_type=:t AND entity_id=:id')->execute(['t' => 'collector_application', 'id' => $appId]);
        $pdo->prepare('DELETE FROM email_outbox WHERE entity_type=:t AND entity_id=:id')->execute(['t' => 'collector_application', 'id' => $appId]);
        $pdo->prepare('DELETE FROM collector_applications WHERE id=:id')->execute(['id' => $appId]);
    }
    if (!empty($originalSettings['id'])) {
        $pdo->prepare(
            'UPDATE mail_settings
                SET provider=:provider, smtp_host=:smtp_host, smtp_port=:smtp_port, smtp_encryption=:smtp_encryption,
                    smtp_username=:smtp_username, smtp_password_encrypted=:smtp_password_encrypted,
                    from_name=:from_name, from_email=:from_email, reply_to_name=:reply_to_name, reply_to_email=:reply_to_email,
                    enabled=:enabled, dry_run=:dry_run, hourly_limit=:hourly_limit, daily_limit=:daily_limit,
                    last_tested_at=:last_tested_at, last_test_status=:last_test_status, last_test_message=:last_test_message,
                    updated_at=NOW()
              WHERE id=:id'
        )->execute([
            'provider' => $originalSettings['provider'],
            'smtp_host' => $originalSettings['smtp_host'],
            'smtp_port' => $originalSettings['smtp_port'],
            'smtp_encryption' => $originalSettings['smtp_encryption'],
            'smtp_username' => $originalSettings['smtp_username'],
            'smtp_password_encrypted' => $originalSettings['smtp_password_encrypted'],
            'from_name' => $originalSettings['from_name'],
            'from_email' => $originalSettings['from_email'],
            'reply_to_name' => $originalSettings['reply_to_name'],
            'reply_to_email' => $originalSettings['reply_to_email'],
            'enabled' => $originalSettings['enabled'],
            'dry_run' => $originalSettings['dry_run'],
            'hourly_limit' => $originalSettings['hourly_limit'],
            'daily_limit' => $originalSettings['daily_limit'],
            'last_tested_at' => $originalSettings['last_tested_at'],
            'last_test_status' => $originalSettings['last_test_status'],
            'last_test_message' => $originalSettings['last_test_message'] ?? null,
            'id' => $originalSettings['id'],
        ]);
    }
}

$schema = (string) file_get_contents($root . '/database/schema.sql');
foreach (['entity_type', 'idx_email_outbox_event_entity_recipient', 'email_event_rules'] as $needle) {
    assert21b(str_contains($schema, $needle), "schema.sql cobre {$needle}", "schema.sql nao cobre {$needle}");
}

echo "\nResultado: {$passes} PASS, " . count($failures) . " FAIL\n";
if ($failures !== []) {
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}
exit(0);
