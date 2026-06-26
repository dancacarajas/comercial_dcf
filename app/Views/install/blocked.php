<section class="section install-section">
    <div class="form-box lead-card">
        <h1 class="h2-section">Instalador bloqueado</h1>
        <p>Sistema já instalado. Por segurança, o instalador está bloqueado.</p>
        <p class="text-muted">Para reinstalar manualmente, remova o arquivo <code>storage/installed.lock</code> via FTP/SSH (somente se souber o que está fazendo).</p>
        <a href="<?= e(app_url('/login')) ?>" class="btn btn-yellow" style="margin-top:16px;">Ir para o login</a>
    </div>
</section>
