<?php

declare(strict_types=1);

namespace App\Helpers;

/** Formatação compartilhada para documento contratual (HTML/PDF). */
final class ContractDocumentHelper
{
    public static function canonicalAutorizacaoCaptadorTitle(): string
    {
        return 'Autorização de Captação — Captador Externo';
    }

    public static function canonicalAutorizacaoCaptadorHtml(): string
    {
        return '<h2>Autorização de Captação</h2>'
            . '<p>Pelo presente instrumento, <strong>{{collector.name}}</strong>, inscrito(a) sob o documento '
            . '<strong>{{collector.document_number}}</strong>, residente em {{collector.city_state}}, e-mail {{collector.email}}, '
            . 'autoriza o Dança Carajás Festival a credenciá-lo(a) como captador externo de recursos, conforme candidatura '
            . '<strong>{{application.application_number}}</strong>.</p>'
            . '<p>Data: {{date.today}}</p>'
            . '<p>{{organization.name}}</p>';
    }

    /**
     * Corrige textos corrompidos por importação SQL com charset incorreto (ex.: Autoriza????o).
     */
    public static function repairLegacyUtf8(string $text): string
    {
        if ($text === '' || !str_contains($text, '?')) {
            return $text;
        }

        $replacements = [
            'Autoriza????o de Capta????o ??? Captador Externo' => self::canonicalAutorizacaoCaptadorTitle(),
            'Autoriza????o de Capta????o' => 'Autorização de Captação',
            'Autoriza????o' => 'Autorização',
            'Capta????o' => 'Captação',
            'Dan??a Caraj??s' => 'Dança Carajás',
            'Dan??a' => 'Dança',
            'Caraj??s' => 'Carajás',
            'credenci??-lo' => 'credenciá-lo',
            'credenci??-la' => 'credenciá-la',
            'contrata????o' => 'contratação',
            'assinatura eletr??nica' => 'assinatura eletrônica',
            'jur??dico' => 'jurídico',
            'respons??vel' => 'responsável',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    public static function normalizeTemplate(array $template): array
    {
        if (($template['template_type'] ?? '') !== 'autorizacao_captador') {
            return $template;
        }

        $title = (string) ($template['title'] ?? '');
        $html = (string) ($template['content_html'] ?? '');
        if (str_contains($title, '?') || str_contains($html, '?')) {
            $template['title'] = self::canonicalAutorizacaoCaptadorTitle();
            $template['content_html'] = self::canonicalAutorizacaoCaptadorHtml();
        }

        return $template;
    }

    public static function normalizeDocumentText(string $text): string
    {
        return self::repairLegacyUtf8($text);
    }
    public static function maskDocument(string $doc): string
    {
        $digits = preg_replace('/\D+/', '', $doc) ?? '';
        if (strlen($digits) === 11) {
            return '***.***.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
        }
        if (strlen($digits) === 14) {
            return '**.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/****-' . substr($digits, 12, 2);
        }

        return $doc !== '' ? '***' : '—';
    }

    public static function formatVerificationCode(string $hash): string
    {
        $clean = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $hash) ?? '');
        if (strlen($clean) < 16) {
            return strtoupper(substr($hash, 0, 16));
        }

        return implode('-', str_split(substr($clean, 0, 16), 4));
    }

    public static function documentSubtitle(string $title): string
    {
        $title = trim($title);
        if (stripos($title, 'prestação de serviços') !== false || stripos($title, 'contrato de') !== false) {
            return 'Instrumento contratual de prestação de serviços de captação de recursos e autorização de atuação como captador externo do Dança Carajás Festival.';
        }
        if (stripos($title, 'captação') !== false || stripos($title, 'captador') !== false) {
            return 'Instrumento particular de autorização limitada para atuação como captador externo de recursos do Dança Carajás Festival.';
        }

        return 'Instrumento contratual emitido pela plataforma Dança Carajás Captação.';
    }

    /** @return list<string> */
    public static function titleLines(string $title): array
    {
        $title = trim($title);
        if (stripos($title, 'prestação de serviços') !== false || stripos($title, 'contrato de') !== false) {
            return [
                'CONTRATO DE PRESTAÇÃO DE SERVIÇOS',
                'CAPTAÇÃO DE RECURSOS — CAPTADOR EXTERNO',
            ];
        }
        if (preg_match('/capta/i', $title)) {
            return [
                'AUTORIZAÇÃO DE CAPTAÇÃO DE RECURSOS',
                'CAPTADOR EXTERNO',
            ];
        }

        return [mb_strtoupper($title)];
    }

    public static function brandingUrl(string $relativePath): string
    {
        return '/' . ltrim($relativePath, '/');
    }
}
