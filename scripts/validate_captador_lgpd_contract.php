<?php

declare(strict_types=1);

/**
 * Validacao - termo LGPD do captador externo.
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Core\Database;

$pass = 0;
$fail = 0;

function checkLgpd(bool $condition, string $ok, string $bad): void
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

echo "== Termo LGPD - Captador Externo ==\n\n";

$files = [
    'app/Data/CaptadorLgpdTemplate.php',
    'app/Data/templates/captador_lgpd_uso_dados.html',
    'app/Services/ContractTemplateSeeder.php',
    'scripts/seed_captador_lgpd_contract.php',
    'scripts/validate_captador_lgpd_contract.php',
];

foreach ($files as $file) {
    $path = $root . '/' . $file;
    checkLgpd(is_file($path), 'Arquivo existe: ' . $file, 'Arquivo ausente: ' . $file);
    if (is_file($path) && str_ends_with($file, '.php') && function_exists('exec')) {
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        checkLgpd($code === 0, 'php -l ' . $file, 'Erro de sintaxe em ' . $file . ': ' . implode(' | ', $out));
    }
}

$row = Database::run(
    "SELECT * FROM contract_templates WHERE template_key = 'captador_lgpd_uso_dados' LIMIT 1"
)->fetch();

checkLgpd($row !== false, 'Modelo existe no banco', 'Modelo nao encontrado no banco');
if ($row !== false) {
    checkLgpd((string) $row['title'] === 'Termo de Ciencia LGPD e Uso de Dados - Captador Externo', 'Titulo correto', 'Titulo incorreto');
    checkLgpd((string) $row['template_type'] === 'outro', 'Tipo outro', 'Tipo incorreto');
    checkLgpd((string) $row['status'] === 'ativo', 'Status ativo', 'Status nao ativo');
    checkLgpd((int) $row['collector_signature_stage_enabled'] === 1, 'Assinatura do captador habilitada', 'Assinatura do captador nao habilitada');
    checkLgpd((int) $row['collector_signature_required'] === 1, 'Termo obrigatorio na assinatura', 'Termo nao obrigatorio');
    checkLgpd((int) $row['collector_signature_order'] === 30, 'Ordem de assinatura 30', 'Ordem de assinatura diferente de 30');

    $html = (string) ($row['content_html'] ?? '');
    foreach ([
        'TERMO DE CIENCIA LGPD',
        'Lei Geral de Protecao de Dados Pessoais',
        '{{collector.name}}',
        '{{application.application_number}}',
        '{{organization.name}}',
        '{{signature.request_number}}',
        'dados pessoais',
        'incidente de seguranca',
        'assina eletronicamente',
    ] as $needle) {
        checkLgpd(str_contains($html, $needle), 'Conteudo cobre: ' . $needle, 'Conteudo nao cobre: ' . $needle);
    }
}

$sequence = Database::run(
    "SELECT template_key, collector_signature_order
       FROM contract_templates
      WHERE template_key IN ('captador_externo_padrao', 'captador_confidencialidade_nda', 'captador_lgpd_uso_dados')
      ORDER BY collector_signature_order ASC"
)->fetchAll();
$keys = array_column($sequence, 'template_key');
checkLgpd($keys === ['captador_externo_padrao', 'captador_confidencialidade_nda', 'captador_lgpd_uso_dados'], 'Sequencia contrato/NDA/LGPD correta', 'Sequencia de assinatura incorreta');

echo "\nResultado: {$pass} PASS, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
