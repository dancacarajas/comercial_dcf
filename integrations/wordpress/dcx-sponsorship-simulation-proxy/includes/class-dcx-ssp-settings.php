<?php
declare(strict_types=1);

final class Dcx_Ssp_Settings
{
    /**
     * @return array{enabled:bool,trusted_proxies:list<string>,timeout_seconds:int}
     */
    public static function all(): array
    {
        $defaults = [
            'enabled' => '1',
            'trusted_proxies' => [],
            'timeout_seconds' => 12,
        ];

        $saved = [];
        if (function_exists('get_option')) {
            $raw = get_option('dcx_ssp_settings', []);
            if (is_array($raw)) {
                // Strip forbidden keys even if someone injected them.
                unset($raw['token'], $raw['secret'], $raw['crm_token'], $raw['crm_endpoint'], $raw['endpoint']);
                $saved = $raw;
            }
        }

        $merged = array_merge($defaults, $saved);

        $proxies = $merged['trusted_proxies'] ?? [];
        if (is_string($proxies)) {
            $proxies = array_filter(array_map('trim', explode(',', $proxies)));
        }
        if (!is_array($proxies)) {
            $proxies = [];
        }

        $timeout = (int) ($merged['timeout_seconds'] ?? 12);
        if ($timeout < 1) {
            $timeout = 12;
        }
        if ($timeout > 30) {
            $timeout = 30;
        }

        return [
            'enabled' => ((string) ($merged['enabled'] ?? '1')) === '1',
            'trusted_proxies' => array_values(array_map('strval', $proxies)),
            'timeout_seconds' => $timeout,
        ];
    }
}
