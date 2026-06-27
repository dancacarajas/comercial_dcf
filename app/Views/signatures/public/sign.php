<?php
$signer = $signer ?? [];
$renderedHtml = $renderedHtml ?? '';
$maskedDocument = $maskedDocument ?? '—';
$errors = $errors ?? [];
$old = $old ?? [];
$token = (string) ($signer['public_token'] ?? '');
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Assinatura eletrônica</span>
        <h1 class="h2-section"><?= e($signer['request_title'] ?? 'Documento') ?></h1>
        <p class="page-sub">Signatário: <?= e($signer['signer_name'] ?? '') ?> · Documento: <?= e($maskedDocument) ?></p>
    </div>
</div>

<div class="alert alert-info" style="margin-bottom:18px;">
    <i data-lucide="info"></i>
    <span>Esta é uma assinatura eletrônica simples por aceite, para controle interno. Integrações com certificadoras externas poderão ser implantadas futuramente.</span>
</div>

<div class="card contract-document-body" style="margin-bottom:18px;"><?= $renderedHtml ?></div>

<div class="card">
    <h3 class="h3-card">Confirmar assinatura</h3>
    <form method="post" action="<?= e(app_url('/assinatura/' . rawurlencode($token) . '/sign')) ?>">
        <?= csrf_field() ?>
        <label class="check-inline" style="display:block;margin-bottom:12px;">
            <input type="checkbox" name="accept_terms" value="1" <?= !empty($old['accept_terms']) ? 'checked' : '' ?> required>
            Li e concordo com os termos deste documento.
        </label>
        <?php if (!empty($errors['accept_terms'])): ?><p class="field-error"><?= e($errors['accept_terms']) ?></p><?php endif; ?>

        <div class="form-group">
            <label for="confirmed_name">Digite seu nome completo para confirmar</label>
            <input type="text" id="confirmed_name" name="confirmed_name" class="input" required
                   value="<?= e((string) ($old['confirmed_name'] ?? '')) ?>"
                   placeholder="<?= e($signer['signer_name'] ?? '') ?>">
            <?php if (!empty($errors['confirmed_name'])): ?><p class="field-error"><?= e($errors['confirmed_name']) ?></p><?php endif; ?>
        </div>

        <button type="submit" class="btn btn-yellow">Assinar eletronicamente</button>
    </form>
</div>
</div></section>
