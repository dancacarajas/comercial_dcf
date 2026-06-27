<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section"><?= e($title ?? 'Indisponível') ?></h1>
                <p class="page-sub"><?= e($message ?? 'Não foi possível acessar este processo.') ?></p>
            </div>
        </div>

        <div class="card">
            <div class="empty-state">
                <i data-lucide="link-2-off"></i>
                <p>Se precisar de ajuda, entre em contato com a equipe Dança Carajás Festival.</p>
                <?php if (($title ?? '') === 'Cadastro concluído'): ?>
                    <p style="margin-top:14px;"><a class="btn btn-yellow" href="<?= e(app_url('/login')) ?>">Entrar no sistema</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
