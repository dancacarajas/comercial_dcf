<?php

declare(strict_types=1);

/**
 * Validacao - ETAPA 21E: preview visual dos templates de e-mail.
 */

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');
require_once $root . '/app/Helpers/security.php';

$pass = 0;
$fail = 0;

function check21e(bool $condition, string $ok, string $bad): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "[PASS] {$ok}\n";
        return;
    }

    $fail++;
    echo "[FAIL] {$bad}\n";
}

echo "== ETAPA 21E - Preview de Templates de E-mail ==\n\n";

$files = [
    'app/Controllers/EmailSettingsController.php',
    'app/Views/email_settings/templates.php',
];

foreach ($files as $file) {
    $path = $root . '/' . $file;
    check21e(is_file($path), 'Arquivo existe: ' . $file, 'Arquivo ausente: ' . $file);
    if (is_file($path) && function_exists('exec')) {
        $output = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
        check21e($code === 0, 'php -l ' . $file, 'Erro de sintaxe em ' . $file . ': ' . implode(' | ', $output));
    }
}

$controller = (string) file_get_contents($root . '/app/Controllers/EmailSettingsController.php');
$view = (string) file_get_contents($root . '/app/Views/email_settings/templates.php');

foreach ([
    'prepareTemplatePreviews' => 'Controller prepara previews',
    'previewVariables' => 'Controller monta variaveis de exemplo',
    'preview_html' => 'Controller envia HTML renderizado',
    'preview_subject' => 'Controller envia assunto renderizado',
    'festival_logo_url' => 'Preview injeta logo Danca Carajas',
    'producer_logo_url' => 'Preview injeta logo JA Producoes',
] as $needle => $label) {
    check21e(str_contains($controller, $needle), $label, 'Controller nao cobre: ' . $needle);
}

foreach ([
    'email-template-card' => 'View usa cards por gatilho',
    'Preview do template' => 'View exibe acao de preview',
    'iframe class="email-template-frame"' => 'View renderiza iframe isolado',
    'srcdoc="' => 'View usa srcdoc para HTML do template',
    'Texto fallback' => 'View exibe fallback texto',
    'Assunto renderizado' => 'View exibe assunto renderizado',
    'data-lucide="eye"' => 'View usa icone de preview',
    'data-lucide="monitor"' => 'View usa icone de monitor',
    '/settings/email/logs' => 'View mantem atalho para logs',
] as $needle => $label) {
    check21e(str_contains($view, $needle), $label, 'View nao cobre: ' . $needle);
}

echo "\nResultado: {$pass} PASS, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
