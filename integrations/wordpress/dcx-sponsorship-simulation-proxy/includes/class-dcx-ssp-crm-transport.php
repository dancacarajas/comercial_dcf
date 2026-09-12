<?php
declare(strict_types=1);

final class Dcx_Ssp_Crm_Transport
{
    /**
     * @param array<string, mixed> $envelopeB
     * @param callable|null $httpClient function(string $url, array $args): array{http:int,body:?array,raw:string,headers_sent:array}
     * @return array{http:int,body:?array,raw:string,headers_sent:array,duration_ms:int}
     */
    public static function post(
        array $envelopeB,
        string $token,
        string $clientIp,
        string $userAgent,
        int $timeoutSeconds,
        ?callable $httpClient = null
    ): array {
        $url = Dcx_Ssp_Constants::CRM_ENDPOINT;
        if (!self::isAllowedEndpoint($url)) {
            return [
                'http' => 503,
                'body' => null,
                'raw' => '',
                'headers_sent' => [],
                'duration_ms' => 0,
            ];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'X-DCF-Lead-Token' => $token,
            'X-Forwarded-For' => $clientIp,
            'User-Agent' => self::sanitizeUa($userAgent),
        ];

        $args = [
            'method' => 'POST',
            'timeout' => $timeoutSeconds,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => $headers,
            'body' => json_encode($envelopeB, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $t0 = (int) (microtime(true) * 1000);
        if ($httpClient !== null) {
            $res = $httpClient($url, $args);
        } elseif (function_exists('wp_remote_post')) {
            $response = wp_remote_post($url, $args);
            if (is_wp_error($response)) {
                return [
                    'http' => 503,
                    'body' => null,
                    'raw' => $response->get_error_message(),
                    'headers_sent' => $headers,
                    'duration_ms' => (int) (microtime(true) * 1000) - $t0,
                ];
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            $raw = (string) wp_remote_retrieve_body($response);
            $decoded = json_decode($raw, true);
            $res = [
                'http' => $code,
                'body' => is_array($decoded) ? $decoded : null,
                'raw' => $raw,
                'headers_sent' => $headers,
            ];
        } else {
            $res = self::curlPost($url, $args, $headers);
        }
        $res['duration_ms'] = (int) (microtime(true) * 1000) - $t0;
        $res['headers_sent'] = $headers;

        return $res;
    }

    /**
     * Inspect transport policy without network.
     *
     * @return array{sslverify:bool,redirection:int,timeout:int,endpoint:string}
     */
    public static function policy(int $timeoutSeconds): array
    {
        return [
            'sslverify' => true,
            'redirection' => 0,
            'timeout' => $timeoutSeconds,
            'endpoint' => Dcx_Ssp_Constants::CRM_ENDPOINT,
        ];
    }

    /** Endpoint permitido: exatamente o literal HTTPS canônico. */
    public static function isAllowedEndpoint(string $url): bool
    {
        return $url === Dcx_Ssp_Constants::CRM_ENDPOINT
            && str_starts_with($url, 'https://');
    }

    private static function sanitizeUa(string $ua): string
    {
        $ua = trim(preg_replace('/[\r\n]+/', ' ', $ua) ?? '');
        if (strlen($ua) > 512) {
            $ua = substr($ua, 0, 512);
        }

        return $ua !== '' ? $ua : 'DCX-SSP/1.0';
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, string> $headers
     * @return array{http:int,body:?array,raw:string,headers_sent:array}
     */
    private static function curlPost(string $url, array $args, array $headers): array
    {
        if (!function_exists('curl_init')) {
            return ['http' => 503, 'body' => null, 'raw' => 'curl missing', 'headers_sent' => $headers];
        }
        $ch = curl_init($url);
        $hdr = [];
        foreach ($headers as $k => $v) {
            $hdr[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $args['body'],
            CURLOPT_HTTPHEADER => $hdr,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $args['timeout'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            $raw = curl_error($ch);
            $code = 503;
        }
        curl_close($ch);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return [
            'http' => $code > 0 ? $code : 503,
            'body' => is_array($decoded) ? $decoded : null,
            'raw' => is_string($raw) ? $raw : '',
            'headers_sent' => $headers,
        ];
    }
}
