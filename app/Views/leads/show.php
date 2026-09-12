<?php
$lead = $lead ?? []; $statuses = $statuses ?? [];
$simulation = $simulation ?? null;
$id = (int)($lead['id']??0); $st = (string)($lead['status']??'');
$arch = !empty($lead['archived_at']);
$dash = static fn($v)=> ($v===null||$v==='')?'—':(string)$v;
$payload = $lead['integration_payload'] ?? '';
if (is_string($payload) && $payload!=='') { $payload = json_decode($payload,true) ?: $payload; }
$simModel = new \App\Models\SponsorshipSimulation();
$display = is_array($simulation['display_snapshot'] ?? null) ? $simulation['display_snapshot'] : [];
$labels = static function ($value): string {
    if (is_array($value)) {
        $parts = array_filter(array_map(static fn($v) => is_scalar($v) ? (string)$v : '', $value));
        return $parts === [] ? '—' : implode(', ', $parts);
    }
    return ($value === null || $value === '') ? '—' : (string)$value;
};
?>
<section class="section"><div class="container">
<div class="page-head"><div><span class="kicker kicker-dark">Lead #<?= $id ?></span>
<h1 class="h2-section"><?= e($lead['name']??'') ?></h1>
<p class="page-sub"><span class="badge-lead badge-lead-<?= e($st) ?>"><?= e($statuses[$st]??$st) ?></span>
<?php if (($lead['submission_type'] ?? '') === 'SPONSORSHIP_SIMULATION'): ?>
<span class="badge-status">Simulação de Patrocínio</span>
<?php endif; ?>
<?php if ($arch): ?><span class="badge-status badge-status-arquivado">Arquivado</span><?php endif; ?></p></div>
<a href="<?= e(app_url('/leads')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a></div>

<div class="notice" style="margin-bottom:18px;"><p class="mb-0"><i data-lucide="info"></i> Leads devem ser triados antes de virar empresa, contato, oportunidade ou tarefa.</p></div>

<?php if (is_array($simulation) && !empty($simulation['id'])): ?>
<article class="card" style="margin-bottom:18px;">
<h3 class="h3-card"><i data-lucide="compass"></i> Simulação de Patrocínio</h3>
<dl class="meta-list">
<dt>Projeto</dt><dd><?= e($dash($simulation['project_name'] ?? $lead['project_name'] ?? '')) ?></dd>
<dt>PRONAC</dt><dd><?= e($dash($simulation['pronac_number'] ?? $lead['project_pronac'] ?? '')) ?></dd>
<dt>Catálogo</dt><dd><?= e($dash($simulation['catalog_version'] ?? '')) ?></dd>
</dl>

<h4 class="h3-card" style="margin-top:16px;">Sua leitura</h4>
<dl class="meta-list">
<dt>Objetivo</dt><dd><?= e($labels($display['objective_labels'] ?? null)) ?></dd>
<dt>Profundidade</dt><dd><?= e($labels($display['depth_label'] ?? null)) ?></dd>
<dt>Experiências</dt><dd><?= e($labels($display['experience_labels'] ?? null)) ?></dd>
<dt>Comprovação</dt><dd><?= e($labels($display['proof_labels'] ?? null)) ?></dd>
<dt>Investimento</dt><dd><?= e($simModel->investmentDisplayLabel($simulation)) ?></dd>
</dl>

<h4 class="h3-card" style="margin-top:16px;">Recomendação</h4>
<dl class="meta-list">
<dt>Configuração</dt><dd><?= e($simModel->tierLabel($simulation['primary_tier_ref'] ?? null)) ?></dd>
<dt>Território</dt><dd><?= e($dash($simulation['primary_axis_ref'] ?? '')) ?></dd>
<dt>Experiência</dt><dd><?= e($dash($simulation['primary_activation_ref'] ?? '')) ?></dd>
<dt>Aderência</dt><dd><?= e($simModel->fitLabel($simulation['fit_level'] ?? null)) ?></dd>
<dt>Disponibilidade</dt><dd><?= e($simModel->availabilityLabel($simulation['availability_status'] ?? null)) ?></dd>
</dl>

<?php
$alts = $simulation['recommendation_snapshot']['alternatives'] ?? [];
if (is_array($alts) && $alts !== []):
?>
<h4 class="h3-card" style="margin-top:16px;">Outras possibilidades</h4>
<ul>
<?php foreach ($alts as $alt): if (!is_array($alt)) continue; ?>
<li><?= e($simModel->tierLabel($alt['tier_id'] ?? null)) ?>
 · Aderência: <?= e($simModel->fitLabel($alt['fit_level'] ?? null)) ?>
 · <?= e($simModel->availabilityLabel($alt['availability'] ?? 'NOT_CHECKED')) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<p class="page-sub" style="margin-top:12px;">Snapshot histórico do Guide. A negociação comercial ocorre na oportunidade — sem reservar cota automaticamente.</p>
</article>
<?php endif; ?>

