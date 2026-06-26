<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de perfis (roles).
 */
final class Role extends Model
{
    protected string $table = 'roles';

    public const ADMIN_SLUG = 'administrador-geral';

    /**
     * Lista perfis com contagem de usuarios e permissoes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWithCounts(): array
    {
        return $this->query(
            "SELECT r.`id`, r.`name`, r.`slug`, r.`description`,
                    (SELECT COUNT(*) FROM `user_roles` ur WHERE ur.`role_id` = r.`id`) AS users_count,
                    (SELECT COUNT(*) FROM `role_permissions` rp WHERE rp.`role_id` = r.`id`) AS permissions_count
               FROM `roles` r
              ORDER BY r.`name` ASC"
        )->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * IDs das permissoes vinculadas ao perfil.
     *
     * @return array<int, int>
     */
    public function permissionIds(int|string $roleId): array
    {
        $rows = $this->query(
            'SELECT `permission_id` FROM `role_permissions` WHERE `role_id` = :id',
            ['id' => $roleId]
        )->fetchAll();

        return array_map(static fn ($r): int => (int) $r['permission_id'], $rows);
    }

    /**
     * Permissoes (linhas) vinculadas ao perfil.
     *
     * @return array<int, array<string, mixed>>
     */
    public function permissions(int|string $roleId): array
    {
        return $this->query(
            'SELECT p.`id`, p.`name`, p.`slug`, p.`description`
               FROM `permissions` p
               JOIN `role_permissions` rp ON rp.`permission_id` = p.`id`
              WHERE rp.`role_id` = :id
              ORDER BY p.`slug`',
            ['id' => $roleId]
        )->fetchAll();
    }

    /**
     * Conta usuarios vinculados ao perfil.
     */
    public function usersCount(int|string $roleId): int
    {
        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `user_roles` WHERE `role_id` = :id',
            ['id' => $roleId]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Sincroniza as permissoes do perfil (substitui vinculos).
     *
     * @param array<int, int> $permissionIds
     */
    public function syncPermissions(int|string $roleId, array $permissionIds): void
    {
        $this->query('DELETE FROM `role_permissions` WHERE `role_id` = :id', ['id' => $roleId]);

        foreach (array_unique($permissionIds) as $pid) {
            $this->query(
                'INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (:r, :p)',
                ['r' => $roleId, 'p' => $pid]
            );
        }
    }
}
