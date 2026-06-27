<?php
$item = $item ?? [];
$title = $title ?? 'Editar modelo';
$id = (int) ($item['id'] ?? 0);
?>
<section class="section"><div class="container">
<div class="page-head">
    <div><span class="kicker kicker-dark">Modelos</span><h1 class="h2-section"><?= e($title) ?></h1></div>
    <a href="<?= e(app_url('/contract-templates/' . $id)) ?>" class="btn btn-sm btn-outline">Voltar</a>
</div>
<div class="card">
<?php
$old = $old ?? $item;
$formAction = app_url('/contract-templates/' . $id . '/update');
$submitLabel = 'Salvar alterações';
include __DIR__ . '/_form.php';
?>
</div>
</div></section>
