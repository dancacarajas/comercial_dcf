<?php
$project = $project ?? [];
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Projetos · PRONACs</span>
        <h1 class="h2-section">Novo projeto incentivado</h1>
        <p class="page-sub">Cadastro-mãe do plano de captação.</p>
    </div>
    <a href="<?= e(app_url('/projects')) ?>" class="btn btn-sm btn-outline">Voltar</a>
</div>
<?php require __DIR__ . '/_form.php'; ?>
</div></section>
