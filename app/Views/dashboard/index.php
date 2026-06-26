<?php
/**
 * Painel administrativo protegido — tela institucional inicial.
 *
 * Variaveis esperadas:
 * - $user        (array{id,name,email}|null)
 * - $roleNames   (array<int,string>)
 * - $adminActive (array<int,string>)
 */
$user           = $user ?? null;
$userName       = $user['name'] ?? 'Usuário';
$roleNames      = $roleNames ?? [];
$adminActive    = $adminActive ?? [];
$companiesCount     = $companiesCount ?? null;
$contactsCount      = $contactsCount ?? null;
$opportunitiesOpen  = $opportunitiesOpen ?? null;
$opportunitiesValue = $opportunitiesValue ?? null;
$quotasCount        = $quotasCount ?? null;
$quotasAvailable    = $quotasAvailable ?? null;
$tasksOpen          = $tasksOpen ?? null;
$tasksOverdue       = $tasksOverdue ?? null;
$tasksToday         = $tasksToday ?? null;
$tasksMine          = $tasksMine ?? null;
$leadsNew           = $leadsNew ?? null;
$leadsTriagem       = $leadsTriagem ?? null;
$leadsConverted     = $leadsConverted ?? null;
$leadsDiscarded     = $leadsDiscarded ?? null;
?>

<section class="section section-dark">
    <div class="container">
        <div class="stack-md">
            <span class="kicker">Painel</span>
            <h1 class="h2-section" style="color:#fff;">Painel Administrativo</h1>
            <p class="lead" style="max-width:680px;color:rgba(255,255,255,.88);">
                Ambiente autenticado instalado. Os módulos de CRM serão liberados
                nas próximas etapas controladas.
            </p>
            <p style="margin:0;">
                <span class="badge-dcx badge-ok">
                    <i data-lucide="user-check"></i> <?= e($userName) ?>
                </span>
            </p>
        </div>
    </div>
</section>

