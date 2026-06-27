<?php
/**
 * Card de rastreabilidade de captador (Etapa 18C — Fase 2).
 * Espera $collectorTrace (linha de collector_deals com nome do captador) ou null.
 * Mostra o captador vinculado a uma entidade do funil, quando houver.
 */
$collectorTrace = $collectorTrace ?? null;
if ($collectorTrace === null) {
    return;
}
$collectorId = (int) ($collectorTrace['collector_id'] ?? 0);
$statusLabels = [
    'lead_indicado'        => 'Lead indicado',
    'empresa_em_analise'   => 'Empresa em análise',
    'abordagem_autorizada' => 'Abordagem autorizada',
    'oportunidade_criada'  => 'Oportunidade criada',
    'proposta_enviada'     => 'Proposta enviada',
    'negociacao'           => 'Negociação',
    'fechado'              => 'Fechado',
    'perdido'              => 'Perdido',
    'cancelado'            => 'Cancelado',
];
$attrLabels = ['direta' => 'Direta', 'indicacao' => 'Indicação', 'compartilhada' => 'Compartilhada'];
$ds = (string) ($collectorTrace['deal_status'] ?? '');
$at = (string) ($collectorTrace['attribution_type'] ?? '');
?>
<div class="card" style="margin-top:18px;border-left:4px solid #e7b500;">
    <h3 class="h3-card">Origem da captação</h3>
    <dl class="detail-list">
        <dt>Captador</dt>
        <dd>
            <?php if (can('collectors.view') && $collectorId > 0): ?>
                <a href="<?= e(app_url('/collectors/' . $collectorId)) ?>"><?= e($collectorTrace['collector_name'] ?? 'Captador') ?></a>
            <?php else: ?>
                <?= e($collectorTrace['collector_name'] ?? 'Captador') ?>
            <?php endif; ?>
            <?php if (($collectorTrace['collector_code'] ?? '') !== ''): ?> · <?= e($collectorTrace['collector_code']) ?><?php endif; ?>
        </dd>
        <dt>Status da captação</dt><dd><?= e($statusLabels[$ds] ?? $ds ?: '—') ?></dd>
        <dt>Tipo de atribuição</dt><dd><?= e($attrLabels[$at] ?? $at ?: '—') ?></dd>
        <?php if (($collectorTrace['source'] ?? '') !== ''): ?><dt>Origem</dt><dd><?= e($collectorTrace['source']) ?></dd><?php endif; ?>
    </dl>
</div>
