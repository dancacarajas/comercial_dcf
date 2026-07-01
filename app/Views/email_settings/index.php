<?php
$settings = $settings ?? [];
$providers = $providers ?? [];
$encryptions = $encryptions ?? [];
$errors = $errors ?? [];
$hasPassword = !empty($hasPassword);
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Etapa 21A</span>
                <h1 class="h2-section">Configuracao de E-mail</h1>
                <p class="page-sub">SMTP transacional para a trilha de captadores. Por seguranca, use dry-run ate validar o teste controlado.</p>
            </div>
            <div class="actions-row">
                <?php if (can('email_templates.view')): ?><a href="<?= e(app_url('/settings/email/templates')) ?>" class="btn btn-sm btn-outline"><i data-lucide="file-text"></i> Templates</a><?php endif; ?>
                <?php if (can('email_logs.view')): ?><a href="<?= e(app_url('/settings/email/logs')) ?>" class="btn btn-sm btn-outline"><i data-lucide="list"></i> Logs</a><?php endif; ?>
            </div>
        </div>

        <div class="notice" style="margin-bottom:18px;">
            <p class="mb-0"><i data-lucide="shield-check"></i> Nenhum e-mail real e disparado quando o envio esta desativado ou quando o dry-run esta ativo.</p>
        </div>

        <form method="post" action="<?= e(app_url('/settings/email')) ?>" class="form-card">
            <?= csrf_field() ?>
            <h2 class="h3-section">SMTP</h2>
            <div class="form-grid">
                <div class="form-field">
                    <label>Provedor</label>
                    <select name="provider">
                        <?php foreach ($providers as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($settings['provider'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['provider'])): ?><small class="text-danger"><?= e($errors['provider']) ?></small><?php endif; ?>
                </div>
                <div class="form-field">
                    <label>Host SMTP</label>
                    <input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? 'smtp.gmail.com') ?>">
                    <?php if (isset($errors['smtp_host'])): ?><small class="text-danger"><?= e($errors['smtp_host']) ?></small><?php endif; ?>
                </div>
                <div class="form-field">
                    <label>Porta</label>
                    <input type="number" name="smtp_port" min="1" value="<?= e((string) ($settings['smtp_port'] ?? 587)) ?>">
                </div>
                <div class="form-field">
                    <label>Criptografia</label>
                    <select name="smtp_encryption">
                        <?php foreach ($encryptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($settings['smtp_encryption'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Usuario SMTP</label>
                    <input type="text" name="smtp_username" value="<?= e($settings['smtp_username'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-field">
                    <label>Senha SMTP / senha de app</label>
                    <input type="password" name="smtp_password" value="" autocomplete="new-password" placeholder="<?= $hasPassword ? 'Senha salva; preencha somente para trocar' : 'Informe a senha de app' ?>">
                    <?php if ($hasPassword): ?><small class="text-muted">Senha ja configurada. Ela nao e exibida novamente.</small><?php endif; ?>
                    <?php if (isset($errors['smtp_password'])): ?><small class="text-danger"><?= e($errors['smtp_password']) ?></small><?php endif; ?>
                </div>
            </div>

            <h2 class="h3-section" style="margin-top:18px;">Remetente</h2>
            <div class="form-grid">
                <div class="form-field">
                    <label>Nome do remetente</label>
                    <input type="text" name="from_name" value="<?= e($settings['from_name'] ?? 'Danca Carajas Captacao') ?>">
                </div>
                <div class="form-field">
                    <label>E-mail do remetente</label>
                    <input type="email" name="from_email" value="<?= e($settings['from_email'] ?? '') ?>">
                    <?php if (isset($errors['from_email'])): ?><small class="text-danger"><?= e($errors['from_email']) ?></small><?php endif; ?>
                </div>
                <div class="form-field">
                    <label>Nome para resposta</label>
                    <input type="text" name="reply_to_name" value="<?= e($settings['reply_to_name'] ?? 'Equipe Danca Carajas') ?>">
                </div>
                <div class="form-field">
                    <label>E-mail para resposta</label>
                    <input type="email" name="reply_to_email" value="<?= e($settings['reply_to_email'] ?? '') ?>">
                </div>
            </div>

            <h2 class="h3-section" style="margin-top:18px;">Operacao</h2>
            <div class="form-grid">
                <label class="form-check"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>> Envio habilitado</label>
                <label class="form-check"><input type="checkbox" name="dry_run" value="1" <?= !empty($settings['dry_run']) ? 'checked' : '' ?>> Dry-run ativo</label>
                <div class="form-field">
                    <label>Limite por hora</label>
                    <input type="number" min="0" name="hourly_limit" value="<?= e((string) ($settings['hourly_limit'] ?? 20)) ?>">
                </div>
                <div class="form-field">
                    <label>Limite por dia</label>
                    <input type="number" min="0" name="daily_limit" value="<?= e((string) ($settings['daily_limit'] ?? 100)) ?>">
                </div>
            </div>

            <div class="actions-row" style="margin-top:18px;">
                <?php if (can('email_settings.edit')): ?><button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> Salvar configuracao</button><?php endif; ?>
            </div>
        </form>

        <form method="post" action="<?= e(app_url('/settings/email/test')) ?>" class="form-card" style="margin-top:18px;">
            <?= csrf_field() ?>
            <h2 class="h3-section">Teste controlado</h2>
            <p class="page-sub">Envie primeiro para um unico e-mail controlado. Se dry-run estiver ativo, o teste sera apenas registrado.</p>
            <div class="form-grid">
                <div class="form-field"><label>Nome</label><input type="text" name="test_name" value=""></div>
                <div class="form-field"><label>E-mail de teste</label><input type="email" name="test_email" required></div>
                <div class="form-field"><label>Ultimo teste</label><input type="text" readonly value="<?= e(trim((string) ($settings['last_test_status'] ?? '') . ' ' . (string) ($settings['last_tested_at'] ?? ''))) ?>"></div>
            </div>
            <div class="actions-row" style="margin-top:12px;">
                <?php if (can('email_settings.test')): ?><button type="submit" class="btn btn-outline"><i data-lucide="send"></i> Enviar teste</button><?php endif; ?>
            </div>
        </form>
    </div>
</section>
