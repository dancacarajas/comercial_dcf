<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de usuarios (autenticacao + administracao).
 *
 * Exclusao fisica NAO e permitida: usar ativacao/inativacao (status).
 */
final class User extends Model
{
    protected string $table = 'users';

    /**
     * Busca um usuario pelo e-mail.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    /**
     * Registra um login bem-sucedido: atualiza last_login_at e zera tentativas.
     */
    public function registerSuccessfulLogin(int|string $id): void
    {
        $this->query(
            'UPDATE `users`
                SET `last_login_at` = NOW(),
                    `failed_login_attempts` = 0,
                    `locked_until` = NULL
              WHERE `id` = :id',
            ['id' => $id]
        );
    }

    /**
     * Lista todos os usuarios com seus perfis (string concatenada em PHP).
     * Evita dialetos de GROUP_CONCAT para manter portabilidade.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWithRoles(): array
    {
        $users = $this->query(
            'SELECT `id`, `name`, `email`, `status`, `last_login_at`, `created_at`
               FROM `users` ORDER BY `name` ASC'
        )->fetchAll();

        $map = [];
        $rows = $this->query(
            'SELECT ur.`user_id`, r.`name`
               FROM `user_roles` ur
               JOIN `roles` r ON r.`id` = ur.`role_id`
              ORDER BY r.`name`'
        )->fetchAll();

        foreach ($rows as $row) {
            $map[(int) $row['user_id']][] = (string) $row['name'];
        }

        foreach ($users as &$u) {
            $u['roles'] = isset($map[(int) $u['id']]) ? implode(', ', $map[(int) $u['id']]) : '';
        }
        unset($u);

        return $users;
    }

    /**
     * Lista usuarios ativos (para selecao de responsavel).
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeList(): array
    {
        return $this->query(
            "SELECT `id`, `name`, `email`
               FROM `users`
              WHERE `status` = 'active'
              ORDER BY `name` ASC"
        )->fetchAll();
    }

    /**
     * IDs dos perfis vinculados ao usuario.
     *
     * @return array<int, int>
     */
    public function roleIds(int|string $userId): array
    {
        $rows = $this->query(
            'SELECT `role_id` FROM `user_roles` WHERE `user_id` = :id',
            ['id' => $userId]
        )->fetchAll();

        return array_map(static fn ($r): int => (int) $r['role_id'], $rows);
    }

    /**
     * Perfis (linhas completas) vinculados ao usuario.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rolesFor(int|string $userId): array
    {
        return $this->query(
            'SELECT r.`id`, r.`name`, r.`slug`
               FROM `roles` r
               JOIN `user_roles` ur ON ur.`role_id` = r.`id`
              WHERE ur.`user_id` = :id
              ORDER BY r.`name`',
            ['id' => $userId]
        )->fetchAll();
    }

    /**
     * Slugs de permissoes efetivas do usuario (uniao dos perfis).
     *
     * @return array<int, string>
     */
    public function permissionsFor(int|string $userId): array
    {
        $rows = $this->query(
            'SELECT DISTINCT p.`slug`
               FROM `permissions` p
               JOIN `role_permissions` rp ON rp.`permission_id` = p.`id`
               JOIN `user_roles` ur ON ur.`role_id` = rp.`role_id`
              WHERE ur.`user_id` = :id',
            ['id' => $userId]
        )->fetchAll();

        return array_map(static fn ($r): string => (string) $r['slug'], $rows);
    }

    /**
     * Cria um usuario e retorna o ID.
     *
     * @param array<string, mixed> $data
     */
    public function createUser(string $name, string $email, string $passwordHash, string $status = 'active', bool $mustChange = true): string
    {
        $this->query(
            'INSERT INTO `users` (`name`, `email`, `password_hash`, `status`, `must_change_password`)
             VALUES (:name, :email, :hash, :status, :mc)',
            ['name' => $name, 'email' => $email, 'hash' => $passwordHash, 'status' => $status, 'mc' => $mustChange ? 1 : 0]
        );

        return $this->db->lastInsertId();
    }

    public function updateProfile(int|string $id, string $name, string $email, string $status): void
    {
        $this->query(
            'UPDATE `users` SET `name` = :name, `email` = :email, `status` = :status WHERE `id` = :id',
            ['name' => $name, 'email' => $email, 'status' => $status, 'id' => $id]
        );
    }

    public function setStatus(int|string $id, string $status): void
    {
        $this->query(
            'UPDATE `users` SET `status` = :status WHERE `id` = :id',
            ['status' => $status, 'id' => $id]
        );
    }

    public function setPassword(int|string $id, string $passwordHash, bool $mustChange = true): void
    {
        $this->query(
            'UPDATE `users`
                SET `password_hash` = :hash, `must_change_password` = :mc
              WHERE `id` = :id',
            ['hash' => $passwordHash, 'mc' => $mustChange ? 1 : 0, 'id' => $id]
        );
    }

    /**
     * Verifica se ja existe outro usuario com o mesmo e-mail.
     */
    public function emailExists(string $email, int|string|null $exceptId = null): bool
    {
        if ($exceptId === null) {
            $row = $this->query(
                'SELECT `id` FROM `users` WHERE `email` = :email LIMIT 1',
                ['email' => $email]
            )->fetch();
        } else {
            $row = $this->query(
                'SELECT `id` FROM `users` WHERE `email` = :email AND `id` <> :id LIMIT 1',
                ['email' => $email, 'id' => $exceptId]
            )->fetch();
        }

        return $row !== false;
    }

    /**
     * Sincroniza os perfis do usuario (substitui vinculos).
     *
     * @param array<int, int> $roleIds
     */
    public function syncRoles(int|string $userId, array $roleIds): void
    {
        $this->query('DELETE FROM `user_roles` WHERE `user_id` = :id', ['id' => $userId]);

        foreach (array_unique($roleIds) as $roleId) {
            $this->query(
                'INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES (:u, :r)',
                ['u' => $userId, 'r' => $roleId]
            );
        }
    }
}
