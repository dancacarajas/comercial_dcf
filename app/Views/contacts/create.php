<?php
/**
 * Cadastro de contato.
 */
$formAction  = app_url('/contacts');
$submitLabel = 'Cadastrar contato';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Contatos</span>
                <h1 class="h2-section">Novo contato</h1>
                <p class="page-sub">Cadastre uma pessoa vinculada a uma empresa.</p>
            </div>
            <a href="<?= e(app_url('/contacts')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
