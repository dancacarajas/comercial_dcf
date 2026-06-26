<?php
/**
 * Cadastro de oportunidade.
 */
$formAction  = app_url('/opportunities');
$submitLabel = 'Cadastrar oportunidade';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Nova oportunidade</h1>
                <p class="page-sub">Abra uma negociação vinculada a uma empresa.</p>
            </div>
            <a href="<?= e(app_url('/opportunities')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
