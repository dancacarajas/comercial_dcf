<?php
$old = $old ?? [];
$errors = $errors ?? [];
$val = static fn (string $k, string $d = ''): string => (string) ($old[$k] ?? $d);
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<section class="section install-section">
    <div class="form-box lead-card">
        <h1 class="h2-section">Administrador inicial</h1>
        <p class="text-muted">Crie o usuário principal com perfil <strong>Administrador Geral</strong>. Não usamos senha padrão em produção.</p>

        <form method="post" action="<?= e(app_url('/install/admin')) ?>" class="form-grid" style="margin-top:18px;">
            <?= csrf_field() ?>
            <div class="col-span-2"><label for="admin_name">Nome *</label><input type="text" id="admin_name" name="admin_name" value="<?= e($val('admin_name')) ?>" required><?= $err('admin_name') ?></div>
            <div class="col-span-2"><label for="admin_email">E-mail *</label><input type="email" id="admin_email" name="admin_email" value="<?= e($val('admin_email')) ?>" required><?= $err('admin_email') ?></div>
            <div><label for="admin_password">Senha *</label><input type="password" id="admin_password" name="admin_password" autocomplete="new-password" minlength="8" required><?= $err('admin_password') ?></div>
            <div><label for="admin_password_confirm">Confirmar senha *</label><input type="password" id="admin_password_confirm" name="admin_password_confirm" autocomplete="new-password" minlength="8" required><?= $err('admin_password_confirm') ?></div>
            <div class="col-span-2 actions-row">
                <a href="<?= e(app_url('/install/system')) ?>" class="btn btn-outline">Voltar</a>
                <button type="submit" class="btn btn-yellow"><i data-lucide="arrow-right"></i> Continuar</button>
            </div>
        </form>
    </div>
</section>
