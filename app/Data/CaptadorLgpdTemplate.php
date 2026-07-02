<?php

declare(strict_types=1);

namespace App\Data;

/** Metadados e conteudo HTML do termo LGPD do captador externo. */
final class CaptadorLgpdTemplate
{
    public const TEMPLATE_KEY = 'captador_lgpd_uso_dados';

    public const TEMPLATE_TYPE = 'outro';

    public const TITLE = 'Termo de Ciencia LGPD e Uso de Dados - Captador Externo';

    public const DESCRIPTION = 'Termo de ciencia sobre tratamento de dados pessoais, uso do portal e responsabilidades LGPD do captador externo.';

    public static function contentHtml(): string
    {
        $path = __DIR__ . '/templates/captador_lgpd_uso_dados.html';
        if (!is_file($path)) {
            return '';
        }
        $html = file_get_contents($path);

        return is_string($html) ? $html : '';
    }
}
