<?php
$heading = $heading ?? 'Erro';
$message = $message ?? '';
?>
<section class="section"><div class="container">
<div class="page-head"><h1 class="h2-section"><?= e($heading) ?></h1></div>
<div class="alert alert-error"><i data-lucide="alert-circle"></i><span><?= e($message) ?></span></div>
</div></section>
