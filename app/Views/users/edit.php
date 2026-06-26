<?php
/**
 * Editar usuário.
 *
 * Variaveis: $user, $roles, $selected (ids), $errors
 */
$user     = $user ?? [];
$roles    = $roles ?? [];
$selected = $selected ?? [];
$errors   = $errors ?? [];
$uid      = (int) ($user['id'] ?? 0);
$isSelf   = $uid === (int) ($_SESSION['user_id'] ?? 0);
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Usuários</span>
                <h1 class="h2-section">Editar usuário</h1>
            </div>
            <a href="<?= e(app_url('/users/' . $uid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <form method="post" action="<?= e(app_url('/users/' . $uid . '/update')) ?>" class="form-box" novalidate>
            <?= csrf_field() ?>

            <div class="form-grid">
                <div>
                    <label for="name">Nome</label>
                    <input type="text" id="name" name="name" value="<?= e($user['name'] ?? '') ?>" required>
                    <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="<?= e($user['email'] ?? '') ?>" required>
                    <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status" <?= $isSelf ? 'disabled' : '' ?>>
                        <option value="active" <?= ($user['status'] ?? '') === 'active' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                        <option value="blocked" <?= ($user['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Bloqueado</option>
                    </select>
                    <?php if ($isSelf): ?>
                        <input type="hidden" name="status" value="active">
                        <p class="field-error" style="color:var(--dcx-muted);">Você não pode alterar o status do seu próprio usuário.</p>
                    <?php endif; ?>
                    <?php if (isset($errors['status'])): ?><p class="field-error"><?= e($errors['status']) ?></p><?php endif; ?>
                </div>
            </div>

            <div style="margin-top:18px;">
                <label>Perfis</label>
                <?php if (isset($errors['roles'])): ?><p class="field-error"><?= e($errors['roles']) ?></p><?php endif; ?>
                <div class="check-grid" style="margin-top:8px;">
                    <?php foreach ($roles as $r): ?>
                        <label class="check-item">
                            <input type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>"
                                <?= in_array((int) $r['id'], array_map('intval', $selected), true) ? 'checked' : '' ?>>
                            <span class="check-label">
                                <?= e($r['name']) ?>
                                <span class="check-slug"><?= e($r['slug']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="notice" style="margin:20px 0;">
                <p class="mb-0"><i data-lucide="lock"></i> A senha não é editada aqui. Use a ação <strong>Redefinir senha</strong> na tela do usuário.</p>
            </div>

            <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> Salvar alterações</button>
        </form>
    </div>
</section>
