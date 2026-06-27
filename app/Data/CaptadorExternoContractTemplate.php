<?php

declare(strict_types=1);

namespace App\Data;

/** Metadados e conteúdo HTML do contrato padrão de captador externo. */
final class CaptadorExternoContractTemplate
{
    public const TEMPLATE_KEY = 'captador_externo_padrao';

    public const TEMPLATE_TYPE = 'contrato_captador';

    public const TITLE = 'Contrato de Prestação de Serviços de Captação de Recursos — Captador Externo';

    public const DESCRIPTION = 'Contrato completo JA Produções Artísticas LTDA / Dança Carajás Festival — captador externo.';

    public static function contentHtml(): string
    {
        $path = __DIR__ . '/templates/captador_externo_padrao.html';
        if (!is_file($path)) {
            return '';
        }
        $html = file_get_contents($path);

        return is_string($html) ? $html : '';
    }
}
