<?php
declare(strict_types=1);

final class Dcx_Ssp_Client_Ip
{
    /**
     * @param array<string, mixed> $server
     * @param list<string> $trustedProxies CIDR or exact IPs
     * @return array{ok:bool,ip:?string,error:?string}
     */
    public static function resolve(array $server, array $trustedProxies): array
    {
        $remote = self::normalizeIp((string) ($server['REMOTE_ADDR'] ?? ''));
        if ($remote === null) {
            return ['ok' => false, 'ip' => null, 'error' => 'REMOTE_ADDR invalido.'];
        }

        if (!self::isTrusted($remote, $trustedProxies)) {
            return ['ok' => true, 'ip' => $remote, 'error' => null];
        }

        $xff = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
        if (trim($xff) === '') {
            return ['ok' => true, 'ip' => $remote, 'error' => null];
        }

        $parts = array_map('trim', explode(',', $xff));
        $ips = [];
        foreach ($parts as $part) {
            $ip = self::normalizeIp($part);
            if ($ip !== null) {
                $ips[] = $ip;
            }
        }

        if ($ips === []) {
            return ['ok' => false, 'ip' => null, 'error' => 'X-Forwarded-For invalido.'];
        }

        // Walk right → left; skip trusted hops; first non-trusted = client.
        for ($i = count($ips) - 1; $i >= 0; $i--) {
            if (!self::isTrusted($ips[$i], $trustedProxies)) {
                return ['ok' => true, 'ip' => $ips[$i], 'error' => null];
            }
        }

        return ['ok' => false, 'ip' => null, 'error' => 'Cadeia XFF sem cliente nao-trusted.'];
    }

    public static function normalizeIp(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        // Strip surrounding brackets for IPv6 literals occasionally seen.
        if ($value[0] === '[' && str_ends_with($value, ']')) {
            $value = substr($value, 1, -1);
        }
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $value;
    }

    /**
     * @param list<string> $trusted
     */
    public static function isTrusted(string $ip, array $trusted): bool
    {
        foreach ($trusted as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }
            if (str_contains($entry, '/')) {
                if (self::ipInCidr($ip, $entry)) {
                    return true;
                }
                continue;
            }
            $norm = self::normalizeIp($entry);
            if ($norm !== null && $norm === $ip) {
                return true;
            }
        }

        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($subnet === null || $mask === null || !is_numeric($mask)) {
            return false;
        }
        $mask = (int) $mask;
        $ipBin = @inet_pton($ip);
        $subBin = @inet_pton(trim($subnet));
        if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
            return false;
        }
        $len = strlen($ipBin);
        $maxBits = $len * 8;
        if ($mask < 0 || $mask > $maxBits) {
            return false;
        }
        $bytes = intdiv($mask, 8);
        $bits = $mask % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subBin, 0, $bytes)) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }
        $maskByte = (~((1 << (8 - $bits)) - 1)) & 0xFF;

        return (ord($ipBin[$bytes]) & $maskByte) === (ord($subBin[$bytes]) & $maskByte);
    }
}
