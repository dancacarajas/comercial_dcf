<?php

$application = $application ?? [];

$documents = $documents ?? [];

$steps = $steps ?? [];

$currentStep = $currentStep ?? 'manifestacao';

$docStatuses = $docStatuses ?? [];

$statuses = $statuses ?? [];

$token = (string) ($application['public_token'] ?? '');

$entityTypeLabel = $entityTypeLabel ?? 'Pessoa física (CPF)';

$isLegalEntity = !empty($isLegalEntity);

$showRegistrationForm = !empty($showRegistrationForm);
$signatureStageItems = $signatureStageItems ?? [];
$signatureProgress = $signatureProgress ?? ['signed_required' => 0, 'total_required' => 0, 'signed_total' => 0, 'total_enabled' => 0, 'all_required_signed' => false];
$docProgress = $docProgress ?? ['total' => 0, 'submitted' => 0, 'pending' => 0, 'approved' => 0];
$allDocumentsSubmitted = !empty($allDocumentsSubmitted);
$docTotal = (int) ($docProgress['total'] ?? 0);
$docSubmitted = (int) ($docProgress['submitted'] ?? 0);
$docPending = (int) ($docProgress['pending'] ?? 0);
$sigSigned = (int) ($signatureProgress['signed_total'] ?? 0);
$sigTotal = (int) ($signatureProgress['total_enabled'] ?? 0);
$sigSignedRequired = (int) ($signatureProgress['signed_required'] ?? 0);
$sigTotalRequired = (int) ($signatureProgress['total_required'] ?? 0);
$allSignaturesSigned = !empty($signatureProgress['all_required_signed']);



$rawStatus = (string) ($application['status'] ?? '');

$isArchived = !empty($application['archived_at']) || $rawStatus === 'arquivado';

$statusLabel = $statuses[$rawStatus] ?? $rawStatus;

$nameParts = explode(' ', trim((string) ($application['name'] ?? '')));

$firstName = ($nameParts[0] ?? '') !== '' ? $nameParts[0] : 'interessado';



$docBadgeClass = static function (string $st): string {

    return match ($st) {

        'aprovado' => 'badge-document badge-document-aprovado',

        'enviado', 'em_analise' => 'badge-document badge-document-enviado',

        'reprovado', 'substituir' => 'badge-document badge-document-expirado',

        default => 'badge-document badge-document-em_revisao',

    };

};

?>

