<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Throwable;

/**
 * Autenticacao: login, logout e tela de recuperacao de senha.
 *
 * Protecao contra forca bruta por sessao/IP (sem tabela dedicada nesta etapa).
 * Mensagens de erro sao sempre genericas (nao revelam existencia de e-mail).
 */
final class AuthController extends Controller
{
    private const GENERIC_ERROR = 'E-mail ou senha inválidos.';

    public function showLogin(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }

        $this->renderLogin();
    }

    public function login(): void
    {
        // Ja autenticado: nao reprocessa.
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }

        // 1) CSRF
        csrf_verify();

        // 2) Bloqueio por excesso de tentativas
        if ($this->isLockedOut()) {
            $this->renderLogin(
                'Muitas tentativas inválidas. Tente novamente em alguns minutos.',
                429
            );
            return;
        }

        // 3) Validacao de entrada
        $email    = (string) input('email', '');
        $password = (string) ($_POST['password'] ?? '');

        $errors = validate(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email', 'password' => 'required|min:1']
        );

        if ($errors !== []) {
            $this->registerFailure(null);
            $this->renderLogin(self::GENERIC_ERROR, 422, $email);
            return;
        }

        // 4) Busca + verificacao
        try {
            $user = (new User())->findByEmail($email);
        } catch (Throwable $e) {
            error_log('[AUTH] Erro ao buscar usuario: ' . $e->getMessage());
            $this->renderLogin('Erro temporário. Tente novamente.', 500, $email);
            return;
        }

        $passwordOk = is_array($user)
            && password_verify($password, (string) $user['password_hash']);
        $isActive = is_array($user) && ($user['status'] ?? '') === 'active';

        if (!$passwordOk || !$isActive) {
            $this->registerFailure(is_array($user) ? (int) $user['id'] : null);
            $this->renderLogin(self::GENERIC_ERROR, 401, $email);
            return;
        }

        // 5) Sucesso — fixa sessao autenticada
        $this->resetAttempts();
        session_regenerate_id(true);

        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['user_name']  = (string) $user['name'];
        $_SESSION['user_email'] = (string) $user['email'];
        $_SESSION['_created_at']   = time();
        $_SESSION['_last_activity'] = time();

        // Carrega perfis e permissoes efetivas na sessao (RBAC).
        try {
            $model = new User();
            $roles = $model->rolesFor((int) $user['id']);
            $_SESSION['roles']       = array_map(static fn ($r): string => (string) $r['slug'], $roles);
            $_SESSION['role_names']  = array_map(static fn ($r): string => (string) $r['name'], $roles);
            $_SESSION['permissions'] = $model->permissionsFor((int) $user['id']);
        } catch (Throwable $e) {
            error_log('[AUTH] Falha ao carregar permissoes: ' . $e->getMessage());
            $_SESSION['roles'] = [];
            $_SESSION['role_names'] = [];
            $_SESSION['permissions'] = [];
        }

        try {
            (new User())->registerSuccessfulLogin((int) $user['id']);
            (new ActivityLog())->record('login_success', (int) $user['id'], 'user', (int) $user['id']);
        } catch (Throwable $e) {
            error_log('[AUTH] Pos-login: ' . $e->getMessage());
        }

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        csrf_verify();

        if ($this->isAuthenticated()) {
            try {
                (new ActivityLog())->record('logout', (int) $_SESSION['user_id'], 'user', (int) $_SESSION['user_id']);
            } catch (Throwable $e) {
                error_log('[AUTH] Log de logout: ' . $e->getMessage());
            }
        }

        destroy_session();
        $this->redirect('/login');
    }

    public function forgot(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }

        $this->view('auth/forgot', ['title' => 'Recuperar senha'], 'layouts/auth');
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    private function renderLogin(?string $error = null, int $status = 200, string $email = ''): void
    {
        if ($status !== 200) {
            http_response_code($status);
        }

        // Aviso de sessao expirada por inatividade.
        $timeoutNotice = !empty($_SESSION['_flash_timeout']);
        unset($_SESSION['_flash_timeout']);

        $this->view('auth/login', [
            'title'         => 'Acesso restrito',
            'error'         => $error,
            'email'         => $email,
            'timeoutNotice' => $timeoutNotice,
        ], 'layouts/auth');
    }

    private function attemptsKey(): string
    {
        return '_login_attempts';
    }

    private function isLockedOut(): bool
    {
        $data = $_SESSION[$this->attemptsKey()] ?? null;

        if (!is_array($data)) {
            return false;
        }

        $lockedUntil = (int) ($data['locked_until'] ?? 0);

        if ($lockedUntil > time()) {
            return true;
        }

        // Expirou o bloqueio: limpa.
        if ($lockedUntil !== 0 && $lockedUntil <= time()) {
            $this->resetAttempts();
        }

        return false;
    }

    private function registerFailure(int|string|null $userId): void
    {
        $config  = require dirname(__DIR__, 2) . '/config/app.php';
        $max     = (int) ($config['auth']['max_login_attempts'] ?? 5);
        $lockout = (int) ($config['auth']['lockout_seconds'] ?? 900);

        $data  = $_SESSION[$this->attemptsKey()] ?? ['count' => 0, 'locked_until' => 0];
        $count = (int) ($data['count'] ?? 0) + 1;

        $lockedUntil = 0;
        if ($count >= $max) {
            $lockedUntil = time() + $lockout;
        }

        $_SESSION[$this->attemptsKey()] = [
            'count'        => $count,
            'locked_until' => $lockedUntil,
        ];

        try {
            (new ActivityLog())->record('login_failed', $userId, 'user', $userId);
        } catch (Throwable $e) {
            error_log('[AUTH] Log de falha: ' . $e->getMessage());
        }
    }

    private function resetAttempts(): void
    {
        unset($_SESSION[$this->attemptsKey()]);
    }
}
