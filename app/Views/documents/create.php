<?php
$formAction  = app_url('/documents');
$submitLabel = 'Cadastrar documento';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Documentos · Captação</span>
                <h1 class="h2-section">Novo documento</h1>
                <p class="page-sub">Armazene materiais comerciais com segurança e vínculos opcionais.</p>
            </div>
            <a href="<?= e(app_url('/documents')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
