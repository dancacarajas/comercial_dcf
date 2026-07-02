<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 21D: templates premium com logos reais.
 */

$root = dirname(__DIR__);
$passes = 0;
$failures = [];

function ok21d(string $m): void { global $passes; $passes++; echo "[PASS] {$m}\n"; }
function fail21d(string $m): void { global $failures; $failures[] = $m; echo "[FAIL] {$m}\n"; }
function assert21d(bool $c, string $p, string $f): void { $c ? ok21d($p) : fail21d($f); }
function lint21d(string $path): int
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

echo "== ETAPA 21D - Templates Premium de E-mail ==\n\n";

$files = [
    'app/Services/EmailEventService.php',
    'scripts/run_migration_etapa21c_email_templates_resend.php',
    'scripts/run_migration_etapa21d_premium_email_templates.php',
    'scripts/validate_etapa21d_premium_email_templates.php',
    'public/assets/img/branding/danca-carajas-logo.png',
    'public/assets/img/branding/ja-producoes-logo.png',
];
foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    assert21d(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    if (is_file($path) && str_ends_with($rel, '.php')) {
        assert21d(lint21d($path) === 0, "php -l {$rel}", "Syntax error: {$rel}");
    }
}

$service = (string) file_get_contents($root . '/app/Services/EmailEventService.php');
foreach (['festival_logo_url', 'producer_logo_url', 'danca-carajas-logo.png', 'ja-producoes-logo.png'] as $needle) {
    assert21d(str_contains($service, $needle), "EmailEventService injeta {$needle}", "EmailEventService nao injeta {$needle}");
}

$templates = [
    'collector_application_received',
    'collector_documents_requested',
    'signature_request_sent',
    'collector_access_released',
];
$stmt = $pdo->prepare('SELECT subject, body_html FROM email_templates WHERE event_key=:key');
foreach ($templates as $key) {
    $stmt->execute(['key' => $key]);
    $row = $stmt->fetch();
    $html = (string) ($row['body_html'] ?? '');
    assert21d($row !== false, "Template existe: {$key}", "Template ausente: {$key}");
    foreach ([
        'dcx-email-premium' => 'marcador premium',
        '{{festival_logo_url}}' => 'logo Danca Carajas',
        '{{producer_logo_url}}' => 'logo JA Producoes',
        'box-shadow:0 18px 48px' => 'profundidade visual',
        'background:#f4c400' => 'amarelo institucional',
        'border-radius:18px' => 'card premium',
        'E-mail transacional' => 'selo transacional',
        'Link seguro' => 'icone/selo de seguranca',
    ] as $needle => $label) {
        assert21d(str_contains($html, $needle), "Template {$key} contem {$label}", "Template {$key} sem {$label}");
    }
}

$migration21c = (string) file_get_contents($root . '/scripts/run_migration_etapa21c_email_templates_resend.php');
assert21d(str_contains($migration21c, 'dcx-email-premium'), 'Migration 21C preserva templates premium', 'Migration 21C pode sobrescrever templates premium');

echo "\nResultado: {$passes} PASS, " . count($failures) . " FAIL\n";
if ($failures !== []) {
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}
exit(0);
