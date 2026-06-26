<?php
/**
 * Tela de recuperação de senha (apenas informativa nesta etapa).
 */
?>

<section class="form-box auth-box">
    <span class="kicker kicker-dark">Recuperação de senha</span>

    <h1 class="h3-card auth-title">Esqueci minha senha</h1>

    <div class="notice" style="margin: 8px 0 20px;">
        <p class="mb-0">
            <i data-lucide="mail"></i>
            Recuperação de senha será habilitada após configuração SMTP.
        </p>
    </div>

    <p class="auth-text text-muted-dcx">
        Por enquanto, solicite a redefinição diretamente ao administrador do sistema.
    </p>

    <a href="<?= e(app_url('/login')) ?>" class="btn btn-light" style="width:100%;">
        <i data-lucide="arrow-left"></i> Voltar para o login
    </a>
</section>
