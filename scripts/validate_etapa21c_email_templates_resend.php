<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 21C: templates HTML e reenvio operacional.
 */

$root = dirname(__DIR__);
$passes = 0;
$failures = [];

function ok21c(string $m): void { global $passes; $passes++; echo "[PASS] {$m}\n"; }
function fail21c(string $m): void { global $failures; $failures[] = $m; echo "[FAIL] {$m}\n"; }
function assert21c(bool $c, string $p, string $f): void { $c ? ok21c($p) : fail21c($f); }
function lint21c(string $path): int
{
    if (!function_exists('exec')) {
        return 0;
    }
    $out = [];
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
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

echo "== ETAPA 21C - Templates HTML e Reenvio Operacional ==\n\n";

$files = [
    'app/Controllers/EmailSettingsController.php',
    'app/Models/EmailLog.php',
    'app/Services/MailerService.php',
    'app/Views/email_settings/logs.php',
    'routes/web.php',
    'database/schema.sql',
    'scripts/run_migration_etapa21c_email_templates_resend.php',
    'scripts/validate_etapa21c_email_templates_resend.php',
    'scripts/validate_etapa21b_collector_email_triggers.php',
];
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    assert21c(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($path) && str_ends_with($rel, '.php')) {
        assert21c(lint21c($path) === 0, "php -l {$rel}", "Syntax error: {$rel}");
    }
}

$routes = (string) file_get_contents($root . '/routes/web.php');
assert21c(str_contains($routes, '/settings/email/outbox/{id}/resend'), 'Rota de reenvio registrada', 'Rota de reenvio ausente');

$controller = (string) file_get_contents($root . '/app/Controllers/EmailSettingsController.php');
foreach (['public function resend', 'email_logs.resend', 'email_outbox_resent', 'resent_from_outbox_id'] as $needle) {
    assert21c(str_contains($controller, $needle), "Controller cobre {$needle}", "Controller nao cobre {$needle}");
}

$view = (string) file_get_contents($root . '/app/Views/email_settings/logs.php');
assert21c(str_contains($view, 'Reenviar'), 'Logs exibem botao Reenviar', 'Logs nao exibem botao Reenviar');

$perm = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=:slug');
$perm->execute(['slug' => 'email_logs.resend']);
assert21c((int) $perm->fetchColumn() === 1, 'Permissao email_logs.resend existe', 'Permissao email_logs.resend ausente');

$grant = $pdo->query(
    "SELECT COUNT(*) FROM role_permissions rp
      JOIN roles r ON r.id=rp.role_id
      JOIN permissions p ON p.id=rp.permission_id
     WHERE r.slug='administrador-geral' AND p.slug='email_logs.resend'"
);
assert21c((int) $grant->fetchColumn() === 1, 'Administrador-geral pode reenviar e-mail', 'Administrador-geral sem permissao de reenvio');

$idx = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND INDEX_NAME=:i');
$idx->execute(['t' => 'email_outbox', 'i' => 'uniq_email_outbox_event_entity_recipient']);
assert21c((int) $idx->fetchColumn() === 0, 'Indice unico removido para permitir reenvio', 'Indice unico ainda bloqueia reenvio');
$idx->execute(['t' => 'email_outbox', 'i' => 'idx_email_outbox_event_entity_recipient']);
assert21c((int) $idx->fetchColumn() > 0, 'Indice operacional de outbox existe', 'Indice operacional de outbox ausente');

$templateKeys = ['collector_application_received', 'collector_documents_requested', 'signature_request_sent', 'collector_access_released'];
$tpl = $pdo->prepare('SELECT subject, body_html FROM email_templates WHERE event_key=:key');
foreach ($templateKeys as $key) {
    $tpl->execute(['key' => $key]);
    $row = $tpl->fetch();
    $html = (string) ($row['body_html'] ?? '');
    assert21c($row !== false, "Template existe: {$key}", "Template ausente: {$key}");
    assert21c(str_contains($html, 'Dança Carajás Festival'), "Template {$key} tem marca", "Template {$key} sem marca");
    assert21c(str_contains($html, 'background:#f4c400'), "Template {$key} tem faixa amarela", "Template {$key} sem identidade visual");
    assert21c(str_contains($html, 'border-radius:14px'), "Template {$key} usa card de e-mail", "Template {$key} sem layout card");
}