<div class="detail-grid">
<article class="card lead-card"><h3 class="h3-card">Dados recebidos</h3>
<dl class="meta-list">
<dt>Nome</dt><dd><?= e($dash($lead['name']??'')) ?></dd>
<dt>Empresa</dt><dd><?= e($dash($lead['company_name']??'')) ?></dd>
<dt>Cargo</dt><dd><?= e($dash($lead['role_title']??'')) ?></dd>
<dt>E-mail</dt><dd><?= e($dash($lead['email']??'')) ?></dd>
<dt>WhatsApp</dt><dd><?= e($dash($lead['whatsapp']??'')) ?></dd>
<dt>Cidade/UF</dt><dd><?= e(trim(($lead['city']??'').'/'.($lead['state']??''),'/')) ?: '—' ?></dd>
<dt>Segmento</dt><dd><?= e($dash($lead['segment']??'')) ?></dd>
<dt>Interesse</dt><dd><?= e($dash($lead['interest']??'')) ?></dd>
<dt>Consentimento</dt><dd><?= !empty($lead['contact_consent'])?'Sim':'Não' ?></dd>
</dl></article>
<article class="card"><h3 class="h3-card">Origem e formulário</h3>
<dl class="meta-list">
<dt>Tipo de envio</dt><dd><?= ($lead['submission_type'] ?? 'GENERIC_LEAD') === 'SPONSORSHIP_SIMULATION' ? 'Simulação de Patrocínio' : 'Lead genérico' ?></dd>
<dt>Projeto</dt><dd><?= e($dash($lead['project_name'] ?? '')) ?><?php if (!empty($lead['project_pronac'])): ?> · PRONAC <?= e($lead['project_pronac']) ?><?php endif; ?></dd>
<dt>Página</dt><dd class="lead-source"><?= e($dash($lead['origin_page']??'')) ?></dd>
<dt>URL</dt><dd><?= e($dash($lead['source_url']??'')) ?></dd>
<dt>Formulário</dt><dd><?= e($dash($lead['form_name']??($lead['form_id']??''))) ?></dd>
<dt>IP</dt><dd><?= e($dash($lead['ip_address']??'')) ?></dd>
</dl></article>
<article class="card"><h3 class="h3-card">Vínculos CRM</h3>
<dl class="meta-list">
<dt>Empresa</dt><dd><?php if(!empty($lead['company_id'])): ?><a href="<?= e(app_url('/companies/'.(int)$lead['company_id'])) ?>"><?= e($lead['linked_company_name']??('#'.$lead['company_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
<dt>Contato</dt><dd><?php if(!empty($lead['contact_id'])): ?><a href="<?= e(app_url('/contacts/'.(int)$lead['contact_id'])) ?>"><?= e($lead['linked_contact_name']??('#'.$lead['contact_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
<dt>Oportunidade</dt><dd><?php if(!empty($lead['opportunity_id'])): ?><a href="<?= e(app_url('/opportunities/'.(int)$lead['opportunity_id'])) ?>"><?= e($lead['linked_opportunity_title']??('#'.$lead['opportunity_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
<dt>Tarefa</dt><dd><?php if(!empty($lead['task_id'])): ?><a href="<?= e(app_url('/tasks/'.(int)$lead['task_id'])) ?>"><?= e($lead['linked_task_title']??('#'.$lead['task_id'])) ?></a><?php else: ?>—<?php endif; ?></dd>
<dt>Convertido em</dt><dd><?= e($dash($lead['converted_at']??'')) ?></dd>
<dt>Convertido por</dt><dd><?= e($dash($lead['converted_by_name']??'')) ?></dd>
</dl></article>
</div>

<?php if (!empty($lead['message'])): ?><article class="card" style="margin-top:18px;"><h3 class="h3-card">Mensagem</h3><p style="white-space:pre-line;"><?= e($lead['message']) ?></p></article><?php endif; ?>

<?php if ($payload): ?>
<details class="card lead-payload" style="margin-top:18px;padding:16px;">
<summary style="cursor:pointer;font-weight:600;">Rastreabilidade técnica — ver payload recebido</summary>
<pre style="font-size:12px;overflow:auto;max-height:240px;margin-top:12px;"><?= e(is_array($payload)?json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE):$payload) ?></pre>
</details>
<?php endif; ?>

<?php if (can('documents.view')): ?>
    <?php
    $blockTitle = 'Documentos do lead';
    $createUrl  = app_url('/leads/' . $id . '/documents/create');
    $allUrl     = app_url('/documents?lead_id=' . $id);
    $emptyText  = 'Nenhum documento vinculado a este lead ainda.';
    require dirname(__DIR__) . '/documents/_summary_block.php';
    ?>
<?php endif; ?>

<div class="actions-row" style="margin-top:22px;flex-wrap:wrap;gap:10px;">
<?php if (can('leads.edit') && !$arch): ?><a href="<?= e(app_url('/leads/'.$id.'/edit')) ?>" class="btn btn-light"><i data-lucide="pencil"></i> Editar</a><?php endif; ?>
<?php if (can('leads.convert') && !$arch): ?><a href="<?= e(app_url('/leads/'.$id.'/convert')) ?>" class="btn btn-yellow"><i data-lucide="git-merge"></i> Converter</a><?php endif; ?>
<?php if (can('leads.edit') && !$arch): ?>
<form method="post" action="<?= e(app_url('/leads/'.$id.'/mark-duplicate')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-light">Duplicado</button></form>
<form method="post" action="<?= e(app_url('/leads/'.$id.'/discard')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-light">Descartar</button></form>
<?php endif; ?>
<?php if (can('leads.archive') && !$arch): ?><form method="post" action="<?= e(app_url('/leads/'.$id.'/archive')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-light">Arquivar</button></form><?php endif; ?>
<?php if (can('leads.edit') && $arch): ?><form method="post" action="<?= e(app_url('/leads/'.$id.'/restore')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-yellow">Restaurar</button></form><?php endif; ?>
</div>
</div></section>