<section class="section">

    <div class="container">

        <div class="page-head">

            <div>

                <span class="kicker kicker-dark">CRM · Captação</span>

                <h1 class="h2-section">Credenciamento de captador</h1>

                <p class="page-sub">

                    Olá, <?= e($firstName) ?>. Acompanhe abaixo o andamento do seu credenciamento no Dança Carajás.

                </p>

            </div>

        </div>



        <div class="card" style="margin-bottom:18px;">

            <h3 class="h3-card"><i data-lucide="route"></i> Etapas do processo</h3>

            <ol class="collector-journey-steps" aria-label="Etapas do credenciamento">

                <?php

                $stepKeys = array_keys($steps);

                $currentIdx = array_search($currentStep, $stepKeys, true);

                foreach ($steps as $key => $label):

                    $idx = array_search($key, $stepKeys, true);

                    if ($isArchived && $idx !== false && $currentIdx !== false && $idx <= $currentIdx) {

                        $state = 'done';

                    } elseif ($idx !== false && $currentIdx !== false && $idx < $currentIdx) {

                        $state = 'done';

                    } elseif ($key === $currentStep) {

                        $state = 'current';

                    } else {

                        $state = 'pending';

                    }

                ?>

                    <li class="collector-journey-step is-<?= e($state) ?>">

                        <span class="collector-journey-step__num"><?= (int) $idx + 1 ?></span>

                        <span class="collector-journey-step__label"><?= e($label) ?></span>

                    </li>

                <?php endforeach; ?>

            </ol>

        </div>



        <?php if ($isArchived): ?>

            <div class="alert alert-info">

                <i data-lucide="archive"></i>

                <span>Este processo foi <strong>arquivado</strong> pela equipe. Para retomar, entre em contato com o Dança Carajás.</span>

            </div>

        <?php elseif ($rawStatus === 'reprovado'): ?>

            <div class="alert alert-error">

                <i data-lucide="x-circle"></i>

                <span>Sua candidatura não foi aprovada neste momento. A equipe entrará em contato se necessário.</span>

            </div>

        <?php else: ?>

            <div class="card collector-status-card">

                <h3 class="h3-card"><i data-lucide="activity"></i> Status atual</h3>

                <p class="collector-status-card__value"><?= e($statusLabel) ?></p>

            </div>

        <?php endif; ?>



        <?php if (in_array($rawStatus, ['aguardando_assinatura_contratual', 'aprovado'], true) && $signatureStageItems !== []): ?>

            <div class="card" style="margin:18px 0;">

                <h3 class="h3-card"><i data-lucide="file-signature"></i> Documentos para assinatura</h3>

                <p class="page-sub" style="margin-bottom:12px;">

                    Sua candidatura foi aprovada. Para continuar, leia e assine todos os documentos abaixo.

                </p>

                <?php if ($sigTotalRequired > 0): ?>
                    <p class="text-sm mb-3"><strong><?= $sigSignedRequired ?> de <?= $sigTotalRequired ?></strong> documento(s) obrigatório(s) assinado(s).</p>
                <?php elseif ($sigTotal > 0): ?>
                    <p class="text-sm mb-3"><strong><?= $sigSigned ?> de <?= $sigTotal ?></strong> documento(s) assinado(s).</p>
                <?php endif; ?>

                <div class="collector-signature-cards">
                    <?php foreach ($signatureStageItems as $item):
                        $isSigned = !empty($item['is_signed']);
                        $captadorLink = (string) ($item['captador_link'] ?? '');
                        $pdfUrl = (string) ($item['pdf_url'] ?? '');
                    ?>
                        <div class="card collector-signature-card" style="margin-bottom:12px;padding:16px;">
                            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                                <div>
                                    <h4 class="h4-card" style="margin:0 0 6px;"><?= e($item['title'] ?? 'Documento') ?></h4>
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="text-sm text-muted-dcx mb-2"><?= e($item['description']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-sm mb-0">
                                        Status: <span class="badge"><?= e($item['request_status'] ?? 'pendente') ?></span>
                                        <?php if (!empty($item['sent_at'])): ?> · Enviado em <?= e(format_datetime_br($item['sent_at'])) ?><?php endif; ?>
                                        <?php if (!empty($item['signed_at'])): ?> · Assinado em <?= e(format_datetime_br($item['signed_at'])) ?><?php endif; ?>
                                    </p>
                                </div>
                                <div class="actions-row">
                                    <?php if (!$isSigned && $captadorLink !== ''): ?>
                                        <a href="<?= e($captadorLink) ?>" class="btn btn-yellow">Ler e assinar</a>
                                    <?php elseif ($isSigned && $pdfUrl !== ''): ?>
                                        <a href="<?= e($pdfUrl) ?>" class="btn btn-outline" download>Baixar PDF</a>
                                    <?php elseif ($isSigned): ?>
                                        <span class="badge badge-document badge-document-aprovado">Assinado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>

        <?php elseif ($rawStatus === 'contrato_assinado' || ($rawStatus === 'acesso_preparado' && !$showRegistrationForm)): ?>

            <div class="card" style="margin:18px 0;">

                <h3 class="h3-card"><i data-lucide="file-check"></i> Documentos assinados</h3>

                <p class="page-sub" style="margin-bottom:16px;">

                    <?php if ($allSignaturesSigned || $signatureStageItems === []): ?>
                        Todos os documentos foram assinados. Aguarde a liberação de acesso pela equipe.
                    <?php else: ?>
                        Seus documentos contratuais foram assinados. Aguarde a liberação de acesso pelo administrador.
                    <?php endif; ?>

                </p>

                <?php if ($signatureStageItems !== []): ?>
                    <div class="collector-signature-cards">
                        <?php foreach ($signatureStageItems as $item):
                            if (empty($item['is_signed']) || empty($item['pdf_url'])) {
                                continue;
                            }
                        ?>
                            <a href="<?= e((string) $item['pdf_url']) ?>" class="btn btn-outline btn-sm" style="margin-right:8px;margin-bottom:8px;" download>
                                Baixar: <?= e($item['title'] ?? 'Documento') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

        <?php endif; ?>



        <?php if ($showRegistrationForm): ?>

            <div class="card collector-register-card" style="margin:18px 0;">

                <h3 class="h3-card"><i data-lucide="user-plus"></i> Crie seu acesso ao sistema</h3>

                <p class="page-sub" style="margin-bottom:16px;">

                    Seu credenciamento foi aprovado. Defina abaixo seu usuário e senha para entrar no Dança Carajás Captação.

                    Após concluir, este link de credenciamento não será mais necessário.

                </p>

                <form method="post" action="<?= e(app_url('/captadores/credenciamento/' . rawurlencode($token) . '/cadastro')) ?>" class="collector-register-form">

                    <div class="form-grid">

                        <div class="form-group">

                            <label for="reg_name">Nome completo</label>

                            <input type="text" id="reg_name" name="name" class="input" required minlength="3"

                                   value="<?= e((string) ($application['name'] ?? '')) ?>">

                        </div>

                        <div class="form-group">

                            <label for="reg_email">E-mail (login)</label>

                            <input type="email" id="reg_email" name="email" class="input" required readonly

                                   value="<?= e((string) ($application['email'] ?? '')) ?>">

                        </div>

                        <div class="form-group">

                            <label for="reg_password">Senha</label>

                            <input type="password" id="reg_password" name="password" class="input" required minlength="8" autocomplete="new-password">

                            <p class="text-sm text-muted-dcx mb-0">Mínimo de 8 caracteres.</p>

                        </div>

                        <div class="form-group">

                            <label for="reg_password_confirmation">Confirmar senha</label>

                            <input type="password" id="reg_password_confirmation" name="password_confirmation" class="input" required minlength="8" autocomplete="new-password">

                        </div>

                    </div>

                    <button type="submit" class="btn btn-yellow">Concluir cadastro e entrar</button>

                </form>

            </div>

        <?php endif; ?>



        <?php if (!$showRegistrationForm): ?>

            <div class="notice notice-warn" style="margin:18px 0;">

                <p><i data-lucide="shield-check" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"></i>

                    Seus dados pessoais são tratados conforme a LGPD. Esta etapa é destinada ao envio de documentos cadastrais, bancários e comprobatórios de experiência. Documentos contratuais serão assinados apenas após aprovação, na etapa de assinatura contratual.</p>

            </div>



            <div class="card">

                <h3 class="h3-card"><i data-lucide="folder-up"></i> Documentos solicitados</h3>

                <p class="text-sm text-muted-dcx" style="margin:-4px 0 14px;">

                    Perfil identificado na manifestação: <strong><?= e($entityTypeLabel) ?></strong>.

                    <?php if ($isLegalEntity): ?>

                        Inclui documentação da empresa (CNPJ, ato de constituição, comprovante bancário) e do representante.

                    <?php else: ?>

                        Inclui documentação pessoal e comprovante bancário.

                    <?php endif; ?>

                </p>
                <?php if ($docTotal > 0): ?>
                    <div class="collector-doc-progress" style="margin-bottom:16px;">
                        <div class="collector-doc-progress__bar" aria-hidden="true">
                            <span style="width:<?= $docTotal > 0 ? (int) round(($docSubmitted / $docTotal) * 100) : 0 ?>%;"></span>
                        </div>
                        <p class="text-sm mb-0">
                            <strong><?= $docSubmitted ?> de <?= $docTotal ?></strong> documento(s) enviado(s).
                            <?php if (!$allDocumentsSubmitted): ?>
                                O envio de <strong>todos</strong> os itens abaixo é obrigatório para avançar na esteira.
                            <?php else: ?>
                                Pacote completo recebido. Aguarde a análise da equipe.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if (!$allDocumentsSubmitted): ?>
                        <div class="alert alert-info" style="margin-bottom:14px;">
                            <i data-lucide="alert-circle"></i>
                            <span>Faltam <strong><?= $docPending ?></strong> documento(s). A etapa só avança após o envio integral.</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($documents === []): ?>

                    <div class="empty-state">

                        <i data-lucide="file-text"></i>

                        <p>A equipe ainda não solicitou documentos. Você será avisado quando puder enviá-los por este link.</p>

                    </div>

                <?php else: ?>

                    <div class="table-wrap">

                        <table>

                            <thead>

                                <tr>

                                    <th>Documento</th>

                                    <th>Status</th>

                                    <th>Enviado em</th>

                                    <th>Ação</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($documents as $doc):

                                    $docSt = (string) ($doc['status'] ?? '');

                                    $docLabel = $docStatuses[$docSt] ?? $docSt;

                                    $canUpload = !$isArchived && in_array($docSt, ['pendente', 'substituir', 'reprovado'], true);

                                ?>

                                    <tr>

                                        <td><strong><?= e($doc['title'] ?? '') ?></strong></td>

                                        <td><span class="<?= e($docBadgeClass($docSt)) ?>"><?= e($docLabel) ?></span></td>

                                        <td><?= e(format_datetime_br($doc['uploaded_at'] ?? null)) ?></td>

                                        <td>

                                            <?php if ($canUpload): ?>

                                                <form method="post" action="<?= e(app_url('/captadores/credenciamento/' . rawurlencode($token) . '/documents')) ?>" enctype="multipart/form-data" class="collector-doc-upload-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="document_slot_id" value="<?= (int) ($doc['id'] ?? 0) ?>">

                                                    <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.docx" required>

                                                    <button type="submit" class="btn btn-sm btn-yellow">Enviar</button>

                                                </form>

                                                <p class="text-sm text-muted-dcx mb-0">PDF, JPG, PNG ou DOCX — máx. 10 MB.</p>

                                            <?php elseif (!empty($doc['uploaded_at'])): ?>

                                                <span class="text-muted-dcx">Recebido</span>

                                            <?php else: ?>

                                                <span class="text-muted-dcx">—</span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</section>


