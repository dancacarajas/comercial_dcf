<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Models\ActivityLog;
use Throwable;

/**
 * Middleware de autenticacao.
 *
 * Garante que exista um usuario autenticado na sessao. Caso contrario,
 * registra a tentativa de acesso e redireciona para o login.
 * Preparado para expansao futura (verificacao de permissoes por perfil).
 */
final class AuthMiddleware
{
    public function handle(): void
    {
        self::requireAuth();
    }

    /**
     * Exige usuario autenticado; redireciona para /login caso contrario.
     */
    public static function requireAuth(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!empty($_SESSION['user_id'])) {
            return; // autenticado
        }

        // Registra tentativa de acesso a rota protegida sem sessao.
        try {
            (new ActivityLog())->record('blocked_access_attempt', null, 'route', null);
        } catch (Throwable $e) {
            error_log('[AUTH] Falha ao logar acesso bloqueado: ' . $e->getMessage());
        }

        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $base   = rtrim((string) ($config['url'] ?? ''), '/');

        // Guarda destino para redirecionar apos login.
        $_SESSION['_intended_url'] = $_SERVER['REQUEST_URI'] ?? '/';

        header('Location: ' . ($base !== '' ? $base . '/login' : '/login'));
        exit;
    }

    /**
     * Exige autenticacao + permissao especifica. Responde 403 se faltar.
     */
    public static function requirePermission(string $permission): void
    {
        self::requireAuth();

        $perms = $_SESSION['permissions'] ?? [];

        if (!is_array($perms) || !in_array($permission, $perms, true)) {
            try {
                (new ActivityLog())->record('blocked_access_attempt', $_SESSION['user_id'] ?? null, 'permission', null);
            } catch (Throwable $e) {
                error_log('[AUTH] Falha ao logar acesso sem permissao: ' . $e->getMessage());
            }

            http_response_code(403);
            echo \App\Core\View::render('errors/403', [], 'layouts/admin');
            exit;
        }
    }
}
