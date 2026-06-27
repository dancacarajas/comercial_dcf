<?php
$item = $item ?? [];
$rendered = $rendered ?? '';
?>
<section class="section"><div class="container">
<div class="page-head">
    <div><span class="kicker kicker-dark">Pré-visualização</span><h1 class="h2-section"><?= e($item['title'] ?? '') ?></h1></div>
    <a href="<?= e(app_url('/contract-templates/' . (int) ($item['id'] ?? 0))) ?>" class="btn btn-sm btn-outline">Voltar</a>
</div>
<div class="card contract-document-body">
    <?= $rendered ?>
</div>
<p class="text-sm text-muted-dcx">Dados de exemplo utilizados na pré-visualização.</p>
</div></section>