$settingsModel = new \App\Models\MailSetting();
$original = $settingsModel->current();
$outboxIds = [];
try {
    $settingsModel->saveSettings([
        'provider' => 'gmail',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => 'teste21c@example.com',
        'smtp_password' => 'SenhaTeste21C!NaoEnviar',
        'from_name' => 'Danca Carajas Captacao',
        'from_email' => 'teste21c@example.com',
        'reply_to_name' => 'Equipe Danca Carajas',
        'reply_to_email' => 'equipe21c@example.com',
        'enabled' => 1,
        'dry_run' => 1,
        'hourly_limit' => 20,
        'daily_limit' => 100,
    ]);
    $message = [
        'event_key' => 'collector_application_received',
        'entity_type' => 'collector_application',
        'entity_id' => 21000021,
        'recipient_type' => 'captador',
        'to_email' => 'reenviar21c@example.com',
        'to_name' => 'Teste Reenvio',
        'subject' => 'Teste reenvio',
        'body_text' => 'Teste reenvio',
        'body_html' => '<p>Teste reenvio</p>',
        'payload' => ['source' => 'validate_21c'],
    ];
    $first = (new \App\Services\MailerService())->send($message);
    $second = (new \App\Services\MailerService())->send($message + ['payload' => ['source' => 'validate_21c', 'resent_from_outbox_id' => $first['outbox_id'] ?? null]]);
    $outboxIds = array_filter([(int) ($first['outbox_id'] ?? 0), (int) ($second['outbox_id'] ?? 0)]);
    assert21c(($first['status'] ?? '') === 'simulated', 'Primeiro envio simulado registrado', 'Primeiro envio nao foi simulado');
    assert21c(($second['status'] ?? '') === 'simulated', 'Reenvio simulado registrado', 'Reenvio nao foi simulado');
    assert21c(count(array_unique($outboxIds)) === 2, 'Reenvio cria novo registro outbox', 'Reenvio nao criou novo outbox');
} catch (Throwable $e) {
    fail21c('Teste funcional de reenvio falhou: ' . $e->getMessage());
} finally {
    $pdo->prepare("DELETE FROM email_logs WHERE entity_type='collector_application' AND entity_id=21000021")->execute();
    $pdo->prepare("DELETE FROM email_outbox WHERE entity_type='collector_application' AND entity_id=21000021")->execute();
    if (!empty($original['id'])) {
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
            'provider' => $original['provider'],
            'smtp_host' => $original['smtp_host'],
            'smtp_port' => $original['smtp_port'],
            'smtp_encryption' => $original['smtp_encryption'],
            'smtp_username' => $original['smtp_username'],
            'smtp_password_encrypted' => $original['smtp_password_encrypted'],
            'from_name' => $original['from_name'],
            'from_email' => $original['from_email'],
            'reply_to_name' => $original['reply_to_name'],
            'reply_to_email' => $original['reply_to_email'],
            'enabled' => $original['enabled'],
            'dry_run' => $original['dry_run'],
            'hourly_limit' => $original['hourly_limit'],
            'daily_limit' => $original['daily_limit'],
            'last_tested_at' => $original['last_tested_at'],
            'last_test_status' => $original['last_test_status'],
            'last_test_message' => $original['last_test_message'] ?? null,
            'id' => $original['id'],
        ]);
    }
}

echo "\nResultado: {$passes} PASS, " . count($failures) . " FAIL\n";
if ($failures !== []) {
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}
exit(0);
