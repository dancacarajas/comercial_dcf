<?php
$document = $document ?? [];
$model    = $model ?? null;
$categories   = $categories ?? [];
$statuses     = $statuses ?? [];
$accessLevels = $accessLevels ?? [];

$did        = (int) ($document['id'] ?? 0);
$isArchived = !empty($document['archived_at']);
$st         = (string) ($document['status'] ?? '');
$cat        = (string) ($document['category'] ?? '');
$access     = (string) ($document['access_level'] ?? '');
$expired    = $model && $model->isExpired($document);
$dash       = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Documento comercial</span>
                <h1 class="h2-section"><?= e($document['title'] ?? '') ?></h1>
                <p class="page-sub">
                    <span class="badge-document document-status badge-document-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
                    <span class="badge-document document-category badge-document-<?= e($cat) ?>"><?= e($categories[$cat] ?? $cat) ?></span>
                    <span class="badge-document document-access badge-access-<?= e($access) ?>"><?= e($accessLevels[$access] ?? $access) ?></span>
                    <span class="document-version">v<?= (int) ($document['version_number'] ?? 1) ?></span>
                    <?php if ($expired): ?><span class="badge-document badge-document-expirado">Vencido</span><?php endif; ?>
                    <?php if ($isArchived): ?>
                        <span class="badge-status badge-status-arquivado">Arquivado em <?= e($document['archived_at']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/documents')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="detail-grid">
            <article class="card document-card">
                <h3 class="h3-card"><i data-lucide="link"></i> Vínculos</h3>
                <dl class="meta-list">
                    <dt>Empresa</dt>
                    <dd>
                        <?php if (!empty($document['company_id'])): ?>
                            <a href="<?= e(app_url('/companies/' . (int) $document['company_id'])) ?>" class="link-strong"><?= e($document['company_name'] ?? '—') ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Contato</dt>
                    <dd>
                        <?php if (!empty($document['contact_id'])): ?>
                            <a href="<?= e(app_url('/contacts/' . (int) $document['contact_id'])) ?>" class="link-strong"><?= e($document['contact_name'] ?? '—') ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Oportunidade</dt>
                    <dd>
                        <?php if (!empty($document['opportunity_id'])): ?>
                            <a href="<?= e(app_url('/opportunities/' . (int) $document['opportunity_id'])) ?>" class="link-strong"><?= e($document['opportunity_title'] ?? '—') ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Cota</dt>
                    <dd>
                        <?php if (!empty($document['quota_id'])): ?>
                            <a href="<?= e(app_url('/quotas/' . (int) $document['quota_id'])) ?>" class="link-strong"><?= e($document['quota_name'] ?? '—') ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Proposta</dt>
                    <dd>
                        <?php if (!empty($document['proposal_id'])): ?>
                            <a href="<?= e(app_url('/proposals/' . (int) $document['proposal_id'])) ?>" class="link-strong"><?= e($document['proposal_title'] ?? '—') ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Lead</dt>
                    <dd>
                        <?php if (!empty($document['lead_id'])): ?>
                            <a href="<?= e(app_url('/leads/' . (int) $document['lead_id'])) ?>" class="link-strong"><?= e($document['lead_name'] ?? '—') ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </dd>
                    <dt>Responsável</dt>
                    <dd><?= e($dash($document['responsible_name'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card document-card document-file-meta">
                <h3 class="h3-card"><i data-lucide="file"></i> Arquivo</h3>
                <dl class="meta-list">
                    <dt>Nome original</dt><dd><?= e($document['original_name'] ?? '—') ?></dd>
                    <dt>Extensão</dt><dd><?= e($document['extension'] ?? '—') ?></dd>
                    <dt>MIME type</dt><dd><?= e($document['mime_type'] ?? '—') ?></dd>
                    <dt>Tamanho</dt><dd><?= $model ? e($model->formatSize($document['size_bytes'] ?? 0)) : '—' ?></dd>
                    <dt>Checksum SHA-256</dt><dd style="word-break:break-all;font-size:12px;"><?= e($document['checksum_sha256'] ?? '—') ?></dd>
                    <dt>Versão</dt><dd>v<?= (int) ($document['version_number'] ?? 1) ?></dd>
                    <?php if (!empty($document['parent_document_id'])): ?>
                        <dt>Documento pai</dt>
                        <dd><a href="<?= e(app_url('/documents/' . (int) $document['parent_document_id'])) ?>" class="link-strong"><?= e($document['parent_title'] ?? ('#' . (int) $document['parent_document_id'])) ?></a></dd>
                    <?php endif; ?>
                </dl>
                <?php if (can('documents.download')): ?>
                    <a href="<?= e(app_url('/documents/' . $did . '/download')) ?>" class="btn btn-sm btn-yellow"><i data-lucide="download"></i> Baixar arquivo</a>
                <?php endif; ?>
            </article>

            <article class="card document-card">
                <h3 class="h3-card"><i data-lucide="calendar-clock"></i> Datas</h3>
                <dl class="meta-list">
                    <dt>Data do documento</dt><dd><?= e($dash($document['document_date'] ?? '')) ?></dd>
                    <dt>Validade</dt><dd class="<?= $expired ? 'overdue' : '' ?>"><?= e($dash($document['valid_until'] ?? '')) ?></dd>
                </dl>
            </article>
        </div>

        <?php if (!empty($document['description'])): ?>
            <article class="card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="align-left"></i> Descrição</h3>
                <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($document['description']) ?></p>
            </article>
        <?php endif; ?>

        <?php if (!empty($document['notes'])): ?>
            <article class="card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="sticky-note"></i> Observações</h3>
                <p style="white-space:pre-line;color:var(--dcx-text-card);"><?= e($document['notes']) ?></p>
            </article>
        <?php endif; ?>

        <div class="notice" style="margin-top:20px;">
            <p class="mb-0"><i data-lucide="info"></i> Links públicos, assinatura digital, contratos e portal externo serão tratados em etapas futuras.</p>
        </div>

        <article class="card meta-audit" style="margin-top:18px;">
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($document['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($document['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($document['updated_by_name'] ?? '')) ?></dd>
                <dt>Atualizado em</dt><dd><?= e($dash($document['updated_at'] ?? '')) ?></dd>
            </dl>
        </article>

        <div class="document-actions actions-row" style="margin-top:22px;">
            <?php if (can('documents.download')): ?>
                <a href="<?= e(app_url('/documents/' . $did . '/download')) ?>" class="btn btn-yellow"><i data-lucide="download"></i> Download</a>
            <?php endif; ?>

            <?php if (can('documents.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/documents/' . $did . '/edit')) ?>" class="btn btn-light"><i data-lucide="pencil"></i> Editar</a>
            <?php endif; ?>

            <?php if (can('documents.version')): ?>
                <a href="<?= e(app_url('/documents/' . $did . '/version')) ?>" class="btn btn-outline"><i data-lucide="git-branch"></i> Nova versão</a>
            <?php endif; ?>

            <?php if (can('documents.edit') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-outline"><i data-lucide="refresh-cw"></i> Mudar status</summary>
                    <form method="post" action="<?= e(app_url('/documents/' . $did . '/status')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div>
                                <label for="status">Novo status</label>
                                <select id="status" name="status" required>
                                    <?php foreach ($statuses as $k => $label): ?>
                                        <option value="<?= e($k) ?>" <?= $st === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grid-full">
                                <label for="notes">Observação (opcional)</label>
                                <textarea id="notes" name="notes" rows="2" placeholder="Motivo ou contexto da mudança"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Atualizar status</button>
                    </form>
                </details>
            <?php endif; ?>

            <?php if (can('documents.archive') && !$isArchived): ?>
                <form method="post" action="<?= e(app_url('/documents/' . $did . '/archive')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" data-confirm="Arquivar este documento? Ele sairá da listagem padrão."><i data-lucide="archive"></i> Arquivar</button>
                </form>
            <?php endif; ?>

            <?php if (can('documents.archive') && $isArchived): ?>
                <form method="post" action="<?= e(app_url('/documents/' . $did . '/restore')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-yellow"><i data-lucide="archive-restore"></i> Restaurar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
