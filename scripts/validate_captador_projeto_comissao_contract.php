<?php

declare(strict_types=1);

/**
 * Validacao - termo de projeto, PRONAC, comissao e territorio do captador.
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Core\Database;

$pass = 0;
$fail = 0;

function checkProjetoComissao(bool $condition, string $ok, string $bad): void
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

echo "== Termo de Projeto, PRONAC, Comissao e Territorio - Captador Externo ==\n\n";

$files = [
    'app/Data/CaptadorProjetoComissaoTemplate.php',
    'app/Data/templates/captador_termo_projeto_comissao_territorio.html',
    'app/Services/ContractTemplateSeeder.php',
    'scripts/seed_captador_projeto_comissao_contract.php',
    'scripts/validate_captador_projeto_comissao_contract.php',
];

foreach ($files as $file) {
    $path = $root . '/' . $file;
    checkProjetoComissao(is_file($path), 'Arquivo existe: ' . $file, 'Arquivo ausente: ' . $file);
    if (is_file($path) && str_ends_with($file, '.php') && function_exists('exec')) {
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        checkProjetoComissao($code === 0, 'php -l ' . $file, 'Erro de sintaxe em ' . $file . ': ' . implode(' | ', $out));
    }
}

$row = Database::run(
    "SELECT * FROM contract_templates WHERE template_key = 'captador_termo_projeto_comissao_territorio' LIMIT 1"
)->fetch();

checkProjetoComissao($row !== false, 'Modelo existe no banco', 'Modelo nao encontrado no banco');
if ($row !== false) {
    checkProjetoComissao((string) $row['title'] === 'Termo de Projeto, PRONAC, Comissao e Territorio - Captador Externo', 'Titulo correto', 'Titulo incorreto');
    checkProjetoComissao((string) $row['template_type'] === 'autorizacao_captador', 'Tipo autorizacao_captador', 'Tipo incorreto');
    checkProjetoComissao((string) $row['status'] === 'ativo', 'Status ativo', 'Status nao ativo');
    checkProjetoComissao((int) $row['collector_signature_stage_enabled'] === 1, 'Assinatura do captador habilitada', 'Assinatura do captador nao habilitada');
    checkProjetoComissao((int) $row['collector_signature_required'] === 1, 'Termo obrigatorio na assinatura', 'Termo nao obrigatorio');
    checkProjetoComissao((int) $row['collector_signature_order'] === 60, 'Ordem de assinatura 60', 'Ordem de assinatura diferente de 60');

    $html = (string) ($row['content_html'] ?? '');
    foreach ([
        'TERMO COMPLEMENTAR DE PROJETO',
        'PRONAC',
        'COMISSAO',
        'TERRITORIO DE ATUACAO',
        'recebimento financeiro',
        'rateio',
        '{{collector.name}}',
        '{{application.application_number}}',
        '{{organization.name}}',
        '{{signature.request_number}}',
        'assina eletronicamente',
    ] as $needle) {
        checkProjetoComissao(str_contains($html, $needle), 'Conteudo cobre: ' . $needle, 'Conteudo nao cobre: ' . $needle);
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
          'captador_termo_uso_portal',
          'captador_termo_projeto_comissao_territorio'
      )
      ORDER BY collector_signature_order ASC"
)->fetchAll();
$keys = array_column($sequence, 'template_key');
checkProjetoComissao(
    $keys === [
        'captador_externo_padrao',
        'captador_confidencialidade_nda',
        'captador_lgpd_uso_dados',
        'captador_codigo_conduta_anticorrupcao',
        'captador_termo_uso_portal',
        'captador_termo_projeto_comissao_territorio',
    ],
    'Sequencia completa de documentos do captador correta',
    'Sequencia completa de assinatura incorreta'
);

echo "\nResultado: {$pass} PASS, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
