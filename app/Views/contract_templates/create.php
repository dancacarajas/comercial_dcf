<?php
$title = $title ?? 'Novo modelo';
?>
<section class="section"><div class="container">
<div class="page-head">
    <div><span class="kicker kicker-dark">Modelos</span><h1 class="h2-section"><?= e($title) ?></h1></div>
    <a href="<?= e(app_url('/contract-templates')) ?>" class="btn btn-sm btn-outline">Voltar</a>
</div>
<div class="card">
<?php
$formAction = app_url('/contract-templates');
$submitLabel = 'Criar modelo';
include __DIR__ . '/_form.php';
?>
</div>
</div></section>
