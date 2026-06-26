<?php
$old = $old ?? [];
$errors = $errors ?? [];
$val = static fn (string $k, string $d = ''): string => (string) ($old[$k] ?? $d);
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$checked = static fn (string $k): string => !empty($old[$k]) ? 'checked' : '';
?>
<section class="section install-section">
    <div class="form-box lead-card">
        <h1 class="h2-section">Configurações do sistema</h1>
        <p class="text-muted">Defina a URL pública do CRM e o token do endpoint de leads (WordPress → CRM).</p>

        <form method="post" action="<?= e(app_url('/install/system')) ?>" class="form-grid" style="margin-top:18px;" id="install-system-form">
            <?= csrf_field() ?>
            <div class="col-span-2"><label for="app_name">APP_NAME</label><input type="text" id="app_name" name="app_name" value="<?= e($val('app_name', 'Dança Carajás Captação')) ?>" required></div>
            <div class="col-span-2"><label for="app_url">APP_URL</label><input type="url" id="app_url" name="app_url" value="<?= e($val('app_url', 'https://comercial.dancacarajas.com.br')) ?>" required><?= $err('app_url') ?></div>
            <div><label for="app_env">APP_ENV</label>
                <select id="app_env" name="app_env">
                    <option value="production" <?= $val('app_env') === 'production' ? 'selected' : '' ?>>production</option>
                    <option value="local" <?= $val('app_env') === 'local' ? 'selected' : '' ?>>local</option>
                </select>
            </div>
            <div><label class="check-inline"><input type="checkbox" name="app_debug" value="1" <?= $checked('app_debug') ?>> APP_DEBUG (não use em produção)</label><?= $err('app_debug') ?></div>
            <div class="col-span-2"><label class="check-inline"><input type="checkbox" name="lead_endpoint_enabled" value="1" <?= $val('lead_endpoint_enabled', '1') ? 'checked' : $checked('lead_endpoint_enabled') ?>> LEAD_ENDPOINT_ENABLED</label></div>
            <div class="col-span-2">
                <label for="lead_endpoint_secret">LEAD_ENDPOINT_SECRET</label>
                <div class="flex gap-12" style="display:flex;gap:12px;align-items:center;">
                    <input type="text" id="lead_endpoint_secret" name="lead_endpoint_secret" value="<?= e($val('lead_endpoint_secret')) ?>" readonly style="flex:1;font-family:monospace;">
                    <button type="button" class="btn btn-sm btn-outline" id="btn-regen-token"><i data-lucide="refresh-cw"></i> Regenerar</button>
                </div>
                <p class="text-sm text-muted">Prefixo DCF_2026_ — copie na revisão e configure no WordPress (somente servidor).</p>
            </div>
            <div class="col-span-2 actions-row">
                <a href="<?= e(app_url('/install/database')) ?>" class="btn btn-outline">Voltar</a>
                <button type="submit" class="btn btn-yellow"><i data-lucide="arrow-right"></i> Continuar</button>
            </div>
        </form>
    </div>
</section>
<script>
document.getElementById('btn-regen-token')?.addEventListener('click', function () {
    var fd = new FormData(document.getElementById('install-system-form'));
    fetch('<?= e(app_url('/install/regenerate-token')) ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (j.token) document.getElementById('lead_endpoint_secret').value = j.token;
        });
});
</script>
