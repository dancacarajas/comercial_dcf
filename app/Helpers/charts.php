<?php

declare(strict_types=1);

if (!function_exists('chart_pct_width')) {
    /** Largura percentual segura para barras (0–100). */
    function chart_pct_width(float $value, float $max): float
    {
        if ($max <= 0) {
            return $value > 0 ? 100.0 : 0.0;
        }

        return min(100.0, max(0.0, ($value / $max) * 100));
    }
}

if (!function_exists('chart_parse_money')) {
    /** Converte "R$ 1.234,56" em float. */
    function chart_parse_money(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '' || $s === '—') {
            return 0.0;
        }
        $s = preg_replace('/[^\d,.-]/', '', $s) ?? '';
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? (float) $s : 0.0;
    }
}

if (!function_exists('chart_parse_number')) {
    function chart_parse_number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '' || $s === '—') {
            return 0.0;
        }
        $s = str_replace(['.', ','], ['', '.'], preg_replace('/[^\d,.-]/', '', $s) ?? '');

        return is_numeric($s) ? (float) $s : 0.0;
    }
}

if (!function_exists('chart_metric_find')) {
    /**
     * @param array<int, array{label:string,value:mixed,type?:string}> $metrics
     */
    function chart_metric_find(array $metrics, string $labelNeedle): ?array
    {
        $needle = mb_strtolower($labelNeedle);
        foreach ($metrics as $metric) {
            if (str_contains(mb_strtolower((string) ($metric['label'] ?? '')), $needle)) {
                return $metric;
            }
        }

        return null;
    }
}

if (!function_exists('chart_metric_number')) {
    function chart_metric_number(array $metrics, string $labelNeedle): float
    {
        $m = chart_metric_find($metrics, $labelNeedle);
        if ($m === null) {
            return 0.0;
        }

        return ($m['type'] ?? '') === 'money'
            ? chart_parse_money($m['value'])
            : chart_parse_number($m['value']);
    }
}

if (!function_exists('chart_conversion_pct')) {
    function chart_conversion_pct(float $current, float $previous): ?string
    {
        if ($previous <= 0) {
            return null;
        }

        return number_format(($current / $previous) * 100, 1, ',', '.') . '%';
    }
}

if (!function_exists('chart_donut_stops')) {
    /**
     * @param array<int, array{value:float, color?:string}> $segments
     */
    function chart_donut_stops(array $segments): string
    {
        $total = 0.0;
        foreach ($segments as $seg) {
            $total += max(0, (float) ($seg['value'] ?? 0));
        }
        if ($total <= 0) {
            return '#ededed 0deg 360deg';
        }

        $offset = 0.0;
        $parts  = [];
        foreach ($segments as $seg) {
            $val = max(0, (float) ($seg['value'] ?? 0));
            if ($val <= 0) {
                continue;
            }
            $deg = ($val / $total) * 360;
            $color = (string) ($seg['color'] ?? '#f7c400');
            $parts[] = $color . ' ' . $offset . 'deg ' . ($offset + $deg) . 'deg';
            $offset += $deg;
        }

        return $parts === [] ? '#ededed 0deg 360deg' : implode(', ', $parts);
    }
}