<section class="section section-soft">
    <div class="container stack-md">
        <div class="grid">
            <?php if ($companiesCount !== null): ?>
                <article class="card card-accent">
                    <span class="card-icon"><i data-lucide="building-2"></i></span>
                    <h3>Empresas cadastradas</h3>
                    <p style="font-size:32px;font-weight:900;color:var(--dcx-black);margin-bottom:6px;"><?= (int) $companiesCount ?></p>
                    <p><a href="<?= e(app_url('/companies')) ?>" class="link-strong"><i data-lucide="arrow-right"></i> Gerenciar empresas</a></p>
                </article>
            <?php endif; ?>

            <?php if ($contactsCount !== null): ?>
                <article class="card card-accent">
                    <span class="card-icon"><i data-lucide="contact"></i></span>
                    <h3>Contatos cadastrados</h3>
                    <p style="font-size:32px;font-weight:900;color:var(--dcx-black);margin-bottom:6px;"><?= (int) $contactsCount ?></p>
                    <p><a href="<?= e(app_url('/contacts')) ?>" class="link-strong"><i data-lucide="arrow-right"></i> Gerenciar contatos</a></p>
                </article>
            <?php endif; ?>

            <?php if ($opportunitiesOpen !== null): ?>
                <article class="card card-accent">
                    <span class="card-icon"><i data-lucide="handshake"></i></span>
                    <h3>Oportunidades abertas</h3>
                    <p style="font-size:32px;font-weight:900;color:var(--dcx-black);margin-bottom:6px;"><?= (int) $opportunitiesOpen ?></p>
                    <p class="money-value" style="margin-bottom:6px;">Em negociação: <?= e(money_br($opportunitiesValue, 'R$ 0,00')) ?></p>
                    <p>
                        <a href="<?= e(app_url('/opportunities')) ?>" class="link-strong"><i data-lucide="arrow-right"></i> Gerenciar oportunidades</a>
                        &nbsp;·&nbsp;
                        <a href="<?= e(app_url('/opportunities/pipeline')) ?>" class="link-strong"><i data-lucide="kanban-square"></i> Ver pipeline</a>
                    </p>
                </article>
            <?php endif; ?>

            <?php if ($quotasCount !== null): ?>
                <article class="card card-accent">
                    <span class="card-icon"><i data-lucide="ticket"></i></span>
                    <h3>Cotas de patrocínio</h3>
                    <p style="font-size:32px;font-weight:900;color:var(--dcx-black);margin-bottom:6px;"><?= (int) $quotasCount ?></p>
                    <p style="margin-bottom:6px;"><span class="pill"><?= (int) $quotasAvailable ?> disponível(is)</span></p>
                    <p>
                        <a href="<?= e(app_url('/quotas')) ?>" class="link-strong"><i data-lucide="arrow-right"></i> Gerenciar cotas</a>
                    </p>
                </article>
            <?php endif; ?>

            <?php if ($tasksOpen !== null): ?>
                <article class="card card-accent">
                    <span class="card-icon"><i data-lucide="list-checks"></i></span>
                    <h3>Tarefas e follow-ups</h3>
                    <p style="font-size:32px;font-weight:900;color:var(--dcx-black);margin-bottom:6px;"><?= (int) $tasksOpen ?> <span style="font-size:14px;font-weight:600;">abertas</span></p>
                    <p style="margin-bottom:6px;">
                        <span class="pill <?= (int) $tasksOverdue > 0 ? 'pill-danger' : '' ?>"><?= (int) $tasksOverdue ?> vencida(s)</span>
                        <span class="pill"><?= (int) $tasksToday ?> hoje</span>
                        <span class="pill"><?= (int) $tasksMine ?> minhas</span>
                    </p>
                    <p>
                        <a href="<?= e(app_url('/tasks')) ?>" class="link-strong"><i data-lucide="arrow-right"></i> Gerenciar tarefas</a>
                        &nbsp;·&nbsp;
                        <a href="<?= e(app_url('/tasks?mine=1')) ?>" class="link-strong"><i data-lucide="user"></i> Minhas tarefas</a>
                    </p>
                </article>
            <?php endif; ?>

            <?php if ($leadsNew !== null): ?>
                <article class="card card-accent">
                    <span class="card-icon"><i data-lucide="inbox"></i></span>
                    <h3>Leads do site</h3>
                    <p style="font-size:32px;font-weight:900;color:var(--dcx-black);margin-bottom:6px;"><?= (int) $leadsNew ?> <span style="font-size:14px;font-weight:600;">novos</span></p>
                    <p style="margin-bottom:6px;">
                        <span class="pill"><?= (int) $leadsTriagem ?> em triagem</span>
                        <span class="pill"><?= (int) $leadsConverted ?> convertidos</span>
                        <span class="pill"><?= (int) $leadsDiscarded ?> descartados</span>
                    </p>
                    <p>
                        <a href="<?= e(app_url('/leads')) ?>" class="link-strong"><i data-lucide="arrow-right"></i> Gerenciar leads</a>
                        &nbsp;·&nbsp;
                        <a href="<?= e(app_url('/leads?status=novo')) ?>" class="link-strong"><i data-lucide="sparkles"></i> Ver leads novos</a>
                    </p>
                </article>
            <?php endif; ?>

            <article class="card card-accent">
                <span class="card-icon"><i data-lucide="id-card"></i></span>
                <h3>Usuário autenticado</h3>
                <p><?= e($user['email'] ?? '') ?></p>
            </article>

            <article class="card card-accent">
                <span class="card-icon"><i data-lucide="shield"></i></span>
                <h3>Perfis vinculados</h3>
                <p>
                    <?php if ($roleNames === []): ?>
                        Nenhum perfil vinculado.
                    <?php else: ?>
                        <?php foreach ($roleNames as $rn): ?>
                            <span class="pill"><?= e($rn) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </p>
            </article>

            <article class="card card-accent">
                <span class="card-icon"><i data-lucide="key-round"></i></span>
                <h3>Permissões administrativas</h3>
                <p>
                    <?php if ($adminActive === []): ?>
                        Sem permissões administrativas ativas.
                    <?php else: ?>
                        <?php foreach ($adminActive as $perm): ?>
                            <span class="pill"><?= e($perm) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </p>
            </article>

            <article class="card card-accent">
                <span class="card-icon"><i data-lucide="arrow-right-circle"></i></span>
                <h3>Próxima etapa</h3>
                <p>Empresas, Contatos, Oportunidades, Cotas, Tarefas e Leads ativos. A próxima etapa abre <strong>Propostas</strong>.</p>
            </article>
        </div>

        <div class="notice">
            <h3 class="h3-card flex items-center gap-12"><i data-lucide="info"></i> Módulos ativos: Empresas, Contatos, Oportunidades, Cotas, Tarefas e Leads</h3>
            <p>
                Além do controle de acesso, os módulos comerciais e o recebimento de leads do site
                WordPress já estão disponíveis. Propostas, documentos e relatórios ainda não foram criados.
            </p>
        </div>
    </div>
</section>
