<?php
/**
 * Cadastro de cota de patrocínio.
 */
$formAction  = app_url('/quotas');
$submitLabel = 'Cadastrar cota';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Cotas · Patrocínio</span>
                <h1 class="h2-section">Nova cota</h1>
                <p class="page-sub">Cadastre uma cota de patrocínio do projeto.</p>
            </div>
            <a href="<?= e(app_url('/quotas')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
