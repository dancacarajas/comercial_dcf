<?php
/**
 * Edição de cota de patrocínio.
 */
$qid         = (int) ($quota['id'] ?? ($old['id'] ?? 0));
$formAction  = app_url('/quotas/' . $qid . '/update');
$submitLabel = 'Salvar alterações';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Cotas · Patrocínio</span>
                <h1 class="h2-section">Editar cota</h1>
                <p class="page-sub"><?= e($quota['name'] ?? '') ?></p>
            </div>
            <a href="<?= e(app_url('/quotas/' . $qid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
