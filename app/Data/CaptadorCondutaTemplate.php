<?php

declare(strict_types=1);

namespace App\Data;

/** Metadados e conteudo HTML do codigo de conduta do captador externo. */
final class CaptadorCondutaTemplate
{
    public const TEMPLATE_KEY = 'captador_codigo_conduta_anticorrupcao';

    public const TEMPLATE_TYPE = 'outro';

    public const TITLE = 'Codigo de Conduta, Anticorrupcao e Regras de Abordagem - Captador Externo';

    public const DESCRIPTION = 'Codigo de conduta, integridade, anticorrupcao e regras de abordagem comercial para captador externo.';

    public static function contentHtml(): string
    {
        $path = __DIR__ . '/templates/captador_codigo_conduta_anticorrupcao.html';
        if (!is_file($path)) {
            return '';
        }
        $html = file_get_contents($path);

        return is_string($html) ? $html : '';
    }
}
