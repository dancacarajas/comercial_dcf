<?php
declare(strict_types=1);

final class Dcx_Ssp_Logger
{
    /** @var list<array<string, mixed>> */
    public static array $buffer = [];

    /**
     * @param array<string, mixed> $ctx
     */
    public static function info(string $code, array $ctx = []): void
    {
        $safe = [
            'correlation_id' => $ctx['correlation_id'] ?? null,
            'submission_uuid' => $ctx['submission_uuid'] ?? null,
            'http_crm' => $ctx['http_crm'] ?? null,
            'duration_ms' => $ctx['duration_ms'] ?? null,
            'code' => $code,
        ];
        // Never log token/PII keys even if passed by mistake.
        unset(
            $ctx['token'],
            $ctx['secret'],
            $ctx['body'],
            $ctx['envelope'],
            $ctx['email'],
            $ctx['name'],
            $ctx['whatsapp'],
            $ctx['message']
        );
        self::$buffer[] = $safe;
        if (function_exists('error_log')) {
            error_log('[dcx-ssp] ' . json_encode($safe, JSON_UNESCAPED_SLASHES));
        }
    }
}
