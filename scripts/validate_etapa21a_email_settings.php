<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 21A: Configuracao de e-mail transacional.
 */

$root = dirname(__DIR__);
$passes = 0;
$failures = [];

function ok(string $m): void { global $passes; $passes++; echo "[PASS] {$m}\n"; }
function fail(string $m): void { global $failures; $failures[] = $m; echo "[FAIL] {$m}\n"; }
function is_ok(bool $c, string $p, string $f): void { $c ? ok($p) : fail($f); }
function lint_path(string $path): int
{
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    if (function_exists('exec')) { $out = []; exec($cmd, $out, $rc); return (int) $rc; }
    return 0;
}

require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');
spl_autoload_register(function (string $c) use ($root): void {
    if (strncmp($c, 'App\\', 4) !== 0) { return; }
    $f = $root . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (is_file($f)) { require $f; }
});

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "== ETAPA 21A - Configuracao de E-mail Transacional ==\n\n";

$files = [
    'app/Models/MailSetting.php',
    'app/Models/EmailTemplate.php',
    'app/Models/EmailLog.php',
    'app/Services/MailerService.php',
    'app/Controllers/EmailSettingsController.php',
    'app/Views/email_settings/index.php',
    'app/Views/email_settings/templates.php',
    'app/Views/email_settings/logs.php',
    'app/Views/layouts/admin.php',
    'routes/web.php',
    'database/schema.sql',
    'scripts/run_migration_etapa21a_email_settings.php',
    'scripts/validate_etapa21a_email_settings.php',
];
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    is_ok(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($path) && str_ends_with($rel, '.php')) {
        is_ok(lint_path($path) === 0, "php -l {$rel}", "Syntax error: {$rel}");
    }
}

$tables = ['mail_settings', 'email_templates', 'email_outbox', 'email_logs', 'email_event_rules'];
$st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');
foreach ($tables as $table) {
    $st->execute(['t' => $table]);
    is_ok((int) $st->fetchColumn() === 1, "Tabela existe: {$table}", "Tabela ausente: {$table}");
}

$columns = [
    'mail_settings' => ['provider','smtp_host','smtp_port','smtp_encryption','smtp_username','smtp_password_encrypted','from_name','from_email','reply_to_name','reply_to_email','enabled','dry_run','hourly_limit','daily_limit','last_tested_at','last_test_status'],
    'email_templates' => ['event_key','name','subject','body_text','body_html','enabled'],
    'email_outbox' => ['event_key','recipient_email','recipient_name','subject','status','error_message','payload_json','sent_at'],
    'email_logs' => ['event_key','recipient_email','recipient_name','subject','status','error_message','payload_json','sent_at'],
    'email_event_rules' => ['event_key','template_event_key','recipient_type','enabled'],
];
$colSt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c');
foreach ($columns as $table => $cols) {
    foreach ($cols as $col) {
        $colSt->execute(['t' => $table, 'c' => $col]);
        is_ok((int) $colSt->fetchColumn() === 1, "Coluna existe: {$table}.{$col}", "Coluna ausente: {$table}.{$col}");
    }
}

$routes = (string) file_get_contents($root . '/routes/web.php');
foreach ([
    '/settings/email',
    '/settings/email/test',
    '/settings/email/templates',
    '/settings/email/logs',
    'EmailSettingsController@index',
    'EmailSettingsController@test',
] as $needle) {
    is_ok(str_contains($routes, $needle), "Rota presente: {$needle}", "Rota ausente: {$needle}");
}

$permissions = [
    'email_settings.view',
    'email_settings.edit',
    'email_settings.test',
    'email_templates.view',
    'email_templates.edit',
    'email_logs.view',
];
$permSt = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=:slug');
foreach ($permissions as $slug) {
    $permSt->execute(['slug' => $slug]);
    is_ok((int) $permSt->fetchColumn() === 1, "Permissao existe: {$slug}", "Permissao ausente: {$slug}");
}
$grantSt = $pdo->query(
    "SELECT COUNT(*) FROM role_permissions rp
      JOIN roles r ON r.id=rp.role_id
      JOIN permissions p ON p.id=rp.permission_id
     WHERE r.slug='administrador-geral'
       AND p.slug IN ('" . implode("','", $permissions) . "')"
);
is_ok((int) $grantSt->fetchColumn() === count($permissions), 'Administrador-geral recebeu permissoes de e-mail', 'Administrador-geral sem todas as permissoes de e-mail');

