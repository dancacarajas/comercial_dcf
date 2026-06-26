<?php

declare(strict_types=1);

/**
 * Helpers de seguranca de uso geral.
 *
 * Inclui escape de saida, sanitizacao e validacao basica de entrada.
 * Todos sem dependencias externas.
 */

if (!function_exists('e')) {
    /**
     * Escapa uma string para saida segura em HTML (anti-XSS).
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('clean')) {
    /**
     * Remove tags e espacos das extremidades de uma string de entrada.
     */
    function clean(?string $value): string
    {
        return trim(strip_tags((string) ($value ?? '')));
    }
}

if (!function_exists('input')) {
    /**
     * Recupera um valor de $_GET/$_POST ja limpo (trim).
     */
    function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }
}

if (!function_exists('is_email')) {
    /**
     * Valida um endereco de e-mail.
     */
    function is_email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('validate')) {
    /**
     * Validador minimalista por regras.
     *
     * Regras suportadas por campo: required, email, min:N, max:N, numeric.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules  ex.: ['email' => 'required|email', 'name' => 'required|min:3']
     * @return array<string, string> Mapa campo => mensagem de erro (vazio = ok).
     */
    function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleset) {
            $value = $data[$field] ?? null;

            foreach (explode('|', $ruleset) as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                $fail = match ($name) {
                    'required' => $value === null || $value === '',
                    'email'    => $value !== null && $value !== '' && !is_email((string) $value),
                    'numeric'  => $value !== null && $value !== '' && !is_numeric($value),
                    'min'      => $value !== null && mb_strlen((string) $value) < (int) $param,
                    'max'      => $value !== null && mb_strlen((string) $value) > (int) $param,
                    default    => false,
                };

                if ($fail) {
                    $errors[$field] = "Campo '{$field}' invalido na regra '{$name}'.";
                    break;
                }
            }
        }

        return $errors;
    }
}

if (!function_exists('client_ip')) {
    /**
     * Retorna o IP do cliente (considerando proxy reverso da Hostinger).
     */
    function client_ip(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $ip) {
            $ip = is_string($ip) ? trim($ip) : '';
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }
}

if (!function_exists('user_agent')) {
    function user_agent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}

if (!function_exists('can')) {
    /**
     * Verifica se o usuario logado possui a permissao informada.
     * As permissoes sao carregadas na sessao no momento do login.
     */
    function can(string $permission): bool
    {
        $perms = $_SESSION['permissions'] ?? [];

        return is_array($perms) && in_array($permission, $perms, true);
    }
}

if (!function_exists('app_url')) {
    /**
     * Monta uma URL interna respeitando APP_URL (se definido).
     */
    function app_url(string $path = ''): string
    {
        static $base = null;

        if ($base === null) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
            $base   = rtrim((string) ($config['url'] ?? ''), '/');
        }

        $path = '/' . ltrim($path, '/');

        return $base !== '' ? $base . $path : $path;
    }
}

if (!function_exists('money_br')) {
    /**
     * Formata um valor numérico como moeda brasileira (R$ 1.234,56).
     * Retorna travessão quando vazio/nulo.
     */
    function money_br(mixed $value, string $empty = '—'): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }

        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}

if (!function_exists('flash')) {
    /**
     * Define (com 2 args) ou consome (com 1 arg) uma mensagem flash de sessao.
     */
    function flash(string $key, ?string $value = null): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $msg;
    }
}

if (!function_exists('destroy_session')) {
    /**
     * Encerra a sessao de forma segura (limpa dados, cookie e ID).
     */
    function destroy_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?? '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => $params['secure'] ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }
}
