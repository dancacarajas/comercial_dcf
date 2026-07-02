<?php
$signer = $signer ?? [];
$renderedHtml = $renderedHtml ?? '';
$maskedDocument = $maskedDocument ?? '—';
$errors = $errors ?? [];
$old = $old ?? [];
$token = (string) ($signer['public_token'] ?? '');
$hasErrors = $errors !== [];
?>
<section class="section"><div class="container">
<?php if ($hasErrors): ?>
<div class="signature-error-modal" role="alertdialog" aria-modal="true" aria-labelledby="signature-error-title" aria-describedby="signature-error-description">
    <div class="signature-error-modal__backdrop" aria-hidden="true"></div>
    <div class="signature-error-modal__panel">
        <div class="signature-error-modal__icon" aria-hidden="true">
            <i data-lucide="alert-triangle"></i>
        </div>
        <div>
            <p class="signature-error-modal__eyebrow">Assinatura pendente</p>
            <h2 id="signature-error-title">Assinatura não concluída</h2>
            <p id="signature-error-description">
                O documento ainda <strong>não foi assinado</strong>. Corrija os dados destacados abaixo e clique novamente em
                <strong>Assinar eletronicamente</strong>.
            </p>
            <ul class="signature-error-modal__list">
                <?php foreach ($errors as $error): ?>
                    <li><?= e((string) $error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn btn-yellow signature-error-modal__action" autofocus>
                Corrigir e tentar novamente
            </button>
        </div>
    </div>
</div>
<?php endif; ?>
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
                   placeholder="<?= e($signer['signer_name'] ?? '') ?>"
                   aria-invalid="<?= !empty($errors['confirmed_name']) ? 'true' : 'false' ?>">
            <?php if (!empty($errors['confirmed_name'])): ?><p class="field-error"><?= e($errors['confirmed_name']) ?></p><?php endif; ?>
        </div>

        <button type="submit" class="btn btn-yellow">Assinar eletronicamente</button>
    </form>
</div>
</div></section>
<?php if ($hasErrors): ?>
<script src="/assets/vendor/lucide/lucide.min.js"></script>
<style>
.signature-error-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: grid;
    place-items: center;
    padding: 24px;
}
.signature-error-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(17, 17, 17, .68);
    backdrop-filter: blur(3px);
}
.signature-error-modal__panel {
    position: relative;
    width: min(620px, 100%);
    display: grid;
    grid-template-columns: 56px 1fr;
    gap: 18px;
    padding: 26px;
    border-radius: 18px;
    border-top: 6px solid #f4c400;
    background: #fff;
    box-shadow: 0 28px 80px rgba(0, 0, 0, .34);
}
.signature-error-modal__icon {
    width: 56px;
    height: 56px;
    display: grid;
    place-items: center;
    border-radius: 16px;
    color: #111;
    background: #f4c400;
}
.signature-error-modal__icon svg {
    width: 30px;
    height: 30px;
}
.signature-error-modal__eyebrow {
    margin: 0 0 4px;
    color: #667085;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.signature-error-modal h2 {
    margin: 0 0 10px;
    color: #111;
    font-size: 28px;
    line-height: 1.1;
}
.signature-error-modal p {
    margin: 0 0 14px;
    color: #344054;
    font-size: 16px;
    line-height: 1.55;
}
.signature-error-modal__list {
    margin: 0 0 18px;
    padding: 12px 16px 12px 34px;
    border: 1px solid #fed7aa;
    border-radius: 12px;
    background: #fff7ed;
    color: #9a3412;
    font-weight: 700;
}
.signature-error-modal__action {
    width: auto;
}
@media (max-width: 640px) {
    .signature-error-modal {
        align-items: end;
        padding: 12px;
    }
    .signature-error-modal__panel {
        grid-template-columns: 1fr;
        gap: 12px;
        padding: 22px;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
    var action = document.querySelector('.signature-error-modal__action');
    var nameInput = document.getElementById('confirmed_name');
    var modal = document.querySelector('.signature-error-modal');
    if (!action || !modal) {
        return;
    }
    action.addEventListener('click', function () {
        modal.remove();
        if (nameInput) {
            nameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            nameInput.focus();
            nameInput.select();
        }
    });
});
</script>
<?php endif; ?>
