<?php
$signer = $signer ?? [];
$documentUrl = $documentUrl ?? '';
$auditUrl = $auditUrl ?? '';
$fullySigned = (bool) ($fullySigned ?? false);

$publicJourneyUrl = (string) ($publicJourneyUrl ?? '');
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark"><?= $fullySigned ? 'Assinatura concluída' : 'Assinatura registrada' ?></span>
        <h1 class="h2-section"><?= $fullySigned ? 'Documento assinado por todas as partes' : 'Sua assinatura foi registrada' ?></h1>
        <p class="page-sub"><?= e($signer['request_title'] ?? '') ?></p>
    </div>
</div>
<div class="alert alert-success">
    <i data-lucide="check-circle"></i>
    <span><?= $fullySigned
        ? 'Contrato assinado pelo captador e pela contratante (JA Produções). Documento disponível para visualização.'
        : 'Sua assinatura eletrônica foi registrada. O contrato aguarda a assinatura da contratante (JA Produções Artísticas) pelo administrador do sistema.' ?></span>
</div>
<?php if ($publicJourneyUrl !== ''): ?>

<div class="card" style="margin-top:18px;border-top:5px solid #f4c400;">

    <h3 class="h3-card">Continuar assinaturas</h3>

    <p class="page-sub" style="margin-bottom:16px;">Sua assinatura deste documento foi registrada. Volte para sua etapa de credenciamento para assinar os demais documentos pendentes.</p>

    <a href="<?= e($publicJourneyUrl) ?>" class="btn btn-yellow">Voltar para meus documentos</a>

</div>

<?php endif; ?>

<?php if ($fullySigned && $documentUrl): ?>
<div class="card" style="margin-top:18px;">
    <h3 class="h3-card"><i data-lucide="file-text"></i> Contrato assinado</h3>
    <p class="page-sub" style="margin-bottom:16px;">Visualize o documento no navegador, com layout oficial e assinaturas das duas partes.</p>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="<?= e($documentUrl) ?>" class="btn btn-yellow">Ver contrato assinado</a>
        <?php if ($auditUrl): ?>
            <a href="<?= e($auditUrl) ?>" class="btn btn-outline">Auditoria da assinatura</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php if (!$fullySigned): ?>
<p class="text-sm text-muted-dcx" style="margin-top:18px;">A equipe do Dança Carajás concluirá a assinatura da contratante em breve. Você será notificado quando o documento estiver completo.</p>
<?php else: ?>
<p class="text-sm text-muted-dcx" style="margin-top:18px;">A equipe do Dança Carajás dará continuidade ao seu credenciamento.</p>
<?php endif; ?>
</div></section>

