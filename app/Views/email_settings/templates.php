<?php
$items = $items ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
?>
<style>
    .email-template-toolbar {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        margin:20px 0 16px;
        padding:14px 16px;
        border:1px solid #e5e7eb;
        border-radius:14px;
        background:#fff;
    }
    .email-template-counts {
        display:flex;
        align-items:center;
        flex-wrap:wrap;
        gap:8px;
    }
    .email-template-list {
        display:grid;
        gap:16px;
    }
    .email-template-card {
        border:1px solid #e4e7ec;
        border-radius:16px;
        background:#fff;
        overflow:hidden;
        box-shadow:0 8px 24px rgba(17, 24, 39, .06);
    }
    .email-template-card-head {
        display:grid;
        grid-template-columns:minmax(0, 1fr) auto;
        gap:16px;
        align-items:start;
        padding:18px 20px;
        border-top:5px solid #f4c400;
        border-bottom:1px solid #eef0f4;
    }
    .email-template-title {
        margin:8px 0 6px;
        font-size:22px;
        line-height:1.2;
        font-weight:900;
        color:#111;
    }
    .email-template-subject {
        display:flex;
        align-items:flex-start;
        gap:8px;
        margin-top:8px;
        color:#3f4856;
        font-size:14px;
        line-height:1.45;
    }
    .email-template-subject i {
        width:16px;
        height:16px;
        margin-top:2px;
        flex:0 0 auto;
    }
    .email-template-status {
        display:flex;
        justify-content:flex-end;
        gap:8px;
        flex-wrap:wrap;
    }
    .email-template-preview {
        padding:0;
    }
    .email-template-preview summary {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        cursor:pointer;
        padding:14px 20px;
        list-style:none;
        font-weight:800;
        color:#111;
        background:#fafafa;
        border-bottom:1px solid #eef0f4;
    }
    .email-template-preview summary::-webkit-details-marker {
        display:none;
    }
    .email-template-preview summary span {
        display:flex;
        align-items:center;
        gap:8px;
    }
    .email-template-preview summary i {
        width:18px;
        height:18px;
    }
    .email-template-preview[open] summary .preview-chevron {
        transform:rotate(180deg);
    }
    .email-template-preview-body {
        display:grid;
        grid-template-columns:minmax(280px, 1fr) minmax(360px, 1.45fr);
        gap:18px;
        padding:18px 20px 20px;
        background:#f5f7fa;
    }
    .email-template-copy {
        display:grid;
        align-content:start;
        gap:12px;
    }
    .email-template-copy-box {
        border:1px solid #e4e7ec;
        border-radius:12px;
        background:#fff;
        padding:14px 16px;
    }
    .email-template-copy-box strong {
        display:block;
        margin-bottom:8px;
        color:#667085;
        font-size:11px;
        line-height:1.2;
        text-transform:uppercase;
        letter-spacing:.09em;
    }
    .email-template-copy-box code,
    .email-template-copy-box pre {
        margin:0;
        color:#202938;
        font:13px/1.55 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        white-space:pre-wrap;
        word-break:break-word;
    }
    .email-template-frame-wrap {
        border:1px solid #d8dde6;
        border-radius:14px;
        overflow:hidden;
        background:#eef1f6;
    }
    .email-template-frame-head {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        padding:11px 14px;
        background:#111;
        color:#fff;
        font-size:13px;
        font-weight:800;
    }
    .email-template-frame-head span {
        display:flex;
        align-items:center;
        gap:8px;
    }
    .email-template-frame-head i {
        width:16px;
        height:16px;
        color:#f4c400;
    }
    .email-template-frame {
        display:block;
        width:100%;
        min-height:760px;
        border:0;
        background:#eef1f6;
    }
    @media (max-width: 980px) {
        .email-template-card-head,
        .email-template-preview-body {
            grid-template-columns:1fr;
        }
        .email-template-status {
            justify-content:flex-start;
        }
        .email-template-toolbar {
            align-items:flex-start;
            flex-direction:column;
        }
        .email-template-frame {
            min-height:680px;
        }
    }
