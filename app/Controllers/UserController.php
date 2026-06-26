<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;

/**
 * Administracao de usuarios.
 *
 * Sem exclusao fisica: usuarios sao ativados/inativados via status.
 * Todas as acoes exigem autenticacao + permissao especifica.
 */
final class UserController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requirePermission('users.view');

        $this->view('users/index', [
            'title' => 'Usuários',
            'users' => (new User())->allWithRoles(),
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('users.create');

        $this->view('users/create', [
            'title'    => 'Novo usuário',
            'roles'    => (new Role())->allWithCounts(),
            'selected' => [],
            'old'      => [],
            'errors'   => [],
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('users.create');
        csrf_verify();

        $name     = clean((string) input('name', ''));
        $email    = clean((string) input('email', ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');
        $status   = in_array(input('status'), ['active', 'inactive'], true) ? (string) input('status') : 'active';
        $roleIds  = $this->sanitizeRoleIds($_POST['roles'] ?? []);

        $errors = validate(
            ['name' => $name, 'email' => $email, 'password' => $password],
            ['name' => 'required|min:2', 'email' => 'required|email', 'password' => 'required|min:8']
        );

        $model = new User();

        if (!isset($errors['email']) && $model->emailExists($email)) {
            $errors['email'] = 'Já existe um usuário com este e-mail.';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'A confirmação de senha não confere.';
        }
        if ($roleIds === []) {
            $errors['roles'] = 'Selecione ao menos um perfil.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->view('users/create', [
                'title'    => 'Novo usuário',
                'roles'    => (new Role())->allWithCounts(),
                'selected' => $roleIds,
                'old'      => ['name' => $name, 'email' => $email, 'status' => $status],
                'errors'   => $errors,
            ]);
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $id   = $model->createUser($name, $email, $hash, $status);
        $model->syncRoles($id, $roleIds);

        (new ActivityLog())->record('user_created', $_SESSION['user_id'] ?? null, 'user', $id);

        flash('success', 'Usuário criado com sucesso. Senha provisória definida (troca obrigatória no 1º acesso).');
        $this->redirect('/users');
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('users.view');

        $user = $this->findOr404($params['id'] ?? null);

        $this->view('users/show', [
            'title' => 'Usuário',
            'user'  => $user,
            'roles' => (new User())->rolesFor((int) $user['id']),
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('users.edit');

        $user  = $this->findOr404($params['id'] ?? null);
        $model = new User();

        $this->view('users/edit', [
            'title'    => 'Editar usuário',
            'user'     => $user,
            'roles'    => (new Role())->allWithCounts(),
            'selected' => $model->roleIds((int) $user['id']),
            'errors'   => [],
        ]);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('users.edit');
        csrf_verify();

        $user    = $this->findOr404($params['id'] ?? null);
        $model   = new User();

        $name    = clean((string) input('name', ''));
        $email   = clean((string) input('email', ''));
        $status  = in_array(input('status'), ['active', 'inactive', 'blocked'], true) ? (string) input('status') : (string) $user['status'];
        $roleIds = $this->sanitizeRoleIds($_POST['roles'] ?? []);

        $errors = validate(
            ['name' => $name, 'email' => $email],
            ['name' => 'required|min:2', 'email' => 'required|email']
        );

        if (!isset($errors['email']) && $model->emailExists($email, (int) $user['id'])) {
            $errors['email'] = 'Já existe outro usuário com este e-mail.';
        }
        if ($roleIds === []) {
            $errors['roles'] = 'Selecione ao menos um perfil.';
        }

        // Impede o admin logado de se auto-inativar pela edicao.
        if ((int) $user['id'] === (int) ($_SESSION['user_id'] ?? 0) && $status !== 'active') {
            $errors['status'] = 'Você não pode inativar o seu próprio usuário.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->view('users/edit', [
                'title'    => 'Editar usuário',
                'user'     => array_merge($user, ['name' => $name, 'email' => $email, 'status' => $status]),
                'roles'    => (new Role())->allWithCounts(),
                'selected' => $roleIds,
                'errors'   => $errors,
            ]);
            return;
        }

        $model->updateProfile((int) $user['id'], $name, $email, $status);
        $model->syncRoles((int) $user['id'], $roleIds);

        (new ActivityLog())->record('user_updated', $_SESSION['user_id'] ?? null, 'user', (int) $user['id']);

        flash('success', 'Usuário atualizado com sucesso.');
        $this->redirect('/users/' . (int) $user['id']);
    }

    public function activate(array $params): void
    {
        AuthMiddleware::requirePermission('users.activate');
        csrf_verify();

        $user = $this->findOr404($params['id'] ?? null);
        (new User())->setStatus((int) $user['id'], 'active');
        (new ActivityLog())->record('user_activated', $_SESSION['user_id'] ?? null, 'user', (int) $user['id']);

        flash('success', 'Usuário ativado.');
        $this->redirect('/users');
    }

    public function deactivate(array $params): void
    {
        AuthMiddleware::requirePermission('users.deactivate');
        csrf_verify();

        $user = $this->findOr404($params['id'] ?? null);

        if ((int) $user['id'] === (int) ($_SESSION['user_id'] ?? 0)) {
            flash('error', 'Você não pode inativar o seu próprio usuário.');
            $this->redirect('/users');
            return;
        }

        (new User())->setStatus((int) $user['id'], 'inactive');
        (new ActivityLog())->record('user_deactivated', $_SESSION['user_id'] ?? null, 'user', (int) $user['id']);

        flash('success', 'Usuário inativado.');
        $this->redirect('/users');
    }

    public function resetPassword(array $params): void
    {
        AuthMiddleware::requirePermission('users.reset_password');
        csrf_verify();

        $user = $this->findOr404($params['id'] ?? null);

        // Senha provisoria: informada pelo admin ou gerada com seguranca.
        $provided = (string) ($_POST['password'] ?? '');
        if ($provided !== '' && mb_strlen($provided) >= 8) {
            $temp = $provided;
        } else {
            $temp = $this->generateTempPassword();
        }

        (new User())->setPassword((int) $user['id'], password_hash($temp, PASSWORD_DEFAULT), true);
        (new ActivityLog())->record('user_password_reset', $_SESSION['user_id'] ?? null, 'user', (int) $user['id']);

        // A senha provisoria e exibida UMA vez ao admin (nunca o hash).
        flash('success', 'Senha provisória redefinida para ' . e($user['name']) . ': ' . $temp . ' (troca obrigatória no próximo acesso).');
        $this->redirect('/users/' . (int) $user['id']);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function findOr404(mixed $id): array
    {
        $user = is_numeric($id) ? (new User())->find((int) $id) : null;

        if ($user === null) {
            $this->abort(404, 'Usuário não encontrado.');
        }

        return $user;
    }

    /**
     * @param mixed $raw
     * @return array<int, int>
     */
    private function sanitizeRoleIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $ids = array_map(static fn ($v): int => (int) $v, $raw);

        return array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
    }

    private function generateTempPassword(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#%';
        $max      = strlen($alphabet) - 1;
        $out      = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}
