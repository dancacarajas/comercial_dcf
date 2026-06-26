<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;

/**
 * Administracao de perfis (roles) e suas permissoes.
 */
final class RoleController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requirePermission('roles.view');

        $this->view('roles/index', [
            'title' => 'Perfis',
            'roles' => (new Role())->allWithCounts(),
        ]);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('roles.view');

        $role = $this->findOr404($params['id'] ?? null);
        $model = new Role();

        $this->view('roles/show', [
            'title'       => 'Perfil',
            'role'        => $role,
            'permissions' => $model->permissions((int) $role['id']),
            'usersCount'  => $model->usersCount((int) $role['id']),
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('roles.edit');

        $role  = $this->findOr404($params['id'] ?? null);
        $roleM = new Role();

        $this->view('roles/edit', [
            'title'        => 'Editar permissões do perfil',
            'role'         => $role,
            'allPerms'     => (new Permission())->allOrdered(),
            'selected'     => $roleM->permissionIds((int) $role['id']),
            'isAdminRole'  => ($role['slug'] === Role::ADMIN_SLUG),
        ]);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('roles.edit');
        csrf_verify();

        $role  = $this->findOr404($params['id'] ?? null);
        $roleM = new Role();

        $permIds = $this->sanitizeIds($_POST['permissions'] ?? []);

        // Regra: o Administrador Geral nunca pode perder permissoes.
        if ($role['slug'] === Role::ADMIN_SLUG) {
            $all     = (new Permission())->allOrdered();
            $permIds = array_map(static fn ($p): int => (int) $p['id'], $all);
            flash('info', 'O perfil Administrador Geral mantém todas as permissões por segurança.');
        } elseif ($permIds === []) {
            // Outros perfis: evita salvar vazio acidentalmente.
            http_response_code(422);
            flash('error', 'Selecione ao menos uma permissão para o perfil.');
            $this->redirect('/roles/' . (int) $role['id'] . '/edit');
            return;
        }

        $roleM->syncPermissions((int) $role['id'], $permIds);
        (new ActivityLog())->record('role_permissions_updated', $_SESSION['user_id'] ?? null, 'role', (int) $role['id']);

        flash('success', 'Permissões do perfil atualizadas.');
        $this->redirect('/roles/' . (int) $role['id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function findOr404(mixed $id): array
    {
        $role = is_numeric($id) ? (new Role())->find((int) $id) : null;

        if ($role === null) {
            $this->abort(404, 'Perfil não encontrado.');
        }

        return $role;
    }

    /**
     * @param mixed $raw
     * @return array<int, int>
     */
    private function sanitizeIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $ids = array_map(static fn ($v): int => (int) $v, $raw);

        return array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
    }
}
