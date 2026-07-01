<?php
$entry = $entry ?? [];
$model = $model ?? null;
$entryTypes = $entryTypes ?? [];
$fundingMechanisms = $fundingMechanisms ?? [];
$paymentMethods = $paymentMethods ?? [];
$statuses = $statuses ?? [];
$fiscalStatuses = $fiscalStatuses ?? [];
$documents = $documents ?? [];
$documentSummary = $documentSummary ?? [];
$documentModel = $documentModel ?? null;
$commissions = $commissions ?? [];

$eid = (int) ($entry['id'] ?? 0);
$sid = (int) ($entry['sponsor_id'] ?? 0);
$isArchived = !empty($entry['archived_at']);
$st = (string) ($entry['status'] ?? '');
$fiscal = (string) ($entry['fiscal_document_status'] ?? '');
$overdue = $model && $model->isOverdue($entry);
$dash = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;
$installmentLabel = '';
if (!empty($entry['installment_number']) || !empty($entry['installments_total'])) {
    $installmentLabel = (string) ($entry['installment_number'] ?? '?') . '/' . (string) ($entry['installments_total'] ?? '?');
}
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Financeiro</span>
                <h1 class="h2-section"><?= e($entry['title'] ?? '') ?></h1>
                <p class="page-sub">
                    <?php if (!empty($entry['entry_number'])): ?><span class="pill"><?= e($entry['entry_number']) ?></span><?php endif; ?>
                    <?php if ($installmentLabel !== ''): ?><span class="pill">Parcela <?= e($installmentLabel) ?></span><?php endif; ?>
                    <span class="financial-status badge-fin-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span>
                    <span class="financial-fiscal-status badge-fiscal-<?= e($fiscal) ?>"><?= e($fiscalStatuses[$fiscal] ?? $fiscal) ?></span>
                    <span class="financial-type"><?= e($entryTypes[$entry['entry_type'] ?? ''] ?? '') ?></span>
                    <?php if ($overdue): ?><span class="financial-alert financial-overdue">Em atraso</span><?php endif; ?>
                    <?php if ($isArchived): ?><span class="badge-status badge-status-arquivado">Arquivado</span><?php endif; ?>
                </p>
            </div>
            <a href="<?= e(app_url('/financials')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="notice notice-info financial-alert" style="margin-bottom:18px;">
            <p class="mb-0"><i data-lucide="info"></i> Emissão de nota fiscal, boletos, integrações bancárias, cobrança automática e relatórios avançados serão tratados em etapas futuras.</p>
        </div>

        <div class="detail-grid">
            <article class="card financial-card">
                <h3 class="h3-card"><i data-lucide="link"></i> Vínculos</h3>
                <dl class="meta-list">
                    <dt>Patrocinador</dt><dd><a href="<?= e(app_url('/sponsors/' . $sid)) ?>" class="link-strong"><?= e($entry['sponsor_name'] ?? '—') ?></a></dd>
                    <dt>Contrato</dt><dd><?php if (!empty($entry['contract_id'])): ?><a href="<?= e(app_url('/contracts/' . (int) $entry['contract_id'])) ?>" class="link-strong"><?= e($entry['contract_title'] ?? $entry['contract_number'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Empresa</dt><dd><?php if (!empty($entry['company_id'])): ?><a href="<?= e(app_url('/companies/' . (int) $entry['company_id'])) ?>" class="link-strong"><?= e($entry['company_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Contato</dt><dd><?php if (!empty($entry['contact_id'])): ?><a href="<?= e(app_url('/contacts/' . (int) $entry['contact_id'])) ?>" class="link-strong"><?= e($entry['contact_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Oportunidade</dt><dd><?php if (!empty($entry['opportunity_id'])): ?><a href="<?= e(app_url('/opportunities/' . (int) $entry['opportunity_id'])) ?>" class="link-strong"><?= e($entry['opportunity_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Proposta</dt><dd><?php if (!empty($entry['proposal_id'])): ?><a href="<?= e(app_url('/proposals/' . (int) $entry['proposal_id'])) ?>" class="link-strong"><?= e($entry['proposal_title'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Cota</dt><dd><?php if (!empty($entry['quota_id'])): ?><a href="<?= e(app_url('/quotas/' . (int) $entry['quota_id'])) ?>" class="link-strong"><?= e($entry['quota_name'] ?? '') ?></a><?php else: ?>—<?php endif; ?></dd>
                </dl>
            </article>

            <article class="card financial-card">
                <h3 class="h3-card"><i data-lucide="banknote"></i> Valores e pagamento</h3>
                <dl class="meta-list">
                    <dt>Mecanismo</dt><dd><?= e($fundingMechanisms[$entry['funding_mechanism'] ?? ''] ?? $dash($entry['funding_mechanism'] ?? '')) ?></dd>
                    <dt>Forma de pagamento</dt><dd><?= e($paymentMethods[$entry['payment_method'] ?? ''] ?? $dash($entry['payment_method'] ?? '')) ?></dd>
                    <dt>Valor previsto</dt><dd class="financial-value money-value"><?= $entry['planned_amount'] !== null ? e(money_br($entry['planned_amount'])) : '—' ?></dd>
                    <dt>Valor recebido</dt><dd class="financial-received money-value"><?= $entry['received_amount'] !== null ? e(money_br($entry['received_amount'])) : '—' ?></dd>
                    <dt>Saldo</dt><dd class="financial-balance money-value"><?= $entry['remaining_amount'] !== null ? e(money_br($entry['remaining_amount'])) : '—' ?></dd>
                    <dt>Responsável</dt><dd><?= e($dash($entry['responsible_name'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card financial-card">
                <h3 class="h3-card"><i data-lucide="calendar"></i> Datas e marcos</h3>
                <dl class="meta-list">
                    <dt>Vencimento</dt><dd class="<?= $overdue ? 'overdue' : '' ?>"><?= e($dash($entry['due_date'] ?? '')) ?></dd>
                    <dt>Previsão de recebimento</dt><dd><?= e($dash($entry['expected_payment_date'] ?? '')) ?></dd>
                    <dt>Recebido em</dt><dd><?= e($dash($entry['received_at'] ?? '')) ?></dd>
                    <dt>Conciliado em</dt><dd><?= e($dash($entry['reconciled_at'] ?? '')) ?></dd>
                    <dt>Cancelado em</dt><dd><?= e($dash($entry['cancelled_at'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card financial-card">
                <h3 class="h3-card"><i data-lucide="user"></i> Pagador e referências</h3>
                <dl class="meta-list">
                    <dt>Pagador</dt><dd><?= e($dash($entry['payer_name'] ?? '')) ?></dd>
                    <dt>Documento</dt><dd><?= e($dash($entry['payer_document'] ?? '')) ?></dd>
                    <dt>Referência bancária</dt><dd><?= e($dash($entry['bank_reference'] ?? '')) ?></dd>
                    <dt>Referência da transação</dt><dd><?= e($dash($entry['transaction_reference'] ?? '')) ?></dd>
                    <dt>Confirmado por</dt><dd><?= e($dash($entry['confirmed_by_name'] ?? '')) ?></dd>
                    <dt>Conciliado por</dt><dd><?= e($dash($entry['reconciled_by_name'] ?? '')) ?></dd>
                    <dt>Cancelado por</dt><dd><?= e($dash($entry['cancelled_by_name'] ?? '')) ?></dd>
                </dl>
            </article>

            <article class="card financial-card financial-documents">
                <h3 class="h3-card"><i data-lucide="folder"></i> Documentos vinculados</h3>
                <dl class="meta-list">
                    <dt>Comprovante</dt><dd><?php if (!empty($entry['proof_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $entry['proof_document_id'])) ?>" class="link-strong"><?= e($entry['proof_document_title'] ?? ('#' . (int) $entry['proof_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Recibo</dt><dd><?php if (!empty($entry['receipt_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $entry['receipt_document_id'])) ?>" class="link-strong"><?= e($entry['receipt_document_title'] ?? ('#' . (int) $entry['receipt_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                    <dt>Documento fiscal</dt><dd><?php if (!empty($entry['fiscal_document_id'])): ?><a href="<?= e(app_url('/documents/' . (int) $entry['fiscal_document_id'])) ?>" class="link-strong"><?= e($entry['fiscal_document_title'] ?? ('#' . (int) $entry['fiscal_document_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
                </dl>
            </article>
        </div>

        <?php if (!empty($entry['proof_notes']) || !empty($entry['receipt_notes']) || !empty($entry['fiscal_notes']) || !empty($entry['reconciliation_notes']) || !empty($entry['notes']) || (!empty($entry['internal_notes']) && can('financials.edit'))): ?>
            <article class="card financial-card" style="margin-top:18px;">
                <h3 class="h3-card"><i data-lucide="message-square"></i> Observações</h3>
                <?php if (!empty($entry['proof_notes'])): ?><p><strong>Comprovante:</strong><br><?= nl2br(e($entry['proof_notes'])) ?></p><?php endif; ?>
                <?php if (!empty($entry['receipt_notes'])): ?><p><strong>Recibo:</strong><br><?= nl2br(e($entry['receipt_notes'])) ?></p><?php endif; ?>
                <?php if (!empty($entry['fiscal_notes'])): ?><p><strong>Fiscal:</strong><br><?= nl2br(e($entry['fiscal_notes'])) ?></p><?php endif; ?>
                <?php if (!empty($entry['reconciliation_notes'])): ?><p><strong>Conciliação:</strong><br><?= nl2br(e($entry['reconciliation_notes'])) ?></p><?php endif; ?>
                <?php if (!empty($entry['notes'])): ?><p><strong>Geral:</strong><br><?= nl2br(e($entry['notes'])) ?></p><?php endif; ?>
                <?php if (!empty($entry['internal_notes']) && can('financials.edit')): ?><p><strong>Internas:</strong><br><?= nl2br(e($entry['internal_notes'])) ?></p><?php endif; ?>
            </article>
        <?php endif; ?>

        <?php if (can('documents.view')): ?>
            <?php
            $blockTitle = 'Documentos financeiros';
            $createUrl = can('documents.create') ? app_url('/financials/' . $eid . '/documents/create') : '';
            $allUrl = app_url('/documents') . '?financial_entry_id=' . $eid;
            $emptyText = 'Nenhum documento vinculado a este lançamento financeiro.';
            require __DIR__ . '/../documents/_summary_block.php';
            ?>
        <?php endif; ?>

        <?php if (can('commissions.view')): ?>
            <article class="card financial-card" style="margin-top:18px;">
                <div class="page-head" style="margin-bottom:12px;">
                    <div>
                        <h3 class="h3-card"><i data-lucide="badge-dollar-sign"></i> Comissoes do captador</h3>
                        <p class="page-sub">Motor 20A: calculo proporcional, pendente de aprovacao e sem pagamento.</p>
                    </div>
                    <?php if (can('commissions.calculate') && !$isArchived): ?>
                        <form method="post" action="<?= e(app_url('/financials/' . $eid . '/commissions/recalculate')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline"><i data-lucide="refresh-cw"></i> Recalcular</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Captador</th><th>Recebido</th><th>Comissao</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($commissions as $commission): ?>
                                <tr>
                                    <td><?= e($commission['collector_name'] ?? '') ?><br><small><?= e($commission['collector_code'] ?? '') ?></small></td>
                                    <td><?= e(money_br($commission['financial_received_amount'] ?? 0)) ?></td>
                                    <td><strong><?= e(money_br($commission['capped_commission_amount'] ?? 0)) ?></strong></td>
                                    <td><span class="pill"><?= e($commission['calculation_status'] ?? '') ?></span> <span class="pill"><?= e($commission['approval_status'] ?? '') ?></span></td>
                                    <td><a class="btn btn-sm btn-outline" href="<?= e(app_url('/commissions/' . (int) $commission['id'])) ?>">Abrir</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($commissions === []): ?>
                                <tr><td colspan="5" class="text-muted">Nenhuma comissao calculada para este recebimento.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php endif; ?>

        <div class="financial-actions actions-row" style="margin-top:22px;">
            <?php if (can('financials.edit') && !$isArchived): ?>
                <a href="<?= e(app_url('/financials/' . $eid . '/edit')) ?>" class="btn btn-yellow"><i data-lucide="pencil"></i> Editar</a>
            <?php endif; ?>
            <?php if (can('financials.confirm') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-light"><i data-lucide="check-circle"></i> Confirmar recebimento</summary>
                    <form method="post" action="<?= e(app_url('/financials/' . $eid . '/confirm')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div>
                                <label>Valor recebido (R$)</label>
                                <input type="text" name="received_amount" class="financial-received" value="<?= e((string) ($entry['planned_amount'] ?? '')) ?>" placeholder="Deixe vazio para valor previsto">
                            </div>
                            <div>
                                <label>Recebido em</label>
                                <input type="datetime-local" name="received_at" value="<?= e(str_replace(' ', 'T', substr((string) (date('Y-m-d H:i')), 0, 16))) ?>">
                            </div>
                            <div>
                                <label>Forma de pagamento</label>
                                <select name="payment_method">
                                    <?php foreach ($paymentMethods as $k => $l): ?>
                                        <option value="<?= e($k) ?>" <?= ($entry['payment_method'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-grid-full">
                                <label>Referência da transação</label>
                                <input type="text" name="transaction_reference" value="<?= e((string) ($entry['transaction_reference'] ?? '')) ?>">
                            </div>
                            <div class="form-grid-full"><label>Observações</label><textarea name="notes" rows="2"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Confirmar recebimento</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('financials.status') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-outline"><i data-lucide="refresh-cw"></i> Mudar status</summary>
                    <form method="post" action="<?= e(app_url('/financials/' . $eid . '/status')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div>
                                <label>Status financeiro</label>
                                <select name="status">
                                    <?php foreach ($statuses as $k => $l): ?>
                                        <option value="<?= e($k) ?>" <?= $st === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Status fiscal</label>
                                <select name="fiscal_document_status">
                                    <?php foreach ($fiscalStatuses as $k => $l): ?>
                                        <option value="<?= e($k) ?>" <?= $fiscal === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($fiscal === 'anexado' && empty($entry['fiscal_document_id'])): ?>
                                <div class="form-grid-full">
                                    <p class="financial-alert notice notice-warn mb-0">Status fiscal “anexado” sem documento fiscal vinculado.</p>
                                </div>
                            <?php endif; ?>
                            <div class="form-grid-full"><label>Observação</label><textarea name="notes" rows="2"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Atualizar</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('financials.reconcile') && !$isArchived): ?>
                <details class="status-quick-form">
                    <summary class="btn btn-sm btn-light"><i data-lucide="landmark"></i> Conciliar</summary>
                    <form method="post" action="<?= e(app_url('/financials/' . $eid . '/reconcile')) ?>" class="form-box" style="margin-top:12px;">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                            <div>
                                <label>Conciliado em</label>
                                <input type="datetime-local" name="reconciled_at" value="<?= e(str_replace(' ', 'T', substr((string) (date('Y-m-d H:i')), 0, 16))) ?>">
                            </div>
                            <div>
                                <label>Referência bancária</label>
                                <input type="text" name="bank_reference" value="<?= e((string) ($entry['bank_reference'] ?? '')) ?>">
                            </div>
                            <div class="form-grid-full">
                                <label>Referência da transação</label>
                                <input type="text" name="transaction_reference" value="<?= e((string) ($entry['transaction_reference'] ?? '')) ?>">
                            </div>
                            <div class="form-grid-full"><label>Observações de conciliação</label><textarea name="reconciliation_notes" rows="2"></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-yellow">Conciliar</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php if (can('documents.create')): ?>
                <a href="<?= e(app_url('/financials/' . $eid . '/documents/create')) ?>" class="btn btn-outline"><i data-lucide="folder-plus"></i> Novo documento</a>
            <?php endif; ?>
            <?php if (can('financials.archive')): ?>
                <?php if (!$isArchived): ?>
                    <form method="post" action="<?= e(app_url('/financials/' . $eid . '/archive')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger" data-confirm="Arquivar este lançamento financeiro?"><i data-lucide="archive"></i> Arquivar</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(app_url('/financials/' . $eid . '/restore')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-light"><i data-lucide="rotate-ccw"></i> Restaurar</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <article class="card financial-card" style="margin-top:18px;">
            <h3 class="h3-card"><i data-lucide="history"></i> Auditoria</h3>
            <dl class="meta-list meta-list-inline">
                <dt>Criado por</dt><dd><?= e($dash($entry['created_by_name'] ?? '')) ?></dd>
                <dt>Criado em</dt><dd><?= e($dash($entry['created_at'] ?? '')) ?></dd>
                <dt>Atualizado por</dt><dd><?= e($dash($entry['updated_by_name'] ?? '')) ?></dd>
                <dt>Atualizado em</dt><dd><?= e($dash($entry['updated_at'] ?? '')) ?></dd>
            </dl>
        </article>
    </div>
</section>