</style>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">E-mail transacional</span>
                <h1 class="h2-section">Templates</h1>
                <p class="page-sub">Preview visual dos modelos usados em cada gatilho da trilha de captadores.</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="<?= e(app_url('/settings/email/logs')) ?>" class="btn btn-sm btn-outline"><i data-lucide="list"></i> Logs</a>
                <a href="<?= e(app_url('/settings/email')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Configuracao</a>
            </div>
        </div>

        <div class="email-template-toolbar">
            <div>
                <strong>Modelos transacionais</strong>
                <p class="page-sub" style="margin:4px 0 0;">Os previews usam dados ficticios para preencher variaveis como nome, candidatura, links e documentos.</p>
            </div>
            <div class="email-template-counts">
                <span class="pill"><?= $total ?> template(s)</span>
                <span class="pill">Pagina <?= $page ?> de <?= $pages ?></span>
            </div>
        </div>

        <div class="email-template-list">
            <?php foreach ($items as $index => $item): ?>
                <?php
                $eventKey = (string) ($item['event_key'] ?? '');
                $name = (string) ($item['name'] ?? '');
                $subject = (string) ($item['preview_subject'] ?? ($item['subject'] ?? ''));
                $bodyText = trim((string) ($item['preview_text'] ?? ($item['body_text'] ?? '')));
                $bodyHtml = (string) ($item['preview_html'] ?? ($item['body_html'] ?? ''));
                ?>
                <article class="email-template-card" id="template-<?= e($eventKey) ?>">
                    <div class="email-template-card-head">
                        <div>
                            <span class="pill"><?= e($eventKey) ?></span>
                            <h2 class="email-template-title"><?= e($name !== '' ? $name : $eventKey) ?></h2>
                            <div class="email-template-subject">
                                <i data-lucide="mail"></i>
                                <div><strong>Assunto:</strong> <?= e($subject) ?></div>
                            </div>
                        </div>
                        <div class="email-template-status">
                            <span class="pill"><?= !empty($item['enabled']) ? 'Ativo' : 'Inativo' ?></span>
                            <span class="pill">ID #<?= (int) ($item['id'] ?? 0) ?></span>
                        </div>
                    </div>

                    <details class="email-template-preview" <?= $index === 0 ? 'open' : '' ?>>
                        <summary>
                            <span><i data-lucide="eye"></i> Preview do template</span>
                            <i class="preview-chevron" data-lucide="chevron-down"></i>
                        </summary>
                        <div class="email-template-preview-body">
                            <div class="email-template-copy">
                                <div class="email-template-copy-box">
                                    <strong>Evento</strong>
                                    <code><?= e($eventKey) ?></code>
                                </div>
                                <div class="email-template-copy-box">
                                    <strong>Assunto renderizado</strong>
                                    <code><?= e($subject) ?></code>
                                </div>
                                <div class="email-template-copy-box">
                                    <strong>Texto fallback</strong>
                                    <pre><?= e($bodyText !== '' ? $bodyText : 'Sem corpo texto cadastrado.') ?></pre>
                                </div>
                            </div>
                            <div class="email-template-frame-wrap">
                                <div class="email-template-frame-head">
                                    <span><i data-lucide="monitor"></i> HTML do e-mail</span>
                                    <span><?= e($eventKey) ?></span>
                                </div>
                                <?php if ($bodyHtml !== ''): ?>
                                    <iframe class="email-template-frame" sandbox srcdoc="<?= e($bodyHtml) ?>"></iframe>
                                <?php else: ?>
                                    <div class="email-template-copy-box" style="margin:16px;">Sem corpo HTML cadastrado.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </details>
                </article>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <div class="table-wrap"><table><tbody><tr><td>Nenhum template cadastrado.</td></tr></tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
</section>
