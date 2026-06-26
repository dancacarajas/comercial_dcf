<?php
$title = $title ?? 'Editar lançamento financeiro';
$old = $old ?? [];
$errors = $errors ?? [];
$entry = $entry ?? $old;
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Financeiro · Captação</span>
                <h1 class="h2-section"><?= e($title) ?></h1>
            </div>
            <a href="<?= e(app_url('/financials/' . (int) ($entry['id'] ?? 0))) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>
        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
