<?php
/**
 * Edição de oportunidade.
 */
$opportunity = $opportunity ?? [];
$oid         = (int) ($opportunity['id'] ?? 0);
$formAction  = app_url('/opportunities/' . $oid . '/update');
$submitLabel = 'Salvar alterações';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Editar oportunidade</h1>
                <p class="page-sub"><?= e($opportunity['title'] ?? '') ?></p>
            </div>
            <a href="<?= e(app_url('/opportunities/' . $oid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
