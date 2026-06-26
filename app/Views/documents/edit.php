<?php
$document   = $document ?? [];
$did        = (int) ($document['id'] ?? 0);
$formAction = app_url('/documents/' . $did . '/update');
$submitLabel = 'Salvar alterações';
$old        = $old ?? $document;
$model      = $model ?? null;
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Documentos · Captação</span>
                <h1 class="h2-section">Editar documento</h1>
                <p class="page-sub"><?= e($document['title'] ?? '') ?></p>
            </div>
            <a href="<?= e(app_url('/documents/' . $did)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
