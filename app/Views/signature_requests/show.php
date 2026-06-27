<?php

$item = $item ?? [];

$signers = $signers ?? [];

$signLinks = $signLinks ?? [];

$statuses = $statuses ?? [];

$id = (int) ($item['id'] ?? 0);

$requestStatus = (string) ($item['status'] ?? '');

$contratantePending = null;

$captadorPending = null;

foreach ($signers as $s) {

    if (($s['signer_role'] ?? '') === 'contratante' && ($s['status'] ?? '') !== 'assinado') {

        $contratantePending = $s;

    }

    if (($s['signer_role'] ?? '') === 'captador' && ($s['status'] ?? '') !== 'assinado') {

        $captadorPending = $s;

    }

}

$roleLabels = [

    'captador'    => 'Captador externo',

    'contratante' => 'Contratante (JA Produções)',

];

?>

<section class="section"><div class="container">

<div class="page-head">

    <div>

        <span class="kicker kicker-dark">Assinaturas</span>

        <h1 class="h2-section"><?= e($item['title'] ?? '') ?></h1>

        <p class="page-sub"><?= e($statuses[$requestStatus] ?? $requestStatus) ?></p>

    </div>

    <a href="<?= e(app_url('/signature-requests')) ?>" class="btn btn-sm btn-outline">Voltar</a>

</div>



<?php if ($requestStatus === 'parcialmente_assinado' && $captadorPending !== null && $contratantePending === null): ?>

<div class="alert alert-info" style="margin-bottom:18px;">

    A <strong>JA Produções Artísticas</strong> já assinou automaticamente. Aguardando a assinatura do <strong>captador externo</strong>.

</div>

<?php endif; ?>



<?php if ($requestStatus === 'parcialmente_assinado' && $contratantePending !== null && $captadorPending === null && can('signature_requests.send')): ?>

<div class="alert alert-warning" style="margin-bottom:18px;">

    O captador já assinou. Falta a <strong>assinatura da contratante (JA Produções Artísticas)</strong> para concluir o contrato.

</div>

<div class="card" style="margin-bottom:18px;">

    <h3 class="h3-card">Assinar em nome da contratante</h3>

    <p class="page-sub">Como administrador autorizado, registre a assinatura eletrônica da JA Produções Artísticas LTDA.</p>

    <form method="post" action="<?= e(app_url('/signature-requests/' . $id . '/sign-contratante')) ?>" style="margin-top:12px;">

        <?= csrf_field() ?>

        <label style="display:flex;align-items:flex-start;gap:8px;margin-bottom:12px;">

            <input type="checkbox" name="accept_terms" value="1" required>

            <span>Assino eletronicamente em nome da <strong><?= e((string) ($contratantePending['signer_name'] ?? 'JA PRODUÇÕES ARTÍSTICAS LTDA')) ?></strong>, na qualidade de contratante, declarando concordância integral com o instrumento contratual.</span>

        </label>

        <button type="submit" class="btn btn-yellow">Assinar como contratante</button>

    </form>

</div>

<?php endif; ?>



<div class="detail-grid">

    <div class="card">

        <h3 class="h3-card">Processo</h3>

        <dl class="detail-list">

            <dt>Origem</dt><dd><?= e($item['source_type'] ?? '') ?> #<?= (int) ($item['source_id'] ?? 0) ?></dd>

            <dt>Hash conteúdo</dt><dd><code style="word-break:break-all;"><?= e($item['content_hash'] ?? '—') ?></code></dd>

            <dt>Enviado em</dt><dd><?= e($item['sent_at'] ?? '—') ?></dd>

            <dt>Assinado em</dt><dd><?= e($item['signed_at'] ?? '—') ?></dd>

            <dt>Expira em</dt><dd><?= e($item['public_token_expires_at'] ?? '—') ?></dd>

        </dl>

    </div>

    <div class="card">

        <h3 class="h3-card">Signatários</h3>

        <?php foreach ($signers as $signer): ?>

            <?php

            $role = (string) ($signer['signer_role'] ?? '');

            $roleLabel = $roleLabels[$role] ?? $role;

            ?>

            <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--dcx-border,#eee);">

                <strong><?= e($signer['signer_name'] ?? '') ?></strong>

                <span class="badge" style="margin-left:6px;"><?= e($roleLabel) ?></span><br>

                <?= e($signer['signer_email'] ?? '') ?> · <?= e($signer['status'] ?? '') ?><br>

                <?php if (!empty($signLinks[(int) $signer['id']])): ?>

                    <a href="<?= e($signLinks[(int) $signer['id']]) ?>" target="_blank" rel="noopener">Link público (captador)</a><br>

                <?php endif; ?>

                <?php if (!empty($signer['signed_at'])): ?>

                    <small>Assinado: <?= e($signer['signed_at']) ?> · IP: <?= e($signer['signed_ip'] ?? '—') ?></small>

                    <?php if (!empty($signer['signature_hash'])): ?><br><small>Hash: <code><?= e($signer['signature_hash']) ?></code></small><?php endif; ?>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<?php if (!empty($item['rendered_html'])): ?>

<div class="card contract-document-body" style="margin-top:18px;"><?= $item['rendered_html'] ?></div>

<?php endif; ?>

<div class="card" style="margin-top:18px;">

    <div class="actions-row">

        <?php if (can('signature_requests.send') && $requestStatus !== 'assinado'): ?>

            <form method="post" action="<?= e(app_url('/signature-requests/' . $id . '/send')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-yellow">Enviar</button></form>

        <?php endif; ?>

        <?php if (can('signature_requests.cancel') && $requestStatus !== 'assinado'): ?>

            <form method="post" action="<?= e(app_url('/signature-requests/' . $id . '/cancel')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline">Cancelar</button></form>

        <?php endif; ?>

        <?php if ($requestStatus === 'assinado'): ?>

            <a href="<?= e(app_url('/signature-requests/' . $id . '/pdf')) ?>" class="btn btn-sm btn-yellow">Baixar PDF assinado</a>

        <?php endif; ?>

        <?php if (can('signature_requests.archive')): ?>

            <?php if (empty($item['archived_at'])): ?>

                <form method="post" action="<?= e(app_url('/signature-requests/' . $id . '/archive')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline">Arquivar</button></form>

            <?php else: ?>

                <form method="post" action="<?= e(app_url('/signature-requests/' . $id . '/restore')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline">Restaurar</button></form>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>

</div></section>


