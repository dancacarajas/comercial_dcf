<?php

declare(strict_types=1);

/**
 * Validacao - termo de uso do portal do captador.
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Core\Database;

$pass = 0;
$fail = 0;

function checkPortalUso(bool $condition, string $ok, string $bad): void
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

echo "== Termo de Uso do Portal - Captador Externo ==\n\n";

$files = [
    'app/Data/CaptadorPortalUsoTemplate.php',
    'app/Data/templates/captador_termo_uso_portal.html',
    'app/Services/ContractTemplateSeeder.php',
    'scripts/seed_captador_portal_uso_contract.php',
    'scripts/validate_captador_portal_uso_contract.php',
];

foreach ($files as $file) {
    $path = $root . '/' . $file;
    checkPortalUso(is_file($path), 'Arquivo existe: ' . $file, 'Arquivo ausente: ' . $file);
    if (is_file($path) && str_ends_with($file, '.php') && function_exists('exec')) {
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        checkPortalUso($code === 0, 'php -l ' . $file, 'Erro de sintaxe em ' . $file . ': ' . implode(' | ', $out));
    }
}

$row = Database::run(
    "SELECT * FROM contract_templates WHERE template_key = 'captador_termo_uso_portal' LIMIT 1"
)->fetch();

checkPortalUso($row !== false, 'Modelo existe no banco', 'Modelo nao encontrado no banco');
if ($row !== false) {
    checkPortalUso((string) $row['title'] === 'Termo de Uso do Portal do Captador - Captador Externo', 'Titulo correto', 'Titulo incorreto');
    checkPortalUso((string) $row['template_type'] === 'outro', 'Tipo outro', 'Tipo incorreto');
    checkPortalUso((string) $row['status'] === 'ativo', 'Status ativo', 'Status nao ativo');
    checkPortalUso((int) $row['collector_signature_stage_enabled'] === 1, 'Assinatura do captador habilitada', 'Assinatura do captador nao habilitada');
    checkPortalUso((int) $row['collector_signature_required'] === 1, 'Termo obrigatorio na assinatura', 'Termo nao obrigatorio');
    checkPortalUso((int) $row['collector_signature_order'] === 50, 'Ordem de assinatura 50', 'Ordem de assinatura diferente de 50');

    $html = (string) ($row['content_html'] ?? '');
    foreach ([
        'TERMO DE USO DO PORTAL',
        'credenciais',
        'senha',
        'logs',
        'comissoes',
        '{{collector.name}}',
        '{{application.application_number}}',
        '{{organization.name}}',
        '{{signature.request_number}}',
        'assina eletronicamente',
    ] as $needle) {
        checkPortalUso(str_contains($html, $needle), 'Conteudo cobre: ' . $needle, 'Conteudo nao cobre: ' . $needle);
    }
}

$sequence = Database::run(
    "SELECT template_key, collector_signature_order
       FROM contract_templates
      WHERE template_key IN (
          'captador_externo_padrao',
          'captador_confidencialidade_nda',
          'captador_lgpd_uso_dados',
          'captador_codigo_conduta_anticorrupcao',
          'captador_termo_uso_portal'
      )
      ORDER BY collector_signature_order ASC"
)->fetchAll();
$keys = array_column($sequence, 'template_key');
checkPortalUso(
    $keys === [
        'captador_externo_padrao',
        'captador_confidencialidade_nda',
        'captador_lgpd_uso_dados',
        'captador_codigo_conduta_anticorrupcao',
        'captador_termo_uso_portal',
    ],
    'Sequencia contrato/NDA/LGPD/conduta/portal correta',
    'Sequencia de assinatura incorreta'
);

echo "\nResultado: {$pass} PASS, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
