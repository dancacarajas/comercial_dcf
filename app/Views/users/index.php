<?php
/**
 * Lista de usuários.
 *
 * Variaveis esperadas:
 * - $users (array<int, array>) com chave roles (string concatenada)
 */
$users = $users ?? [];

$statusClass = static function (string $s): string {
    return match ($s) {
        'active'   => 'status status-active',
        'inactive' => 'status status-inactive',
        default    => 'status status-blocked',
    };
};
$statusLabel = static fn (string $s): string => match ($s) {
    'active' => 'Ativo', 'inactive' => 'Inativo', default => 'Bloqueado',
};
$currentId = (int) ($_SESSION['user_id'] ?? 0);
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Controle de acesso</span>
                <h1 class="h2-section">Usuários</h1>
                <p class="page-sub">Gerencie os usuários do sistema e seus perfis de acesso.</p>
            </div>
            <?php if (can('users.create')): ?>
                <a href="<?= e(app_url('/users/create')) ?>" class="btn btn-yellow">
                    <i data-lucide="user-plus"></i> Novo usuário
                </a>
            <?php endif; ?>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Status</th>
                        <th>Perfis</th>
                        <th>Último login</th>
                        <th>Criado em</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users === []): ?>
                        <tr><td colspan="7">Nenhum usuário cadastrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                        <?php $uid = (int) $u['id']; ?>
                        <tr>
                            <td><strong><?= e($u['name']) ?></strong></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="<?= e($statusClass((string) $u['status'])) ?>"><?= e($statusLabel((string) $u['status'])) ?></span></td>
                            <td><?= e($u['roles'] ?? '—') ?: '—' ?></td>
                            <td><?= e($u['last_login_at'] ?? '—') ?: 'Nunca' ?></td>
                            <td><?= e($u['created_at'] ?? '') ?></td>
                            <td>
                                <div class="actions-row" style="justify-content:flex-end;">
                                    <?php if (can('users.view')): ?>
                                        <a href="<?= e(app_url('/users/' . $uid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> Ver</a>
                                    <?php endif; ?>
                                    <?php if (can('users.edit')): ?>
                                        <a href="<?= e(app_url('/users/' . $uid . '/edit')) ?>" class="btn btn-sm btn-light"><i data-lucide="pencil"></i> Editar</a>
                                    <?php endif; ?>
                                    <?php if ($u['status'] === 'active' && can('users.deactivate') && $uid !== $currentId): ?>
                                        <form method="post" action="<?= e(app_url('/users/' . $uid . '/deactivate')) ?>" class="inline-form">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="Inativar este usuário?"><i data-lucide="user-x"></i> Inativar</button>
                                        </form>
                                    <?php elseif ($u['status'] !== 'active' && can('users.activate')): ?>
                                        <form method="post" action="<?= e(app_url('/users/' . $uid . '/activate')) ?>" class="inline-form">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="user-check"></i> Ativar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
