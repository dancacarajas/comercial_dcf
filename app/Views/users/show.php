<?php
/**
 * Detalhes do usuário.
 *
 * Variaveis: $user, $roles (linhas)
 */
$user  = $user ?? [];
$roles = $roles ?? [];
$uid   = (int) ($user['id'] ?? 0);
$isSelf = $uid === (int) ($_SESSION['user_id'] ?? 0);

$statusLabel = static fn (string $s): string => match ($s) {
    'active' => 'Ativo', 'inactive' => 'Inativo', default => 'Bloqueado',
};
$statusCls = static fn (string $s): string => match ($s) {
    'active' => 'status status-active', 'inactive' => 'status status-inactive', default => 'status status-blocked',
};
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Usuários</span>
                <h1 class="h2-section"><?= e($user['name'] ?? '') ?></h1>
                <p class="page-sub"><?= e($user['email'] ?? '') ?></p>
            </div>
            <a href="<?= e(app_url('/users')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="grid" style="grid-template-columns: repeat(2, minmax(0,1fr));">
            <article class="card">
                <h3 class="h3-card">Dados</h3>
                <p>
                    <strong>Status:</strong>
                    <span class="<?= e($statusCls((string) ($user['status'] ?? ''))) ?>"><?= e($statusLabel((string) ($user['status'] ?? ''))) ?></span>
                </p>
                <p><strong>Último login:</strong> <?= e($user['last_login_at'] ?? '') ?: 'Nunca' ?></p>
                <p><strong>Criado em:</strong> <?= e($user['created_at'] ?? '') ?></p>
                <p style="margin-bottom:0;">
                    <strong>Troca de senha obrigatória:</strong>
                    <?= ((int) ($user['must_change_password'] ?? 0) === 1) ? 'Sim' : 'Não' ?>
                </p>
            </article>

            <article class="card">
                <h3 class="h3-card">Perfis vinculados</h3>
                <?php if ($roles === []): ?>
                    <p>Nenhum perfil vinculado.</p>
                <?php else: ?>
                    <p style="margin-bottom:0;">
                        <?php foreach ($roles as $r): ?>
                            <span class="pill"><?= e($r['name']) ?></span>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </article>
        </div>

        <div class="actions-row" style="margin-top:22px;">
            <?php if (can('users.edit')): ?>
                <a href="<?= e(app_url('/users/' . $uid . '/edit')) ?>" class="btn btn-light"><i data-lucide="pencil"></i> Editar</a>
            <?php endif; ?>

            <?php if (can('users.reset_password')): ?>
                <form method="post" action="<?= e(app_url('/users/' . $uid . '/reset-password')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline" data-confirm="Gerar nova senha provisória para este usuário?">
                        <i data-lucide="key-round"></i> Redefinir senha
                    </button>
                </form>
            <?php endif; ?>

            <?php if (($user['status'] ?? '') === 'active' && can('users.deactivate') && !$isSelf): ?>
                <form method="post" action="<?= e(app_url('/users/' . $uid . '/deactivate')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" data-confirm="Inativar este usuário?"><i data-lucide="user-x"></i> Inativar</button>
                </form>
            <?php elseif (($user['status'] ?? '') !== 'active' && can('users.activate')): ?>
                <form method="post" action="<?= e(app_url('/users/' . $uid . '/activate')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-yellow"><i data-lucide="user-check"></i> Ativar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
