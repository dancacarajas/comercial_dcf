<?php
$proposal    = $proposal ?? [];
$formAction  = app_url('/proposals/' . (int) ($proposal['id'] ?? 0) . '/update');
$submitLabel = 'Salvar alterações';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Propostas · Captação</span>
                <h1 class="h2-section">Editar proposta</h1>
                <p class="page-sub"><?= e($proposal['title'] ?? '') ?></p>
            </div>
            <a href="<?= e(app_url('/proposals/' . (int) ($proposal['id'] ?? 0))) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
