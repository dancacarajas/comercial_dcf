<?php
$lid = (int) ($lead['id'] ?? ($old['id'] ?? 0));
$formAction = app_url('/leads/' . $lid . '/update');
$submitLabel = 'Salvar alterações';
?>
<section class="section"><div class="container">
<div class="page-head"><div><span class="kicker kicker-dark">Leads</span><h1 class="h2-section">Editar lead</h1></div>
<a href="<?= e(app_url('/leads/' . $lid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a></div>
<?php require __DIR__ . '/_form.php'; ?>
</div></section>
