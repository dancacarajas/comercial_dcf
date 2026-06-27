<?php

declare(strict_types=1);

if (!function_exists('app_timezone')) {
    function app_timezone(): DateTimeZone
    {
        static $tz = null;
        if ($tz instanceof DateTimeZone) {
            return $tz;
        }

        $name = (string) ((require dirname(__DIR__, 2) . '/config/app.php')['timezone'] ?? 'America/Sao_Paulo');
        if ($name === '') {
            $name = 'America/Sao_Paulo';
        }

        try {
            $tz = new DateTimeZone($name);
        } catch (\Throwable) {
            $tz = new DateTimeZone('America/Sao_Paulo');
        }

        return $tz;
    }
}

if (!function_exists('format_datetime_br')) {
    /**
     * Converte datetime gravado no banco (UTC) para exibição em horário brasileiro.
     */
    function format_datetime_br(mixed $value, string $format = 'd/m/Y H:i:s'): string
    {
        if ($value === null) {
            return '—';
        }

        $value = trim((string) $value);
        if ($value === '' || $value === '—') {
            return '—';
        }

        try {
            $utc = new DateTimeImmutable($value, new DateTimeZone('UTC'));

            return $utc->setTimezone(app_timezone())->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }
}

if (!function_exists('app_now_utc')) {
    /** Timestamp atual em UTC para persistência no banco. */
    function app_now_utc(): string
    {
        return (new DateTimeImmutable('now', app_timezone()))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }
}
