<?php $formAction = app_url('/leads'); $submitLabel = 'Cadastrar lead'; ?>
<section class="section"><div class="container">
<div class="page-head"><div><span class="kicker kicker-dark">Leads</span><h1 class="h2-section">Novo lead</h1></div>
<a href="<?= e(app_url('/leads')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a></div>
<?php require __DIR__ . '/_form.php'; ?>
</div></section>
