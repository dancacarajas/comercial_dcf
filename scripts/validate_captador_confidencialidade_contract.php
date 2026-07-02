<?php

declare(strict_types=1);

/**
 * Validacao - termo de confidencialidade do captador externo.
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Core\Database;

$pass = 0;
$fail = 0;

function checkNda(bool $condition, string $ok, string $bad): void
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

echo "== Termo de Confidencialidade - Captador Externo ==\n\n";

$files = [
    'app/Data/CaptadorConfidencialidadeTemplate.php',
    'app/Data/templates/captador_confidencialidade_nda.html',
    'app/Services/ContractTemplateSeeder.php',
    'scripts/seed_captador_confidencialidade_contract.php',
    'scripts/validate_captador_confidencialidade_contract.php',
];

foreach ($files as $file) {
    $path = $root . '/' . $file;
    checkNda(is_file($path), 'Arquivo existe: ' . $file, 'Arquivo ausente: ' . $file);
    if (is_file($path) && str_ends_with($file, '.php') && function_exists('exec')) {
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        checkNda($code === 0, 'php -l ' . $file, 'Erro de sintaxe em ' . $file . ': ' . implode(' | ', $out));
    }
}

$row = Database::run(
    "SELECT * FROM contract_templates WHERE template_key = 'captador_confidencialidade_nda' LIMIT 1"
)->fetch();

checkNda($row !== false, 'Modelo existe no banco', 'Modelo nao encontrado no banco');
if ($row !== false) {
    checkNda((string) $row['title'] === 'Termo de Confidencialidade e Sigilo - Captador Externo', 'Titulo correto', 'Titulo incorreto');
    checkNda((string) $row['template_type'] === 'termo_confidencialidade', 'Tipo termo_confidencialidade', 'Tipo incorreto');
    checkNda((string) $row['status'] === 'ativo', 'Status ativo', 'Status nao ativo');
    checkNda((int) $row['collector_signature_stage_enabled'] === 1, 'Assinatura do captador habilitada', 'Assinatura do captador nao habilitada');
    checkNda((int) $row['collector_signature_required'] === 1, 'Termo obrigatorio na assinatura', 'Termo nao obrigatorio');
    checkNda((int) $row['collector_signature_order'] === 20, 'Ordem de assinatura 20', 'Ordem de assinatura diferente de 20');

    $html = (string) ($row['content_html'] ?? '');
    foreach ([
        'TERMO DE CONFIDENCIALIDADE',
        '{{collector.name}}',
        '{{application.application_number}}',
        '{{organization.name}}',
        '{{signature.request_number}}',
        'LGPD',
        'assinado eletronicamente',
    ] as $needle) {
        checkNda(str_contains($html, $needle), 'Conteudo cobre: ' . $needle, 'Conteudo nao cobre: ' . $needle);
    }
}

echo "\nResultado: {$pass} PASS, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
