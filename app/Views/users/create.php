<?php
/**
 * Criar usuário.
 *
 * Variaveis: $roles, $selected (ids), $old, $errors
 */
$roles    = $roles ?? [];
$selected = $selected ?? [];
$old      = $old ?? [];
$errors   = $errors ?? [];
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Usuários</span>
                <h1 class="h2-section">Novo usuário</h1>
            </div>
            <a href="<?= e(app_url('/users')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <form method="post" action="<?= e(app_url('/users')) ?>" class="form-box" novalidate>
            <?= csrf_field() ?>

            <div class="form-grid">
                <div>
                    <label for="name">Nome</label>
                    <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" required>
                    <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>
                    <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="password">Senha provisória</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required>
                    <?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="password_confirm">Confirmação de senha</label>
                    <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                    <?php if (isset($errors['password_confirm'])): ?><p class="field-error"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                    </select>
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
                <p class="mb-0"><i data-lucide="info"></i> O usuário será criado com <strong>troca de senha obrigatória</strong> no primeiro acesso.</p>
            </div>

            <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> Criar usuário</button>
        </form>
    </div>
</section>
