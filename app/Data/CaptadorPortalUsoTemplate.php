<?php

declare(strict_types=1);

namespace App\Data;

/** Metadados e conteudo HTML do termo de uso do portal do captador. */
final class CaptadorPortalUsoTemplate
{
    public const TEMPLATE_KEY = 'captador_termo_uso_portal';

    public const TEMPLATE_TYPE = 'outro';

    public const TITLE = 'Termo de Uso do Portal do Captador - Captador Externo';

    public const DESCRIPTION = 'Termo de uso, acesso, seguranca e responsabilidades operacionais do portal do captador.';

    public static function contentHtml(): string
    {
        $path = __DIR__ . '/templates/captador_termo_uso_portal.html';
        if (!is_file($path)) {
            return '';
        }
        $html = file_get_contents($path);

        return is_string($html) ? $html : '';
    }
}
