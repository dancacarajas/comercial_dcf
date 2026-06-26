<?php
/**
 * Editar permissões do perfil.
 *
 * Variaveis: $role, $allPerms (linhas), $selected (ids), $isAdminRole (bool)
 */
$role        = $role ?? [];
$allPerms    = $allPerms ?? [];
$selected    = array_map('intval', $selected ?? []);
$isAdminRole = $isAdminRole ?? false;
$rid         = (int) ($role['id'] ?? 0);

// Separa administrativas x reservadas para organizar visualmente.
$admin = [];
$reserved = [];
foreach ($allPerms as $p) {
    if (\App\Models\Permission::isReserved((string) $p['slug'])) {
        $reserved[] = $p;
    } else {
        $admin[] = $p;
    }
}

$renderGroup = static function (array $list, array $selected, bool $disabled): void {
    foreach ($list as $p) {
        $checked = in_array((int) $p['id'], $selected, true) ? 'checked' : '';
        echo '<label class="check-item">';
        echo '<input type="checkbox" name="permissions[]" value="' . (int) $p['id'] . '" ' . $checked . ' ' . ($disabled ? 'disabled' : '') . '>';
        echo '<span class="check-label">' . e($p['name']);
        echo '<span class="check-slug">' . e($p['slug']) . '</span></span>';
        echo '</label>';
    }
};
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Perfil</span>
                <h1 class="h2-section">Permissões — <?= e($role['name'] ?? '') ?></h1>
                <p class="page-sub"><span class="pill"><?= e($role['slug'] ?? '') ?></span></p>
            </div>
            <a href="<?= e(app_url('/roles/' . $rid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php if ($isAdminRole): ?>
            <div class="notice" style="margin-bottom:22px;">
                <p class="mb-0"><i data-lucide="shield-alert"></i> O perfil <strong>Administrador Geral</strong> mantém <strong>todas</strong> as permissões por segurança e não pode ter o acesso reduzido.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('/roles/' . $rid . '/update')) ?>" class="form-box">
            <?= csrf_field() ?>

            <h3 class="h3-card">Permissões administrativas</h3>
            <div class="check-grid" style="margin:10px 0 26px;">
                <?php $renderGroup($admin, $selected, $isAdminRole); ?>
            </div>

            <h3 class="h3-card">Permissões reservadas (módulos futuros)</h3>
            <div class="check-grid" style="margin:10px 0 26px;">
                <?php $renderGroup($reserved, $selected, $isAdminRole); ?>
            </div>

            <?php if (!$isAdminRole): ?>
                <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> Salvar permissões</button>
            <?php else: ?>
                <button type="submit" class="btn btn-outline" data-confirm="O Administrador Geral manterá todas as permissões. Confirmar?">
                    <i data-lucide="refresh-cw"></i> Reaplicar todas as permissões
                </button>
            <?php endif; ?>
        </form>
    </div>
</section>
