<?php $summary = $summary ?? []; ?>
<section class="section install-section">
    <div class="form-box lead-card conversion-box">
        <h1 class="h2-section">Revisão antes de instalar</h1>
        <p class="text-muted">Confira os dados. Senhas e token completo não são exibidos por segurança.</p>

        <dl class="conversion-summary" style="margin-top:18px;">
            <dt>APP_URL</dt><dd><?= e($summary['app_url'] ?? '') ?></dd>
            <dt>APP_ENV</dt><dd><?= e($summary['app_env'] ?? '') ?></dd>
            <dt>APP_DEBUG</dt><dd><?= !empty($summary['app_debug']) ? 'true' : 'false' ?></dd>
            <dt>DB_HOST</dt><dd><?= e($summary['db_host'] ?? '') ?></dd>
            <dt>DB_PORT</dt><dd><?= e((string) ($summary['db_port'] ?? '')) ?></dd>
            <dt>DB_DATABASE</dt><dd><?= e($summary['db_database'] ?? '') ?></dd>
            <dt>DB_USERNAME</dt><dd><?= e($summary['db_username'] ?? '') ?></dd>
            <dt>E-mail do administrador</dt><dd><?= e($summary['admin_email'] ?? '') ?></dd>
            <dt>Token de leads (mascarado)</dt><dd><code><?= e($summary['lead_token'] ?? '') ?></code></dd>
        </dl>

        <?php if (!empty($env_exists) || !empty($db_has_tables)): ?>
        <div class="notice notice-warning" style="margin-top:18px;">
            <i data-lucide="alert-triangle"></i>
            <?php if (!empty($env_exists)): ?>Já existe um arquivo <code>.env</code>.<?php endif; ?>
            <?php if (!empty($db_has_tables)): ?> O banco já contém tabelas (ex.: <code>users</code>).<?php endif; ?>
            Marque as confirmações abaixo para prosseguir.
        </div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('/install/run')) ?>" style="margin-top:22px;">
            <?= csrf_field() ?>
            <?php if (!empty($env_exists)): ?>
                <label class="check-inline"><input type="checkbox" name="confirm_overwrite_env" value="1" required> Confirmo sobrescrever o .env existente</label><br>
            <?php endif; ?>
            <?php if (!empty($db_has_tables)): ?>
                <label class="check-inline"><input type="checkbox" name="confirm_existing_db" value="1" required> Confirmo instalar sobre banco que já possui tabelas</label><br>
            <?php endif; ?>
            <div class="actions-row" style="margin-top:16px;">
                <a href="<?= e(app_url('/install/admin')) ?>" class="btn btn-outline">Voltar</a>
                <button type="submit" class="btn btn-yellow"><i data-lucide="rocket"></i> Instalar sistema</button>
            </div>
        </form>
    </div>
</section>
