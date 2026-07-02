<?php

declare(strict_types=1);

namespace App\Data;

/** Metadados e conteudo HTML do termo de projeto, PRONAC, comissao e territorio do captador. */
final class CaptadorProjetoComissaoTemplate
{
    public const TEMPLATE_KEY = 'captador_termo_projeto_comissao_territorio';

    public const TEMPLATE_TYPE = 'autorizacao_captador';

    public const TITLE = 'Termo de Projeto, PRONAC, Comissao e Territorio - Captador Externo';

    public const DESCRIPTION = 'Termo complementar de projeto incentivado, PRONAC, regras de comissao, carteira e territorio de atuacao do captador externo.';

    public static function contentHtml(): string
    {
        $path = __DIR__ . '/templates/captador_termo_projeto_comissao_territorio.html';
        if (!is_file($path)) {
            return '';
        }
        $html = file_get_contents($path);

        return is_string($html) ? $html : '';
    }
}
