<?php
$lead = $lead ?? []; $statuses = $statuses ?? [];
$id = (int)($lead['id']??0); $st = (string)($lead['status']??'');
$arch = !empty($lead['archived_at']);
$dash = static fn($v)=> ($v===null||$v==='')?'—':(string)$v;
$payload = $lead['integration_payload'] ?? '';
if (is_string($payload) && $payload!=='') { $payload = json_decode($payload,true) ?: $payload; }
?>
<section class="section"><div class="container">
<div class="page-head"><div><span class="kicker kicker-dark">Lead #<?= $id ?></span>
<h1 class="h2-section"><?= e($lead['name']??'') ?></h1>
<p class="page-sub"><span class="badge-lead badge-lead-<?= e($st) ?>"><?= e($statuses[$st]??$st) ?></span>
<?php if ($arch): ?><span class="badge-status badge-status-arquivado">Arquivado</span><?php endif; ?></p></div>
<a href="<?= e(app_url('/leads')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a></div>

<div class="notice" style="margin-bottom:18px;"><p class="mb-0"><i data-lucide="info"></i> Leads devem ser triados antes de virar empresa, contato, oportunidade ou tarefa.</p></div>

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
<dt>Página</dt><dd class="lead-source"><?= e($dash($lead['origin_page']??'')) ?></dd>
<dt>URL</dt><dd><?= e($dash($lead['source_url']??'')) ?></dd>
<dt>Formulário</dt><dd><?= e($dash($lead['form_name']??($lead['form_id']??''))) ?></dd>
<dt>IP</dt><dd><?= e($dash($lead['ip_address']??'')) ?></dd>
<dt>User agent</dt><dd style="word-break:break-all;font-size:12px;"><?= e($dash($lead['user_agent']??'')) ?></dd>
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

<?php if ($payload): ?><article class="card lead-payload" style="margin-top:18px;"><h3 class="h3-card">Payload técnico</h3>
<pre style="font-size:12px;overflow:auto;max-height:240px;"><?= e(is_array($payload)?json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE):$payload) ?></pre></article><?php endif; ?>

<div class="actions-row" style="margin-top:22px;flex-wrap:wrap;gap:10px;">
<?php if (can('leads.edit') && !$arch): ?><a href="<?= e(app_url('/leads/'.$id.'/edit')) ?>" class="btn btn-light"><i data-lucide="pencil"></i> Editar</a><?php endif; ?>
<?php if (can('leads.convert') && !$arch): ?><a href="<?= e(app_url('/leads/'.$id.'/convert')) ?>" class="btn btn-yellow"><i data-lucide="git-merge"></i> Converter</a><?php endif; ?>
<?php if (can('leads.edit') && !$arch): ?>
<form method="post" action="<?= e(app_url('/leads/'.$id.'/mark-duplicate')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-light">Duplicado</button></form>
<form method="post" action="<?= e(app_url('/leads/'.$id.'/discard')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-light">Descartar</button></form>
<?php endif; ?>
<?php if (can('leads.archive') && !$arch): ?><form method="post" action="<?= e(app_url('/leads/'.$id.'/archive')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-danger">Arquivar</button></form><?php endif; ?>
<?php if (can('leads.edit') && $arch): ?><form method="post" action="<?= e(app_url('/leads/'.$id.'/restore')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-yellow">Restaurar</button></form><?php endif; ?>
</div>
</div></section>
