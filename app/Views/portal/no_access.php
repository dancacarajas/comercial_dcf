<?php
/** Tela exibida quando o usuário tem perfil de captador, mas não está vinculado a um cadastro de captador. */
?>
<div class="pt-card">
    <h2>Portal do Captador</h2>
    <p class="pt-muted">Sua conta ainda não está vinculada a um cadastro de captador aprovado.</p>
    <p>Se você já concluiu o credenciamento e assinou os documentos, aguarde a finalização do vínculo pela equipe Dança Carajás ou entre em contato para regularizar o seu acesso.</p>
    <div class="pt-actions">
        <form method="post" action="<?= e(app_url('/logout')) ?>" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="pt-btn secondary"><i data-lucide="log-out"></i> Sair</button>
        </form>
    </div>
</div>