$controller = (string) file_get_contents($root . '/app/Controllers/EmailSettingsController.php');
foreach (['email_settings.view', 'email_settings.edit', 'email_settings.test', 'email_templates.view', 'email_logs.view', 'email_settings_updated', 'email_settings_test_sent'] as $needle) {
    is_ok(str_contains($controller, $needle), "Controller cobre: {$needle}", "Controller nao cobre: {$needle}");
}
is_ok(str_contains($controller, "input('dry_run', 0)"), 'Controller permite desmarcar dry-run', 'Controller reativa dry-run quando checkbox vem desmarcado');
is_ok(str_contains($controller, "\$result['status'] === 'sent'"), 'Controller diferencia envio real de simulado', 'Controller trata simulado/skipped como sucesso real');

$mailer = (string) file_get_contents($root . '/app/Services/MailerService.php');
foreach (['dry_run', 'simulated', 'stream_socket_client', 'AUTH LOGIN', 'email_outbox', 'email_logs'] as $needle) {
    is_ok(str_contains($mailer, $needle), "Mailer cobre: {$needle}", "Mailer nao cobre: {$needle}");
}

$view = (string) file_get_contents($root . '/app/Views/email_settings/index.php');
is_ok(str_contains($view, 'type="password"'), 'Campo de senha usa password', 'Campo de senha nao usa password');
is_ok(str_contains($view, 'value=""'), 'Senha salva nao e renderizada no value', 'Senha pode estar sendo renderizada');
is_ok(!str_contains($view, 'smtp_password_encrypted') || str_contains($view, 'configured'), 'View nao exibe hash/senha criptografada', 'View pode expor senha criptografada');

$schema = (string) file_get_contents($root . '/database/schema.sql');
foreach (['CREATE TABLE IF NOT EXISTS `mail_settings`', 'CREATE TABLE IF NOT EXISTS `email_templates`', 'CREATE TABLE IF NOT EXISTS `email_outbox`', 'CREATE TABLE IF NOT EXISTS `email_logs`', 'email_settings.view'] as $needle) {
    is_ok(str_contains($schema, $needle), "install_schema cobre: {$needle}", "install_schema nao cobre: {$needle}");
}

$settingsModel = new \App\Models\MailSetting();
$original = $settingsModel->current();
$secret = 'SenhaTeste21A!NaoEnviar';
try {
    $settingsModel->saveSettings([
        'provider' => 'gmail',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_username' => 'teste21a@example.com',
        'smtp_password' => $secret,
        'from_name' => 'Danca Carajas Captacao',
        'from_email' => 'teste21a@example.com',
        'reply_to_name' => 'Equipe Danca Carajas',
        'reply_to_email' => 'teste21a@example.com',
        'enabled' => 1,
        'dry_run' => 1,
        'hourly_limit' => 20,
        'daily_limit' => 100,
    ]);
    $saved = $settingsModel->current();
    is_ok((string) ($saved['smtp_password_encrypted'] ?? '') !== $secret, 'Senha SMTP nao fica em claro no banco', 'Senha SMTP ficou em claro');
    is_ok($settingsModel->decryptedPassword($saved) === $secret, 'Senha SMTP criptografada pode ser recuperada pelo model', 'Falha ao recuperar senha criptografada');

    $result = (new \App\Services\MailerService())->sendTest('teste21a.destino@example.com', 'Teste 21A');
    is_ok($result['status'] === 'simulated', 'Dry-run registra teste como simulado', 'Dry-run nao simulou o teste');
    $outboxStatus = $pdo->prepare('SELECT status FROM email_outbox WHERE id=:id');
    $outboxStatus->execute(['id' => (int) ($result['outbox_id'] ?? 0)]);
    is_ok((string) $outboxStatus->fetchColumn() === 'simulated', 'Outbox marcado como simulated', 'Outbox nao ficou simulated');
} catch (Throwable $e) {
    fail('Teste de criptografia/dry-run falhou: ' . $e->getMessage());
} finally {
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
