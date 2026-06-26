<?php
/**
 * Cadastro de empresa.
 */
$formAction  = app_url('/companies');
$submitLabel = 'Cadastrar empresa';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Empresas</span>
                <h1 class="h2-section">Nova empresa</h1>
                <p class="page-sub">Cadastre uma empresa potencial patrocinadora.</p>
            </div>
            <a href="<?= e(app_url('/companies')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
