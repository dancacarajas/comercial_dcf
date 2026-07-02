<?php

declare(strict_types=1);

/**
 * Validacao - codigo de conduta do captador externo.
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Core\Database;

$pass = 0;
$fail = 0;

function checkConduta(bool $condition, string $ok, string $bad): void
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

echo "== Codigo de Conduta - Captador Externo ==\n\n";

$files = [
    'app/Data/CaptadorCondutaTemplate.php',
    'app/Data/templates/captador_codigo_conduta_anticorrupcao.html',
    'app/Services/ContractTemplateSeeder.php',
    'scripts/seed_captador_conduta_contract.php',
    'scripts/validate_captador_conduta_contract.php',
];

foreach ($files as $file) {
    $path = $root . '/' . $file;
    checkConduta(is_file($path), 'Arquivo existe: ' . $file, 'Arquivo ausente: ' . $file);
    if (is_file($path) && str_ends_with($file, '.php') && function_exists('exec')) {
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        checkConduta($code === 0, 'php -l ' . $file, 'Erro de sintaxe em ' . $file . ': ' . implode(' | ', $out));
    }
}

$row = Database::run(
    "SELECT * FROM contract_templates WHERE template_key = 'captador_codigo_conduta_anticorrupcao' LIMIT 1"
)->fetch();

checkConduta($row !== false, 'Modelo existe no banco', 'Modelo nao encontrado no banco');
if ($row !== false) {
    checkConduta((string) $row['title'] === 'Codigo de Conduta, Anticorrupcao e Regras de Abordagem - Captador Externo', 'Titulo correto', 'Titulo incorreto');
    checkConduta((string) $row['template_type'] === 'outro', 'Tipo outro', 'Tipo incorreto');
    checkConduta((string) $row['status'] === 'ativo', 'Status ativo', 'Status nao ativo');
    checkConduta((int) $row['collector_signature_stage_enabled'] === 1, 'Assinatura do captador habilitada', 'Assinatura do captador nao habilitada');
    checkConduta((int) $row['collector_signature_required'] === 1, 'Codigo obrigatorio na assinatura', 'Codigo nao obrigatorio');
    checkConduta((int) $row['collector_signature_order'] === 40, 'Ordem de assinatura 40', 'Ordem de assinatura diferente de 40');

    $html = (string) ($row['content_html'] ?? '');
    foreach ([
        'CODIGO DE CONDUTA',
        'ANTICORRUPCAO',
        'REGRAS DE ABORDAGEM COMERCIAL',
        '{{collector.name}}',
        '{{application.application_number}}',
        '{{organization.name}}',
        '{{signature.request_number}}',
        'vantagem indevida',
        'conflito de interesses',
        'assina eletronicamente',
    ] as $needle) {
        checkConduta(str_contains($html, $needle), 'Conteudo cobre: ' . $needle, 'Conteudo nao cobre: ' . $needle);
    }
}

$sequence = Database::run(
    "SELECT template_key, collector_signature_order
       FROM contract_templates
      WHERE template_key IN (
          'captador_externo_padrao',
          'captador_confidencialidade_nda',
          'captador_lgpd_uso_dados',
          'captador_codigo_conduta_anticorrupcao'
      )
      ORDER BY collector_signature_order ASC"
)->fetchAll();
$keys = array_column($sequence, 'template_key');
checkConduta(
    $keys === [
        'captador_externo_padrao',
        'captador_confidencialidade_nda',
        'captador_lgpd_uso_dados',
        'captador_codigo_conduta_anticorrupcao',
    ],
    'Sequencia contrato/NDA/LGPD/conduta correta',
    'Sequencia de assinatura incorreta'
);

echo "\nResultado: {$pass} PASS, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
