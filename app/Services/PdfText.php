<?php

declare(strict_types=1);

namespace App\Services;

/** Codificação de texto para PDF (ISO-8859-1 / Windows-1252 — acentuação PT-BR). */
final class PdfText
{
    public static function encode(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $encoded = @mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        if ($encoded === false || $encoded === '') {
            $encoded = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) ?: $text;
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    public static function literal(string $text): string
    {
        return '(' . self::encode($text) . ')';
    }
}
