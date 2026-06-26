<?php
$old = $old ?? [];
$errors = $errors ?? [];
$val = static fn (string $k, string $d = ''): string => (string) ($old[$k] ?? $d);
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<section class="section install-section">
    <div class="form-box lead-card">
        <h1 class="h2-section">Banco de dados MySQL</h1>
        <p class="text-muted">Use os dados criados no hPanel da Hostinger. A senha não será exibida nem registrada em log.</p>

        <form method="post" action="<?= e(app_url('/install/database')) ?>" class="form-grid" style="margin-top:18px;" id="install-db-form">
            <?= csrf_field() ?>
            <div><label for="db_host">DB_HOST</label><input type="text" id="db_host" name="db_host" value="<?= e($val('db_host', 'localhost')) ?>" required><?= $err('db_host') ?></div>
            <div><label for="db_port">DB_PORT</label><input type="number" id="db_port" name="db_port" value="<?= e((string) $val('db_port', '3306')) ?>" required></div>
            <div class="col-span-2"><label for="db_database">DB_DATABASE</label><input type="text" id="db_database" name="db_database" value="<?= e($val('db_database', 'u482227589_comercialdcf')) ?>" required><?= $err('db_database') ?></div>
            <div class="col-span-2"><label for="db_username">DB_USERNAME</label><input type="text" id="db_username" name="db_username" value="<?= e($val('db_username', 'u482227589_comercialdcf')) ?>" required><?= $err('db_username') ?></div>
            <div class="col-span-2">
                <label for="db_password">DB_PASSWORD *</label>
                <input type="password" id="db_password" name="db_password" autocomplete="new-password" required>
                <?= $err('db_password') ?>
            </div>
            <div class="col-span-2 actions-row">
                <button type="button" class="btn btn-outline" id="btn-test-db"><i data-lucide="database"></i> Testar conexão</button>
                <button type="submit" class="btn btn-yellow"><i data-lucide="arrow-right"></i> Salvar e continuar</button>
            </div>
            <div class="col-span-2"><p id="db-test-result" class="text-sm" aria-live="polite"></p></div>
        </form>
    </div>
</section>
<script>
document.getElementById('btn-test-db')?.addEventListener('click', function () {
    var form = document.getElementById('install-db-form');
    var fd = new FormData(form);
    var out = document.getElementById('db-test-result');
    out.textContent = 'Testando conexão…';
    fetch('<?= e(app_url('/install/test-database')) ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { out.textContent = j.message || (j.success ? 'Conexão OK.' : 'Falha na conexão.'); })
        .catch(function () { out.textContent = 'Erro ao testar conexão.'; });
});
</script>
