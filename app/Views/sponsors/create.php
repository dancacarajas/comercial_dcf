<?php
$title = $title ?? 'Novo fechamento';
$old = $old ?? [];
$errors = $errors ?? [];
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Patrocinadores · Captação</span>
                <h1 class="h2-section"><?= e($title) ?></h1>
            </div>
            <a href="<?= e(app_url('/sponsors')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>
        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
