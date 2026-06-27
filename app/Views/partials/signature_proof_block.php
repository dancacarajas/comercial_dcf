<?php
/** Bloco de comprovante de assinatura eletrônica (reutilizável por signatário). */
use App\Helpers\ContractDocumentHelper;

$proofSigner = $proofSigner ?? [];
$proofLabel = (string) ($proofLabel ?? 'Signatário');
$proofRole = (string) ($proofSigner['signer_role'] ?? '');
$proofTitle = match ($proofRole) {
    'contratante' => 'Assinatura da contratante',
    'captador'    => 'Assinatura do captador externo',
    default       => 'Comprovante de assinatura eletrônica',
};
$proofSubtitle = match ($proofRole) {
    'contratante' => 'JA Produções Artísticas · Dança Carajás Festival',
    'captador'    => 'Aceite eletrônico com trilha de auditoria',
    default       => 'Aceite eletrônico com trilha de auditoria',
};
$maskedDoc = ContractDocumentHelper::maskDocument((string) ($proofSigner['signer_document'] ?? ''));
$verificationCode = ContractDocumentHelper::formatVerificationCode((string) ($proofSigner['signature_hash'] ?? ''));
$signedAt = (string) ($proofSigner['signed_at'] ?? '');
$signedIp = (string) ($proofSigner['signed_ip'] ?? '');
$ipDisplay = $signedIp !== '' && $signedIp !== '0.0.0.0' ? $signedIp : 'Registrado na trilha de auditoria';
?>
<section class="signature-proof" aria-label="<?= e($proofTitle) ?>">
    <div class="signature-proof__header">
        <div class="signature-proof__brand">
            <?php require __DIR__ . '/signature_seal.php'; ?>
            <div class="signature-proof__brand-text">
                <span class="signature-proof__brand-title"><?= e($proofTitle) ?></span>
                <span class="signature-proof__brand-sub"><?= e($proofSubtitle) ?></span>
            </div>
        </div>
        <div class="signature-proof__status">Assinado</div>
    </div>
    <div class="signature-proof__grid">
        <div>
            <small><?= e($proofLabel) ?></small>
            <strong><?= e((string) ($proofSigner['signer_name'] ?? '')) ?></strong>
        </div>
        <div>
            <small>Documento</small>
            <strong><?= e($maskedDoc) ?></strong>
        </div>
        <div>
            <small>Assinado em</small>
            <strong><?= e(format_datetime_br($proofSigner['signed_at'] ?? null)) ?></strong>
        </div>
        <div>
            <small>Código de verificação</small>
            <strong class="signature-code"><?= e($verificationCode) ?></strong>
        </div>
    </div>
    <div class="signature-hash">
        <small>Hash criptográfico</small>
        <code><?= e((string) ($proofSigner['signature_hash'] ?? '')) ?></code>
    </div>
</section>
