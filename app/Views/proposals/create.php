<?php
$formAction  = app_url('/proposals');
$submitLabel = 'Cadastrar proposta';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Propostas · Captação</span>
                <h1 class="h2-section">Nova proposta</h1>
                <p class="page-sub">Registre uma proposta comercial vinculada ao processo de captação.</p>
            </div>
            <a href="<?= e(app_url('/proposals')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
