<?php
/** Tela exibida quando o captador nao tem acesso liberado ao portal. */
$message = $message ?? 'Sua conta ainda nao esta vinculada a um cadastro de captador aprovado.';
?>
<div class="pt-card">
    <h2>Portal do Captador</h2>
    <p class="pt-muted"><?= e($message) ?></p>
    <p>Se voce ja concluiu o credenciamento e assinou os documentos, aguarde a finalizacao do vinculo pela equipe Danca Carajas ou entre em contato para regularizar o seu acesso.</p>
    <div class="pt-actions">
        <form method="post" action="<?= e(app_url('/logout')) ?>" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="pt-btn secondary"><i data-lucide="log-out"></i> Sair</button>
        </form>
    </div>
</div>