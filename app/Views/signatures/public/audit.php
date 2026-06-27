<?php

use App\Helpers\ContractDocumentHelper;



$signer = $signer ?? [];

$request = $request ?? [];

$verified = (bool) ($verified ?? false);

$config = require dirname(__DIR__, 4) . '/config/app.php';

$org = (array) ($config['organization'] ?? []);

$legal = (array) ($org['legal_entity'] ?? []);

$branding = (array) ($org['branding'] ?? []);

$verificationCode = ContractDocumentHelper::formatVerificationCode((string) ($signer['signature_hash'] ?? ''));

$maskedDocument = ContractDocumentHelper::maskDocument((string) ($signer['signer_document'] ?? ''));

$documentUrl = $documentUrl ?? '';

$auditTitle = ContractDocumentHelper::normalizeDocumentText((string) ($request['title'] ?? 'Documento contratual'));

$festivalLogo = ContractDocumentHelper::brandingUrl((string) ($branding['festival_logo'] ?? 'assets/img/branding/danca-carajas-logo.png'));

$producerLogo = ContractDocumentHelper::brandingUrl((string) ($branding['producer_logo'] ?? 'assets/img/branding/ja-producoes-logo.png'));

$producerName = (string) ($legal['trade_name'] ?? $legal['name'] ?? 'JA Produções Artísticas');

$signedIp = (string) ($signer['signed_ip'] ?? '');

$signedIpDisplay = $signedIp !== '' && $signedIp !== '0.0.0.0' ? $signedIp : 'Registrado na trilha de auditoria';

?>

<article class="contract-page audit-page">

    <header class="contract-header">

        <div class="contract-accent" aria-hidden="true"></div>

        <div class="contract-header-main">

            <div class="contract-logo contract-logo-left">

                <img src="<?= e($festivalLogo) ?>" alt="Dança Carajás festival">

            </div>

            <div class="contract-header-center">

                <span class="contract-kicker">Auditoria de assinatura</span>

                <strong>Verificação de autenticidade</strong>

                <small><?= e($auditTitle) ?></small>

            </div>

            <div class="contract-logo contract-logo-right contract-logo--on-dark">

                <img src="<?= e($producerLogo) ?>" alt="<?= e($producerName) ?>">

            </div>

        </div>

    </header>



    <div class="audit-status-banner <?= $verified ? 'audit-status-banner--ok' : 'audit-status-banner--fail' ?>">

        <?= $verified

            ? 'Assinatura eletrônica verificada. O documento e a trilha de auditoria estão íntegros.'

            : 'Não foi possível verificar esta assinatura.' ?>

    </div>



    <section class="signature-proof signature-proof--audit" aria-label="Registro de auditoria">

        <div class="signature-proof__header">

            <div class="signature-proof__brand">

                <?php require dirname(__DIR__, 2) . '/partials/signature_seal.php'; ?>

                <div class="signature-proof__brand-text">

                    <span class="signature-proof__brand-title">Registro de auditoria</span>

                    <span class="signature-proof__brand-sub">Comprovante de assinatura eletrônica</span>

                </div>

            </div>

            <div class="signature-proof__status <?= $verified ? '' : 'signature-proof__status--fail' ?>">

                <?= $verified ? 'Assinatura válida' : 'Assinatura inválida' ?>

            </div>

        </div>



        <div class="signature-proof__grid signature-proof__grid--audit">

            <div>

                <small>Status</small>

                <strong><?= $verified ? 'Válida' : 'Inválida' ?></strong>

            </div>

            <div>

                <small>Signatário</small>

                <strong><?= e($signer['signer_name'] ?? '') ?></strong>

            </div>

            <div>

                <small>E-mail</small>

                <strong class="text-break"><?= e($signer['signer_email'] ?? '—') ?></strong>

            </div>

            <div>

                <small>Documento</small>

                <strong><?= e($maskedDocument) ?></strong>

            </div>

            <div>

                <small>Assinado em</small>

                <strong><?= e($signer['signed_at'] ?? '') ?></strong>

            </div>

            <div>

                <small>Endereço IP</small>

                <strong><?= e($signedIpDisplay) ?></strong>

            </div>

            <div>

                <small>Código de verificação</small>

                <strong class="signature-code"><?= e($verificationCode) ?></strong>

            </div>

            <div>

                <small>Processo nº</small>

                <strong><?= e((string) ($request['id'] ?? '')) ?></strong>

            </div>

        </div>



        <div class="signature-hash">

            <small>Hash do conteúdo (SHA-256)</small>

            <code><?= e($request['content_hash'] ?? '') ?></code>

        </div>

        <div class="signature-hash">

            <small>Hash da assinatura (SHA-256)</small>

            <code><?= e($signer['signature_hash'] ?? '') ?></code>

        </div>



        <?php if (!empty($signer['acceptance_text'])): ?>

            <blockquote class="signature-acceptance-quote"><?= e((string) $signer['acceptance_text']) ?></blockquote>

        <?php endif; ?>



        <p class="signature-legal-note">

            Este registro comprova assinatura eletrônica avançada por aceite inequívoco, com identificação do signatário,

            manifestação de vontade, integridade do conteúdo (hash SHA-256), registro de data/hora, endereço IP e trilha

            de auditoria imutável, em conformidade com a Lei nº 14.063/2020 e demais normas aplicáveis ao uso de

            assinaturas eletrônicas no Brasil.

        </p>

    </section>



    <div class="audit-legal-meta">

        <p><strong>Contratante / responsável jurídica:</strong> <?= e($producerName) ?></p>

        <p><strong>Evento:</strong> <?= e((string) ($org['name'] ?? 'Dança Carajás festival')) ?></p>

    </div>



    <?php if ($documentUrl): ?>

        <div class="audit-actions no-print">

            <a href="<?= e($documentUrl) ?>" class="audit-action-btn">Ver documento assinado</a>

        </div>

    <?php endif; ?>



    <footer class="contract-footer">

        <div>

            <strong><?= e($producerName) ?></strong>

            <span><?= e((string) ($legal['role'] ?? 'Responsável jurídica pela contratação')) ?></span>

        </div>

        <div>

            <strong><?= e((string) ($org['name'] ?? 'Dança Carajás festival')) ?></strong>

            <span><?= e($config['name'] ?? 'Dança Carajás Captação') ?></span>

        </div>

    </footer>

</article>


