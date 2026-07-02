<?php

declare(strict_types=1);

namespace App\Data;

/** Metadados e conteudo HTML do termo de confidencialidade do captador externo. */
final class CaptadorConfidencialidadeTemplate
{
    public const TEMPLATE_KEY = 'captador_confidencialidade_nda';

    public const TEMPLATE_TYPE = 'termo_confidencialidade';

    public const TITLE = 'Termo de Confidencialidade e Sigilo - Captador Externo';

    public const DESCRIPTION = 'Termo de confidencialidade, sigilo e uso restrito de informacoes para captador externo.';

    public static function contentHtml(): string
    {
        $path = __DIR__ . '/templates/captador_confidencialidade_nda.html';
        if (!is_file($path)) {
            return '';
        }
        $html = file_get_contents($path);

        return is_string($html) ? $html : '';
    }
}
