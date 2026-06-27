<?php



use App\Helpers\ContractDocumentHelper;



$request = $request ?? [];

$signer = $signer ?? [];

$signedSigners = $signedSigners ?? [$signer];

$renderedHtml = ContractDocumentHelper::normalizeDocumentText((string) ($renderedHtml ?? ''));

$auditUrl = $auditUrl ?? '';

$config = require dirname(__DIR__, 4) . '/config/app.php';

$org = (array) ($config['organization'] ?? []);

$legal = (array) ($org['legal_entity'] ?? []);

$branding = (array) ($org['branding'] ?? []);



$title = ContractDocumentHelper::normalizeDocumentText((string) ($request['title'] ?? 'Contrato'));

$titleLines = ContractDocumentHelper::titleLines($title);

$primarySigner = $signedSigners[0] ?? $signer;

$maskedDocument = ContractDocumentHelper::maskDocument((string) ($primarySigner['signer_document'] ?? ''));

$festivalLogo = ContractDocumentHelper::brandingUrl((string) ($branding['festival_logo'] ?? 'assets/img/branding/danca-carajas-logo.png'));

$producerLogo = ContractDocumentHelper::brandingUrl((string) ($branding['producer_logo'] ?? 'assets/img/branding/ja-producoes-logo.png'));

$producerName = (string) ($legal['trade_name'] ?? $legal['name'] ?? 'JA Produções Artísticas');

$issuedAt = (string) ($request['signed_at'] ?? $primarySigner['signed_at'] ?? '');

$captadorName = '';

$contratanteName = $producerName;

foreach ($signedSigners as $s) {

    if (($s['signer_role'] ?? '') === 'captador') {

        $captadorName = (string) ($s['signer_name'] ?? '');

    }

    if (($s['signer_role'] ?? '') === 'contratante') {

        $contratanteName = (string) ($s['signer_name'] ?? $producerName);

    }

}

?>

<article class="contract-page">

    <header class="contract-header">

        <div class="contract-accent" aria-hidden="true"></div>

        <div class="contract-header-main">

            <div class="contract-logo contract-logo-left">

                <img src="<?= e($festivalLogo) ?>" alt="Dança Carajás Festival">

            </div>

            <div class="contract-header-center">

                <span class="contract-kicker">Documento contratual</span>

                <strong><?= e($titleLines[0]) ?></strong>

                <?php if (isset($titleLines[1])): ?>

                    <strong><?= e($titleLines[1]) ?></strong>

                <?php endif; ?>

                <small>Dança Carajás Festival</small>

            </div>

            <div class="contract-logo contract-logo-right contract-logo--on-dark">

                <img src="<?= e($producerLogo) ?>" alt="<?= e($producerName) ?>">

            </div>

        </div>

        <div class="contract-meta-strip">

            <?php if (!empty($request['reference'])): ?>

                <span><?= e((string) $request['reference']) ?></span>

            <?php endif; ?>

            <?php if ($issuedAt !== ''): ?>

                <span>Concluído em <strong><?= e($issuedAt) ?></strong></span>

            <?php endif; ?>

        </div>

    </header>



    <div class="contract-title-block">

        <?php foreach ($titleLines as $line): ?>

            <h1><?= e($line) ?></h1>

        <?php endforeach; ?>

        <p class="contract-subtitle"><?= e(ContractDocumentHelper::documentSubtitle($title)) ?></p>

    </div>



    <div class="contract-info-grid">

        <div>

            <small>Candidatura / Processo</small>

            <strong><?= e((string) ($request['reference'] ?? '—')) ?></strong>

        </div>

        <div>

            <small>Captador externo</small>

            <strong><?= e($captadorName !== '' ? $captadorName : (string) ($primarySigner['signer_name'] ?? '')) ?></strong>

        </div>

        <div>

            <small>Contratante</small>

            <strong><?= e($contratanteName) ?></strong>

        </div>

        <div>

            <small>Partes assinantes</small>

            <strong><?= count($signedSigners) ?> de 2</strong>

        </div>

    </div>



    <div class="contract-body contract-document-body">

        <?= $renderedHtml ?>

    </div>



    <p class="signature-proofs-heading">Assinaturas eletrônicas das partes</p>



    <?php foreach ($signedSigners as $proofSigner): ?>

        <?php

        $proofLabel = match ((string) ($proofSigner['signer_role'] ?? '')) {

            'contratante' => 'Contratante',

            'captador'    => 'Captador externo',

            default       => 'Signatário',

        };

        require dirname(__DIR__, 2) . '/partials/signature_proof_block.php';

        ?>

    <?php endforeach; ?>



    <?php if ($auditUrl): ?>

        <a href="<?= e($auditUrl) ?>" class="signature-audit-link">Verificar autenticidade e trilha de auditoria</a>

    <?php endif; ?>



    <p class="signature-legal-note" style="margin: 0 28px 20px; padding: 12px 0; border-top: 1px solid #dededb;">

        Assinaturas eletrônicas realizadas por aceite inequívoco, com registro de data, hora, IP, agente de usuário,

        hash criptográfico e trilha de auditoria, nos termos da Lei nº 14.063/2020.

    </p>



    <footer class="contract-footer">

        <div>

            <strong><?= e($producerName) ?></strong>

            <span><?= e((string) ($legal['role'] ?? 'Responsável jurídica pela contratação')) ?></span>

        </div>

        <div>

            <strong><?= e((string) ($org['name'] ?? 'Dança Carajás Festival')) ?></strong>

            <span><?= e($config['name'] ?? 'Dança Carajás Captação') ?></span>

        </div>

    </footer>

</article>


