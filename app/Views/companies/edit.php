<?php
/**
 * Edição de empresa.
 */
$company     = $company ?? [];
$cid         = (int) ($company['id'] ?? 0);
$formAction  = app_url('/companies/' . $cid . '/update');
$submitLabel = 'Salvar alterações';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Empresas</span>
                <h1 class="h2-section">Editar empresa</h1>
                <p class="page-sub"><?= e($company['name'] ?? '') ?></p>
            </div>
            <a href="<?= e(app_url('/companies/' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
