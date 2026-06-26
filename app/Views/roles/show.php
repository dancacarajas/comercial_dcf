<?php
/**
 * Detalhes do perfil + permissões vinculadas.
 *
 * Variaveis: $role, $permissions (linhas), $usersCount
 */
$role        = $role ?? [];
$permissions = $permissions ?? [];
$usersCount  = $usersCount ?? 0;
$rid         = (int) ($role['id'] ?? 0);
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Perfil</span>
                <h1 class="h2-section"><?= e($role['name'] ?? '') ?></h1>
                <p class="page-sub"><?= e($role['description'] ?? '') ?> · <span class="pill"><?= e($role['slug'] ?? '') ?></span></p>
            </div>
            <div class="actions-row">
                <a href="<?= e(app_url('/roles')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
                <?php if (can('roles.edit')): ?>
                    <a href="<?= e(app_url('/roles/' . $rid . '/edit')) ?>" class="btn btn-sm btn-yellow"><i data-lucide="sliders-horizontal"></i> Editar permissões</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="notice" style="margin-bottom:22px;">
            <p class="mb-0"><i data-lucide="users"></i> <strong><?= (int) $usersCount ?></strong> usuário(s) vinculado(s) · <strong><?= count($permissions) ?></strong> permissão(ões).</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Permissão</th><th>Slug</th><th>Tipo</th></tr>
                </thead>
                <tbody>
                    <?php if ($permissions === []): ?>
                        <tr><td colspan="3">Nenhuma permissão vinculada.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($permissions as $p): ?>
                        <tr>
                            <td><?= e($p['name']) ?></td>
                            <td><span class="pill"><?= e($p['slug']) ?></span></td>
                            <td><?= e(\App\Models\Permission::kind((string) $p['slug'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
