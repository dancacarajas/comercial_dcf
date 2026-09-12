<?php
declare(strict_types=1);

final class Dcx_Ssp_Envelope_A
{
    /**
     * @return array{ok:bool,http:int,data?:array<string,mixed>,message?:string}
     */
    public static function parse(string $rawBody, ?int $contentLength = null): array
    {
        $len = strlen($rawBody);
        if ($contentLength !== null && $contentLength > Dcx_Ssp_Constants::MAX_BODY_BYTES) {
            return ['ok' => false, 'http' => 413, 'message' => 'Payload Too Large'];
        }
        if ($len > Dcx_Ssp_Constants::MAX_BODY_BYTES) {
            return ['ok' => false, 'http' => 413, 'message' => 'Payload Too Large'];
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data) || self::isList($data)) {
            return ['ok' => false, 'http' => 400, 'message' => 'JSON invalido.'];
        }

        /** @var array<string, mixed> $data */
        return ['ok' => true, 'http' => 200, 'data' => $data];
    }

    public static function honeypotFilled(array $data): bool
    {
        foreach (['website', 'website_url'] as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $v = $data[$k];
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
            if (!is_string($v) && $v !== null && $v !== '') {
                return true;
            }
        }

        return false;
    }

    private static function isList(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
