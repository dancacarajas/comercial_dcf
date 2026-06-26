<section class="section install-section">
    <div class="form-box lead-card integration-status">
        <h1 class="h2-section"><i data-lucide="check-circle"></i> Sistema instalado com sucesso</h1>
        <div class="notice notice-warning">
            O instalador foi <strong>bloqueado</strong> (<code>storage/installed.lock</code>). Por segurança, não é possível reinstalar sem remover esse arquivo manualmente.
        </div>
        <ul class="text-muted" style="margin:18px 0;line-height:1.8;">
            <li><a href="<?= e(app_url('/login')) ?>">Acessar login</a></li>
            <li><a href="<?= e(app_url('/health')) ?>">Testar /health</a> — deve retornar <code>{"status":"ok"}</code></li>
            <li>Configure o WordPress com o mesmo <code>LEAD_ENDPOINT_SECRET</code> (somente no servidor WP)</li>
            <li>Execute o teste E2E WordPress → CRM nas páginas de patrocínio</li>
        </ul>
        <a href="<?= e(app_url('/login')) ?>" class="btn btn-yellow"><i data-lucide="log-in"></i> Ir para o login</a>
    </div>
</section>
