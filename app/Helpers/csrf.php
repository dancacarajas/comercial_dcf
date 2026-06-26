<?php

declare(strict_types=1);

/**
 * Helpers de CSRF (Cross-Site Request Forgery).
 *
 * Geram e validam um token por sessao. Use csrf_field() nos formularios
 * POST e csrf_verify() ao processar a requisicao.
 */

if (!function_exists('csrf_token')) {
    /**
     * Retorna o token CSRF atual, criando-o se necessario.
     */
    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Retorna o input hidden pronto para inserir em formularios.
     */
    function csrf_field(): string
    {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }
}

if (!function_exists('csrf_validate')) {
    /**
     * Compara um token recebido com o token da sessao (timing-safe).
     */
    function csrf_validate(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $stored = $_SESSION['_csrf_token'] ?? '';

        return is_string($token)
            && $token !== ''
            && $stored !== ''
            && hash_equals((string) $stored, $token);
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Valida o token do request atual (campo _csrf ou header X-CSRF-Token).
     * Em caso de falha, encerra a requisicao com HTTP 419.
     */
    function csrf_verify(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Apenas metodos que alteram estado precisam de verificacao.
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!csrf_validate(is_string($token) ? $token : null)) {
            // Envia a linha de status completa com reason phrase. O codigo 419
            // (Authentication Timeout) nao consta na tabela padrao do Apache;
            // sem a reason phrase explicita o servidor pode reescrever para 500.
            $proto = (string) ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
            header($proto . ' 419 Authentication Timeout', true, 419);
            http_response_code(419);
            error_log('[CSRF] Token invalido ou ausente vindo de ' . (function_exists('client_ip') ? client_ip() : 'desconhecido'));
            exit('Token CSRF invalido ou expirado. Recarregue a pagina e tente novamente.');
        }
    }
}
