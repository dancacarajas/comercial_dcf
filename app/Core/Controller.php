<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Controller base.
 *
 * Fornece atalhos para renderizar views, retornar JSON e redirecionar.
 */
abstract class Controller
{
    /**
     * Renderiza uma view dentro do layout padrao.
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/admin'): void
    {
        View::display($view, $data, $layout);
    }

    /**
     * Retorna uma resposta JSON.
     *
     * @param array<string, mixed> $data
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Redireciona para um caminho interno.
     */
    protected function redirect(string $path): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $base   = (string) ($config['url'] ?? '');

        $location = $base !== '' ? $base . '/' . ltrim($path, '/') : '/' . ltrim($path, '/');

        header('Location: ' . $location);
        exit;
    }

    /**
     * Aborta a requisicao com um codigo HTTP.
     */
    protected function abort(int $status = 404, string $message = ''): void
    {
        http_response_code($status);

        if ($message !== '') {
            echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }

        exit;
    }

    /**
     * Indica se ha um usuario autenticado na sessao.
     */
    protected function isAuthenticated(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Retorna dados basicos do usuario logado a partir da sessao.
     *
     * @return array<string, mixed>|null
     */
    protected function currentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return [
            'id'    => $_SESSION['user_id'] ?? null,
            'name'  => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
        ];
    }

    /**
     * Indica se o usuario logado possui a permissao.
     */
    protected function can(string $permission): bool
    {
        return can($permission);
    }

    /**
     * Exige uma permissao; caso contrario, responde 403 e encerra.
     */
    protected function requirePermission(string $permission): void
    {
        if (!$this->can($permission)) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/admin');
            exit;
        }
    }
}
